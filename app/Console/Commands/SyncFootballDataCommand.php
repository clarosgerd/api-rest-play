<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFootballDataCommand extends Command
{
    protected $signature = 'football:sync {--competition= : Specific competition ID to sync} {--type=competitions : What to sync: competitions, teams, matches}';

    protected $description = 'Sync football data from football-data.org API';

    public function handle(FootballDataService $api)
    {
        $token = config('services.football_data.token');

        if (empty($token)) {
            $this->error('FOOTBALL_DATA_API_TOKEN not set in .env');

            return 1;
        }

        $type = $this->option('type');
        $competitionId = $this->option('competition');

        $this->info("Syncing {$type} from football-data.org...");

        match ($type) {
            'competitions' => $this->syncCompetitions($api),
            'teams' => $this->syncTeams($api, $competitionId),
            'matches' => $this->syncMatches($api, $competitionId),
            default => $this->error("Unknown sync type: {$type}"),
        };

        return 0;
    }

    private function syncCompetitions(FootballDataService $api): void
    {
        $data = $api->getCompetitions();

        if (empty($data['competitions'])) {
            $this->error('No competitions found or API request failed.');

            return;
        }

        $synced = 0;

        foreach ($data['competitions'] as $comp) {
            $existing = Tournament::where('external_id', $comp['id'])->first();

            $tournamentData = [
                'name' => $comp['name'] ?? $comp['code'] ?? "Competition {$comp['id']}",
                'season' => $comp['currentSeason']['startDate'] ?? date('Y'),
                'country' => $comp['area']['name'] ?? '',
                'country_code' => $comp['area']['code'] ?? '',
                'logo_url' => $comp['emblemUrl'] ?? null,
                'flag_url' => $comp['area']['flag'] ?? null,
                'start_date' => $comp['currentSeason']['startDate'] ?? date('Y-m-d'),
                'end_date' => $comp['currentSeason']['endDate'] ?? date('Y-m-d'),
                'active' => true,
                'external_id' => $comp['id'],
            ];

            if ($existing) {
                $existing->update($tournamentData);
            } else {
                Tournament::create($tournamentData);
            }

            $synced++;
            $this->line("  Synced: {$comp['name']}");
        }

        $this->info("Done. Synced {$synced} competitions.");
    }

    private function syncTeams(FootballDataService $api, ?int $competitionId): void
    {
        if (! $competitionId) {
            $competitions = Tournament::whereNotNull('external_id')->get();

            if ($competitions->isEmpty()) {
                $this->error('No competitions with external_id found. Run: php artisan football:sync --type=competitions');

                return;
            }

            foreach ($competitions as $comp) {
                $this->syncTeamsForCompetition($api, $comp);
            }
        } else {
            $comp = Tournament::where('external_id', $competitionId)->first();

            if (! $comp) {
                $this->error("Competition with external_id {$competitionId} not found in database.");

                return;
            }

            $this->syncTeamsForCompetition($api, $comp);
        }
    }

    private function syncTeamsForCompetition(FootballDataService $api, Tournament $tournament): void
    {
        $this->info("Syncing teams for: {$tournament->name}");

        $data = $api->getCompetitionTeams($tournament->external_id);

        if (empty($data['teams'])) {
            $this->warn("  No teams found for {$tournament->name}");

            return;
        }

        $synced = 0;

        foreach ($data['teams'] as $teamData) {
            $existing = Team::where('external_id', $teamData['id'])->first();

            $teamAttributes = [
                'name' => $teamData['name'] ?? $teamData['shortName'] ?? "Team {$teamData['id']}",
                'country' => $teamData['area']['name'] ?? $tournament->country,
                'country_code' => $teamData['area']['code'] ?? $tournament->country_code,
                'league' => $tournament->name,
                'logo_url' => $teamData['crestUrl'] ?? null,
                'flag_url' => $teamData['area']['flag'] ?? null,
                'active' => true,
                'external_id' => $teamData['id'],
            ];

            if ($existing) {
                $existing->update($teamAttributes);
            } else {
                Team::create($teamAttributes);
            }

            $synced++;
        }

        $this->info("  Synced {$synced} teams for {$tournament->name}");
    }

    private function syncMatches(FootballDataService $api, ?int $competitionId): void
    {
        if (! $competitionId) {
            $competitions = Tournament::whereNotNull('external_id')->get();

            if ($competitions->isEmpty()) {
                $this->error('No competitions with external_id found. Run: php artisan football:sync --type=competitions');

                return;
            }

            foreach ($competitions as $comp) {
                $this->syncMatchesForCompetition($api, $comp);
            }
        } else {
            $comp = Tournament::where('external_id', $competitionId)->first();

            if (! $comp) {
                $this->error("Competition with external_id {$competitionId} not found in database.");

                return;
            }

            $this->syncMatchesForCompetition($api, $comp);
        }
    }

    private function syncMatchesForCompetition(FootballDataService $api, Tournament $tournament): void
    {
        $this->info("Syncing matches for: {$tournament->name}");

        $data = $api->getCompetitionMatches($tournament->external_id, [
            'status' => 'SCHEDULED,LIVE,IN_PLAY,PAUSED,FINISHED',
        ]);

        if (empty($data['matches'])) {
            $this->warn("  No matches found for {$tournament->name}");

            return;
        }

        $synced = 0;

        foreach ($data['matches'] as $matchData) {
            $homeTeam = Team::where('external_id', $matchData['homeTeam']['id'])->first();
            $awayTeam = Team::where('external_id', $matchData['awayTeam']['id'])->first();

            if (! $homeTeam || ! $awayTeam) {
                $this->warn('  Skipping match - teams not found in DB');

                continue;
            }

            $status = match ($matchData['status']) {
                'SCHEDULED', 'TIMED' => 'scheduled',
                'IN_PLAY', 'PAUSED' => 'live',
                'FINISHED' => 'finished',
                'SUSPENDED', 'POSTPONED', 'CANCELLED' => 'canceled',
                default => 'scheduled',
            };

            $existing = Matches::where('external_id', $matchData['id'])->first();

            $matchAttributes = [
                'home_team_id' => $homeTeam->team_id,
                'away_team_id' => $awayTeam->team_id,
                'tournament_id' => $tournament->tournament_id,
                'stage_id' => $this->getOrCreateStage($tournament, $matchData),
                'match_date' => Carbon::parse($matchData['utcDate']),
                'status' => $status,
                'home_score' => $matchData['score']['fullTime']['home'] ?? 0,
                'away_score' => $matchData['score']['fullTime']['away'] ?? 0,
                'external_id' => $matchData['id'],
            ];

            if ($existing) {
                $existing->update($matchAttributes);
            } else {
                Matches::create($matchAttributes);
            }

            $synced++;
        }

        $this->info("  Synced {$synced} matches for {$tournament->name}");
    }

    private function getOrCreateStage(Tournament $tournament, array $matchData): int
    {
        $stageName = $matchData['stage'] ?? 'Regular Season';

        $stage = TournamentStage::where('tournament_id', $tournament->tournament_id)
            ->where('name', $stageName)
            ->first();

        if (! $stage) {
            $stage = TournamentStage::create([
                'tournament_id' => $tournament->tournament_id,
                'name' => $stageName,
                'stage_order' => 1,
                'is_knockout' => str_contains($stageName, 'FINAL') || str_contains($stageName, 'SEMI'),
            ]);
        }

        return $stage->stage_id;
    }
}
