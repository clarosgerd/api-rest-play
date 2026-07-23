<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class ImportTournamentCommand extends Command
{
    protected $signature = 'tournament:import
        {file : Path to JSON file}';

    protected $description = 'Bulk import a tournament from JSON (stages, teams, matches)';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: '.json_last_error_msg());

            return 1;
        }

        $tournament = $this->importTournament(Arr::get($data, 'tournament', []));
        $stages = $this->importStages($tournament, Arr::get($data, 'stages', []));
        $teams = $this->importTeams($tournament, Arr::get($data, 'teams', []));
        $matches = $this->importMatches($tournament, $stages, $teams, Arr::get($data, 'matches', []));

        $this->newLine();
        $this->info('Import complete:');
        $this->line("  Tournament: {$tournament->name} ({$tournament->season})");
        $this->line('  Stages: '.count($stages));
        $this->line('  Teams: '.count($teams));
        $this->line("  Matches: {$matches}");

        return 0;
    }

    private function importTournament(array $data): Tournament
    {
        $name = $data['name'] ?? 'Unknown Tournament';
        $season = $data['season'] ?? date('Y');

        $existing = Tournament::where('name', $name)
            ->where('season', $season)
            ->first();

        $attrs = [
            'name' => $name,
            'season' => $season,
            'country' => $data['country'] ?? 'Unknown',
            'country_code' => $data['country_code'] ?? '',
            'logo_url' => $data['logo_url'] ?? null,
            'flag_url' => $data['flag_url'] ?? null,
            'start_date' => $data['start_date'] ?? "{$season}-01-01",
            'end_date' => $data['end_date'] ?? "{$season}-12-31",
            'active' => $data['active'] ?? true,
            'external_id' => $data['external_id'] ?? null,
        ];

        if ($existing) {
            $existing->update($attrs);
            $this->info("Tournament updated: {$name}");
            $this->line("  Tournament ID: {$existing->tournament_id}");

            return $existing;
        }

        $tournament = Tournament::create($attrs);
        $this->info("Tournament created: {$name}");
        $this->line("  Tournament ID: {$tournament->tournament_id}");

        return $tournament;
    }

    private function importStages(Tournament $tournament, array $stages): array
    {
        $created = [];

        foreach ($stages as $i => $stageData) {
            $stageName = $stageData['name'] ?? 'Stage '.($i + 1);
            $order = $stageData['order'] ?? ($i + 1);
            $isKnockout = $stageData['is_knockout'] ?? false;

            $existing = TournamentStage::where('tournament_id', $tournament->tournament_id)
                ->where('name', $stageName)
                ->first();

            $attrs = [
                'tournament_id' => $tournament->tournament_id,
                'name' => $stageName,
                'stage_order' => $order,
                'is_knockout' => $isKnockout,
            ];

            if ($existing) {
                $existing->update($attrs);
                $created[$stageName] = $existing;
            } else {
                $stage = TournamentStage::create($attrs);
                $created[$stageName] = $stage;
            }
        }

        $this->info('  Stages: '.count($created));

        return $created;
    }

    private function importTeams(Tournament $tournament, array $teams): array
    {
        $created = [];

        foreach ($teams as $teamData) {
            $teamName = $teamData['name'] ?? 'Unknown Team';

            $existing = Team::where('name', $teamName)->first();

            $attrs = [
                'name' => $teamName,
                'country' => $teamData['country'] ?? $tournament->country,
                'country_code' => $teamData['country_code'] ?? $tournament->country_code,
                'league' => $tournament->name,
                'logo_url' => $teamData['logo_url'] ?? null,
                'flag_url' => $teamData['flag_url'] ?? null,
                'active' => true,
                'external_id' => $teamData['external_id'] ?? null,
            ];

            if ($existing) {
                $existing->update($attrs);
                $created[$teamName] = $existing;
            } else {
                $team = Team::create($attrs);
                $created[$teamName] = $team;
            }
        }

        $this->info('  Teams: '.count($created));

        return $created;
    }

    private function importMatches(
        Tournament $tournament,
        array $stages,
        array $teams,
        array $matches
    ): int {
        $synced = 0;

        foreach ($matches as $matchData) {
            $homeName = $matchData['home'] ?? null;
            $awayName = $matchData['away'] ?? null;

            $homeTeam = $teams[$homeName] ?? Team::where('name', $homeName)->first();
            $awayTeam = $teams[$awayName] ?? Team::where('name', $awayName)->first();

            if (! $homeTeam || ! $awayTeam) {
                $this->warn("  Skipping: {$homeName} vs {$awayName} (team not found)");

                continue;
            }

            $stageName = $matchData['stage'] ?? null;
            $stage = $stages[$stageName] ?? TournamentStage::where('tournament_id', $tournament->tournament_id)
                ->where('name', $stageName)
                ->first();

            if (! $stage) {
                $this->warn("  Stage not found: {$stageName}, using first stage");

                $stage = TournamentStage::where('tournament_id', $tournament->tournament_id)->first();
            }

            $externalId = $matchData['external_id'] ?? null;
            $existing = $externalId
                ? Matches::where('external_id', $externalId)->first()
                : null;

            $matchDate = null;
            if (! empty($matchData['date'])) {
                $matchDate = Carbon::parse($matchData['date']);
            }

            $attrs = [
                'home_team_id' => $homeTeam->team_id,
                'away_team_id' => $awayTeam->team_id,
                'tournament_id' => $tournament->tournament_id,
                'stage_id' => $stage->stage_id,
                'match_date' => $matchDate,
                'status' => $matchData['status'] ?? 'scheduled',
                'home_score' => $matchData['home_score'] ?? 0,
                'away_score' => $matchData['away_score'] ?? 0,
                'external_id' => $externalId,
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                Matches::create($attrs);
            }

            $synced++;
        }

        $this->info('  Matches: '.$synced);

        return $synced;
    }
}
