<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\ApiFootballService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncConmebolCommand extends Command
{
    protected $signature = 'conmebol:sync
        {--competition= : libertadores or sudamericana (default: both)}
        {--season= : Year like 2026 (default: current year)}';

    protected $description = 'Sync Copa Libertadores and Copa Sudamericana from API-Football';

    private const COMPETITIONS = [
        'libertadores' => [
            'api_football_id' => 13,
            'name' => 'Copa Libertadores',
        ],
        'sudamericana' => [
            'api_football_id' => 11,
            'name' => 'Copa Sudamericana',
        ],
    ];

    public function handle(ApiFootballService $apiFootball): int
    {
        $apiKey = config('services.api_football.key');
        if (empty($apiKey)) {
            $this->error('No API_FOOTBALL_KEY configured in .env');

            return 1;
        }

        $competition = $this->option('competition');
        $season = $this->option('season') ?? date('Y');

        $competitions = $competition
            ? [$competition => self::COMPETITIONS[$competition]]
            : self::COMPETITIONS;

        $this->info("Syncing CONMEBOL competitions for {$season}...");

        foreach ($competitions as $key => $config) {
            $this->newLine();
            $this->info("=== {$config['name']} ===");

            $synced = $this->syncCompetition($apiFootball, $config, $season);

            $this->info("Synced {$synced} matches for {$config['name']}.");
        }

        $this->newLine();
        $this->info('CONMEBOL sync complete.');

        return 0;
    }

    private function syncCompetition(
        ApiFootballService $api,
        array $config,
        string $season
    ): int {
        $leagueId = $config['api_football_id'];

        $tournament = $this->getOrCreateTournament(
            $config['name'],
            $season,
            $leagueId,
            'CONMEBOL',
            'CONMEBOL'
        );

        $teamsResponse = $api->getTeams($leagueId, (int) $season);

        if (! empty($teamsResponse['response'])) {
            foreach ($teamsResponse['response'] as $teamData) {
                $teamInfo = $teamData['team'];
                $existing = Team::where('external_id', $teamInfo['id'])->first();

                $teamAttributes = [
                    'name' => $teamInfo['name'] ?? $teamInfo['code'] ?? "Team {$teamInfo['id']}",
                    'country' => $teamInfo['country'] ?? 'CONMEBOL',
                    'country_code' => $teamInfo['country'] ?? '',
                    'league' => $tournament->name,
                    'logo_url' => $teamInfo['logo'] ?? null,
                    'flag_url' => null,
                    'active' => true,
                    'external_id' => $teamInfo['id'],
                ];

                if ($existing) {
                    $existing->update($teamAttributes);
                } else {
                    Team::create($teamAttributes);
                }
            }
            $this->info('  Synced '.count($teamsResponse['response']).' teams.');
        }

        $fixturesResponse = $api->getFixtures($leagueId, (int) $season);

        if (empty($fixturesResponse['response'])) {
            $this->warn('  No fixtures found on API-Football.');

            return 0;
        }

        $synced = 0;

        foreach ($fixturesResponse['response'] as $fixtureData) {
            $fixture = $fixtureData['fixture'];
            $goals = $fixtureData['goals'];
            $teams = $fixtureData['teams'];

            $homeTeam = Team::where('external_id', $teams['home']['id'])->first();
            $awayTeam = Team::where('external_id', $teams['away']['id'])->first();

            if (! $homeTeam || ! $awayTeam) {
                $this->warn("  Skipping match - teams not found in DB: {$teams['home']['name']} vs {$teams['away']['name']}");

                continue;
            }

            $status = $this->mapApiFootballStatus($fixture['status']['short'] ?? '');
            $externalId = $fixture['id'];

            $existing = Matches::where('external_id', $externalId)->first();

            $matchDate = null;
            if (! empty($fixture['date'])) {
                $matchDate = Carbon::parse($fixture['date']);
            }

            $round = $fixture['round'] ?? 'Group Stage';
            $stage = $this->getOrCreateStage($tournament, $round);

            $matchAttributes = [
                'home_team_id' => $homeTeam->team_id,
                'away_team_id' => $awayTeam->team_id,
                'tournament_id' => $tournament->tournament_id,
                'stage_id' => $stage->stage_id,
                'match_date' => $matchDate,
                'status' => $status,
                'home_score' => $goals['home'] ?? 0,
                'away_score' => $goals['away'] ?? 0,
                'external_id' => $externalId,
            ];

            if ($existing) {
                $existing->update($matchAttributes);
            } else {
                Matches::create($matchAttributes);
            }

            $synced++;
        }

        return $synced;
    }

    private function getOrCreateTournament(
        string $name,
        string $season,
        int $externalId,
        string $country,
        string $countryCode
    ): Tournament {
        $existing = Tournament::where('external_id', $externalId)->first();

        $tournamentData = [
            'name' => $name,
            'season' => $season,
            'country' => $country,
            'country_code' => $countryCode,
            'start_date' => $season.'-01-01',
            'end_date' => $season.'-12-31',
            'active' => true,
            'external_id' => $externalId,
        ];

        if ($existing) {
            $existing->update($tournamentData);

            return $existing;
        }

        return Tournament::create($tournamentData);
    }

    private function getOrCreateStage(Tournament $tournament, string $stageName): TournamentStage
    {
        $stage = TournamentStage::where('tournament_id', $tournament->tournament_id)
            ->where('name', $stageName)
            ->first();

        if (! $stage) {
            $stage = TournamentStage::create([
                'tournament_id' => $tournament->tournament_id,
                'name' => $stageName,
                'stage_order' => 1,
                'is_knockout' => str_contains(strtoupper($stageName), 'FINAL')
                    || str_contains(strtoupper($stageName), 'SEMI')
                    || str_contains(strtoupper($stageName), 'QUARTER'),
            ]);
        }

        return $stage;
    }

    private function mapApiFootballStatus(string $short): string
    {
        return match ($short) {
            'FT', 'AET', 'PEN' => 'finished',
            'NS', 'TBD', 'PST' => 'scheduled',
            '1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE' => 'live',
            'CANC', 'SUS', 'ABD', 'AWD', 'WO' => 'canceled',
            default => 'scheduled',
        };
    }
}
