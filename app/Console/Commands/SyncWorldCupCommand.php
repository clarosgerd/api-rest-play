<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\ApiFootballService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncWorldCupCommand extends Command
{
    protected $signature = 'worldcup:sync {--season=2022 : Season year (free plan: 2022)} {--type=all : What to sync: all, teams, matches, players, standings}';

    protected $description = 'Sync World Cup data from API-Football (teams, matches, players, standings)';

    private const LEAGUE_ID = 1;

    public function handle(ApiFootballService $api): int
    {
        $apiKey = config('services.api_football.key');
        if (empty($apiKey)) {
            $this->error('API_FOOTBALL_KEY not set in .env');

            return 1;
        }

        $season = (int) $this->option('season');
        $type = $this->option('type');

        $this->info("Syncing World Cup {$season} from API-Football...");

        $tournament = $this->getOrCreateTournament($season);
        $tournament->update(['last_synced_at' => now()]);

        match ($type) {
            'all' => $this->syncAll($api, $tournament, $season),
            'teams' => $this->syncTeams($api, $tournament, $season),
            'matches' => $this->syncMatches($api, $tournament, $season),
            'players' => $this->syncPlayers($api, $tournament, $season),
            'standings' => $this->showStandings($api, $season),
            default => $this->error("Unknown sync type: {$type}"),
        };

        return 0;
    }

    private function syncAll(ApiFootballService $api, Tournament $tournament, int $season): void
    {
        $teams = $this->syncTeams($api, $tournament, $season);
        $matches = $this->syncMatches($api, $tournament, $season);
        $players = $this->syncPlayers($api, $tournament, $season);
        $this->showStandings($api, $season);

        $this->newLine();
        $this->info("Done: {$teams} teams, {$matches} matches, {$players} players synced.");
    }

    private function syncTeams(ApiFootballService $api, Tournament $tournament, int $season): int
    {
        $this->info('Syncing teams...');

        $response = $api->getTeams(self::LEAGUE_ID, $season);

        if (empty($response['response'])) {
            $this->warn('No teams found.');

            return 0;
        }

        $synced = 0;
        $now = now();

        foreach ($response['response'] as $teamData) {
            $teamInfo = $teamData['team'];
            $existing = Team::where('external_id', $teamInfo['id'])->first();

            $attrs = [
                'name' => $teamInfo['name'] ?? "Team {$teamInfo['id']}",
                'country' => $teamInfo['country'] ?? '',
                'country_code' => $teamInfo['country'] ?? '',
                'league' => 'World Cup ' . $season,
                'logo_url' => $teamInfo['logo'] ?? null,
                'flag_url' => $teamInfo['flag'] ?? null,
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

    private function syncMatches(ApiFootballService $api, Tournament $tournament, int $season): int
    {
        $this->info('Syncing matches...');

        $response = $api->getFixtures(self::LEAGUE_ID, $season);

        if (empty($response['response'])) {
            $this->warn('No matches found.');

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
                $this->warn("  Skipping: {$teams['home']['name']} vs {$teams['away']['name']} (teams not synced yet)");

                continue;
            }

            $externalId = $fixture['id'];
            $existing = Matches::where('external_id', $externalId)->first();

            $matchDate = null;
            if (! empty($fixture['date'])) {
                $matchDate = Carbon::parse($fixture['date']);
            }

            $round = $fixture['round'] ?? 'Group Stage';
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

    private function syncPlayers(ApiFootballService $api, Tournament $tournament, int $season): int
    {
        $this->info('Syncing players...');

        $totalSynced = 0;
        $page = 1;
        $maxPages = 3;

        while ($page <= $maxPages) {
            $response = $api->getPlayers(self::LEAGUE_ID, $season, $page);

            if (empty($response['response'])) {
                break;
            }

            foreach ($response['response'] as $playerData) {
                $playerInfo = $playerData['player'];
                $existing = Player::where('external_id', $playerInfo['id'])->first();

                $teamName = $playerData['statistics'][0]['team']['name'] ?? null;
                $team = $teamName ? Team::where('name', $teamName)->first() : null;

                $birthDate = null;
                if (! empty($playerInfo['birth']['date'])) {
                    $birthDate = Carbon::parse($playerInfo['birth']['date'])->toDateString();
                }

                $attrs = [
                    'team_id' => $team?->team_id,
                    'name' => $playerInfo['name'] ?? '',
                    'first_name' => $playerInfo['firstname'] ?? null,
                    'last_name' => $playerInfo['lastname'] ?? null,
                    'position' => $playerInfo['position'] ?? null,
                    'nationality' => is_array($playerInfo['nationality'])
                        ? ($playerInfo['nationality'][0] ?? null)
                        : $playerInfo['nationality'] ?? null,
                    'birth_date' => $birthDate,
                    'age' => $playerInfo['age'] ?? null,
                    'photo_url' => $playerInfo['photo'] ?? null,
                    'external_id' => $playerInfo['id'],
                ];

                if ($existing) {
                    $existing->update($attrs);
                } else {
                    Player::create($attrs);
                }

                $totalSynced++;
            }

            $paging = $response['paging'] ?? [];
            if ($page >= ($paging['total'] ?? 1)) {
                break;
            }

            $page++;
            usleep(500000);
        }

        $this->info("  Synced {$totalSynced} players.");

        return $totalSynced;
    }

    private function showStandings(ApiFootballService $api, int $season): void
    {
        $this->info('Fetching standings...');

        $response = $api->getStandings(self::LEAGUE_ID, $season);

        if (empty($response['response'])) {
            $this->warn('  Standings not available for this season/plan.');

            return;
        }

        foreach ($response['response'] as $leagueData) {
            foreach ($leagueData['league']['standings'] as $group) {
                $groupName = $group[0]['group'] ?? 'Unknown';
                $this->newLine();
                $this->info("  {$groupName}:");

                foreach ($group as $row) {
                    $this->line("    {$row['rank']}. {$row['team']['name']}  Pts:{$row['points']}  GD:{$row['goalsDiff']}  W:{$row['all']['win']}/D:{$row['all']['draw']}/L:{$row['all']['lose']}");
                }
            }
        }
    }

    private function getOrCreateTournament(int $season): Tournament
    {
        $existing = Tournament::where('external_id', self::LEAGUE_ID)
            ->where('season', (string) $season)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Tournament::create([
            'name' => 'World Cup ' . $season,
            'season' => (string) $season,
            'country' => 'World',
            'country_code' => 'WW',
            'logo_url' => 'https://media.api-sports.io/football/leagues/1.png',
            'start_date' => $season . '-06-11',
            'end_date' => $season . '-07-19',
            'active' => true,
            'external_id' => self::LEAGUE_ID,
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
