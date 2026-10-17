<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\ApiFootballService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncLeagueCommand extends Command
{
    protected $signature = 'league:sync
        {--league-id= : API-Football league ID}
        {--name= : Tournament name (auto-detected if not provided)}
        {--country= : Country name (auto-detected if not provided)}
        {--season= : Year like 2024 (default: current year)}';

    protected $description = 'Sync any league from API-Football (teams + fixtures). NOTE: free plan only allows seasons 2022-2024.';

    private const KNOWN_LEAGUES = [
        'bolivia' => ['id' => 344, 'name' => 'Bolivian Primera División', 'country' => 'Bolivia'],
        'argentina' => ['id' => 128, 'name' => 'Argentine Primera División', 'country' => 'Argentina'],
        'brazil' => ['id' => 71, 'name' => 'Brasileirão', 'country' => 'Brazil'],
        'chile' => ['id' => 266, 'name' => 'Chilean Primera División', 'country' => 'Chile'],
        'colombia' => ['id' => 239, 'name' => 'Colombian Liga', 'country' => 'Colombia'],
        'ecuador' => ['id' => 242, 'name' => 'Ecuadorian Serie A', 'country' => 'Ecuador'],
        'peru' => ['id' => 282, 'name' => 'Peruvian Primera División', 'country' => 'Peru'],
        'uruguay' => ['id' => 269, 'name' => 'Uruguayan Primera División', 'country' => 'Uruguay'],
        'paraguay' => ['id' => 254, 'name' => 'Paraguayan Primera División', 'country' => 'Paraguay'],
        'venezuela' => ['id' => 267, 'name' => 'Venezuelan Primera División', 'country' => 'Venezuela'],
        'mexico' => ['id' => 262, 'name' => 'Liga MX', 'country' => 'Mexico'],
        'usa' => ['id' => 253, 'name' => 'MLS', 'country' => 'USA'],
        'spain' => ['id' => 140, 'name' => 'La Liga', 'country' => 'Spain'],
        'england' => ['id' => 39, 'name' => 'Premier League', 'country' => 'England'],
        'germany' => ['id' => 78, 'name' => 'Bundesliga', 'country' => 'Germany'],
        'italy' => ['id' => 135, 'name' => 'Serie A', 'country' => 'Italy'],
        'france' => ['id' => 61, 'name' => 'Ligue 1', 'country' => 'France'],
        'portugal' => ['id' => 94, 'name' => 'Primeira Liga', 'country' => 'Portugal'],
        'netherlands' => ['id' => 88, 'name' => 'Eredivisie', 'country' => 'Netherlands'],
        'belgium' => ['id' => 144, 'name' => 'Jupiler Pro League', 'country' => 'Belgium'],
        'turkey' => ['id' => 203, 'name' => 'Süper Lig', 'country' => 'Turkey'],
        'champions-league' => ['id' => 2, 'name' => 'UEFA Champions League', 'country' => 'World'],
        'europa-league' => ['id' => 3, 'name' => 'UEFA Europa League', 'country' => 'World'],
        'conference-league' => ['id' => 848, 'name' => 'UEFA Europa Conference League', 'country' => 'World'],
    ];

    public function handle(ApiFootballService $api): int
    {
        $apiKey = config('services.api_football.key');
        if (empty($apiKey)) {
            $this->error('No API_FOOTBALL_KEY configured in .env');

            return 1;
        }

        $leagueId = $this->option('league-id');
        $season = $this->option('season') ?? date('Y');
        $name = $this->option('name');
        $country = $this->option('country');

        if (empty($leagueId)) {
            $this->error('Provide --league-id or use --help to see available leagues.');

            $this->newLine();
            $this->info('Known leagues:');
            foreach (self::KNOWN_LEAGUES as $key => $league) {
                $this->line("  {$key}: {$league['name']} (id={$league['id']}, {$league['country']})");
            }

            return 1;
        }

        $leagueId = (int) $leagueId;

        if (empty($name) || empty($country)) {
            $detected = $this->detectLeague($api, $leagueId);
            $name = $name ?? $detected['name'] ?? "League {$leagueId}";
            $country = $country ?? $detected['country'] ?? 'Unknown';
        }

        $this->info("Syncing {$name} ({$country}) season {$season}...");

        $tournament = $this->getOrCreateTournament($name, $season, $leagueId, $country);
        $tournament->update(['last_synced_at' => now()]);
        $syncedTeams = $this->syncTeams($api, $tournament, $leagueId, (int) $season);
        $syncedMatches = $this->syncFixtures($api, $tournament, $leagueId, (int) $season);

        $this->newLine();
        $this->info("Done: {$syncedTeams} teams, {$syncedMatches} matches.");

        return 0;
    }

    private function detectLeague(ApiFootballService $api, int $leagueId): array
    {
        $response = $api->request('GET', '/leagues', ['id' => $leagueId]);

        if (! empty($response['response'][0])) {
            $league = $response['response'][0];

            return [
                'name' => $league['league']['name'] ?? "League {$leagueId}",
                'country' => $league['country']['name'] ?? 'Unknown',
            ];
        }

        return ['name' => "League {$leagueId}", 'country' => 'Unknown'];
    }

    private function syncTeams(
        ApiFootballService $api,
        Tournament $tournament,
        int $leagueId,
        int $season
    ): int {
        $response = $api->getTeams($leagueId, $season);

        if (empty($response['response'])) {
            $this->warn('  No teams found.');

            return 0;
        }

        $synced = 0;
        $now = now();

        foreach ($response['response'] as $teamData) {
            $teamInfo = $teamData['team'];
            $existing = Team::where('external_id', $teamInfo['id'])->first();

            $attrs = [
                'name' => $teamInfo['name'] ?? $teamInfo['code'] ?? "Team {$teamInfo['id']}",
                'country' => $teamInfo['country'] ?? $tournament->country,
                'country_code' => $teamInfo['country'] ?? '',
                'league' => $tournament->name,
                'logo_url' => $teamInfo['logo'] ?? null,
                'flag_url' => null,
                'active' => true,
                'external_id' => $teamInfo['id'],
                'last_synced_at' => $now,
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                Team::create($attrs);
            }

            $synced++;
        }

        $this->info("  Synced {$synced} teams.");

        return $synced;
    }

    private function syncFixtures(
        ApiFootballService $api,
        Tournament $tournament,
        int $leagueId,
        int $season
    ): int {
        $response = $api->getFixtures($leagueId, $season);

        if (empty($response['response'])) {
            $this->warn('  No fixtures found.');

            return 0;
        }

        $synced = 0;
        $now = now();

        foreach ($response['response'] as $fixtureData) {
            $fixture = $fixtureData['fixture'];
            $goals = $fixtureData['goals'];
            $teams = $fixtureData['teams'];

            $homeTeam = Team::where('external_id', $teams['home']['id'])->first();
            $awayTeam = Team::where('external_id', $teams['away']['id'])->first();

            if (! $homeTeam || ! $awayTeam) {
                $this->warn("  Skipping: {$teams['home']['name']} vs {$teams['away']['name']} (teams not in DB)");

                continue;
            }

            $externalId = $fixture['id'];
            $existing = Matches::where('external_id', $externalId)->first();

            $matchDate = null;
            if (! empty($fixture['date'])) {
                $matchDate = Carbon::parse($fixture['date']);
            }

            $round = $fixture['round'] ?? 'Regular Season';
            $stage = $this->getOrCreateStage($tournament, $round);

            $attrs = [
                'home_team_id' => $homeTeam->team_id,
                'away_team_id' => $awayTeam->team_id,
                'tournament_id' => $tournament->tournament_id,
                'stage_id' => $stage->stage_id,
                'match_date' => $matchDate,
                'status' => $this->mapStatus($fixture['status']['short'] ?? ''),
                'home_score' => $goals['home'] ?? 0,
                'away_score' => $goals['away'] ?? 0,
                'external_id' => $externalId,
                'last_synced_at' => $now,
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                Matches::create($attrs);
            }

            $synced++;
        }

        $this->info("  Synced {$synced} matches.");

        return $synced;
    }

    private function getOrCreateTournament(
        string $name,
        string $season,
        int $externalId,
        string $country
    ): Tournament {
        $existing = Tournament::where('external_id', $externalId)
            ->where('season', $season)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Tournament::create([
            'name' => $name,
            'season' => $season,
            'country' => $country,
            'country_code' => $country,
            'start_date' => $season.'-01-01',
            'end_date' => $season.'-12-31',
            'active' => true,
            'external_id' => $externalId,
            'last_synced_at' => now(),
        ]);
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

    private function mapStatus(string $short): string
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
