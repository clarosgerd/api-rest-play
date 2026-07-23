<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\EspnService;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncEspnCommand extends Command
{
    protected $signature = 'espn:sync
        {--league= : ESPN league ID (eng.1, esp.1, etc). Omit for all.}
        {--type=all : What to sync: all, teams, fixtures, players, standings}
        {--season= : Season year (default: current year)}';

    protected $description = 'Sync football data from ESPN (free, no API key) + football-data.org standings';

    private const LEAGUES = [
        'eng.1' => [
            'name' => 'Premier League',
            'country' => 'England',
            'country_code' => 'GB-ENG',
            'fd_id' => 2021,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/46/46.png&w=40&h=40&scale=crop',
        ],
        'esp.1' => [
            'name' => 'La Liga',
            'country' => 'Spain',
            'country_code' => 'ES',
            'fd_id' => 2014,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/87/87.png&w=40&h=40&scale=crop',
        ],
        'ger.1' => [
            'name' => 'Bundesliga',
            'country' => 'Germany',
            'country_code' => 'DE',
            'fd_id' => 2002,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/54/54.png&w=40&h=40&scale=crop',
        ],
        'ita.1' => [
            'name' => 'Serie A',
            'country' => 'Italy',
            'country_code' => 'IT',
            'fd_id' => 2019,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/55/55.png&w=40&h=40&scale=crop',
        ],
        'fra.1' => [
            'name' => 'Ligue 1',
            'country' => 'France',
            'country_code' => 'FR',
            'fd_id' => 2015,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/53/53.png&w=40&h=40&scale=crop',
        ],
        'mex.1' => [
            'name' => 'Liga MX',
            'country' => 'Mexico',
            'country_code' => 'MX',
            'fd_id' => null,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/23/23.png&w=40&h=40&scale=crop',
        ],
        'usa.1' => [
            'name' => 'MLS',
            'country' => 'USA',
            'country_code' => 'US',
            'fd_id' => null,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/19/19.png&w=40&h=40&scale=crop',
        ],
        'bra.1' => [
            'name' => 'Brasileirão',
            'country' => 'Brazil',
            'country_code' => 'BR',
            'fd_id' => null,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/24/24.png&w=40&h=40&scale=crop',
        ],
        'arg.1' => [
            'name' => 'Liga Profesional Argentina',
            'country' => 'Argentina',
            'country_code' => 'AR',
            'fd_id' => null,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/25/25.png&w=40&h=40&scale=crop',
        ],
        'ned.1' => [
            'name' => 'Eredivisie',
            'country' => 'Netherlands',
            'country_code' => 'NL',
            'fd_id' => 2003,
            'logo_url' => 'https://a.espncdn.com/combiner/i?img=/i/logos/soccer/leagues/32/32.png&w=40&h=40&scale=crop',
        ],
    ];

    public function handle(
        EspnService $espn,
        FootballDataService $fd
    ): int {
        $type = $this->option('type');
        $season = $this->option('season') ?? (string) date('Y');
        $leagueFilter = $this->option('league');

        $leagues = $leagueFilter
            ? [$leagueFilter => self::LEAGUES[$leagueFilter] ?? null]
            : self::LEAGUES;

        $leagues = array_filter($leagues);

        if ($leagueFilter && ! isset(self::LEAGUES[$leagueFilter])) {
            $this->error("Unknown league: {$leagueFilter}");
            $this->line('Available leagues: ' . implode(', ', array_keys(self::LEAGUES)));

            return 1;
        }

        $this->info("ESPN Sync — {$type} | Season {$season} | " . count($leagues) . " leagues");
        $this->newLine();

        $totalTeams = 0;
        $totalFixtures = 0;
        $totalPlayers = 0;

        foreach ($leagues as $espnId => $config) {
            $this->line("── {$config['name']} ({$config['country']}) ──");

            $tournament = $this->getOrCreateTournament($config, $season);

            if (in_array($type, ['all', 'teams'])) {
                $totalTeams += $this->syncTeams($espn, $tournament, $espnId, $config);
            }

            if (in_array($type, ['all', 'fixtures'])) {
                $totalFixtures += $this->syncFixtures($espn, $tournament, $espnId, $config);
            }

            if (in_array($type, ['all', 'players'])) {
                $totalPlayers += $this->syncPlayers($espn, $tournament, $espnId);
            }

            if (in_array($type, ['all', 'standings'])) {
                $this->syncStandingsFromFdc($fd, $tournament, $config);
            }

            $this->newLine();
        }

        $this->info("Done: {$totalTeams} teams, {$totalFixtures} fixtures, {$totalPlayers} players.");

        return 0;
    }

    private function syncTeams(
        EspnService $espn,
        Tournament $tournament,
        string $espnId,
        array $config
    ): int {
        $this->info('  Syncing teams...');

        $data = $espn->getTeams($espnId);
        $teams = $data['sports'][0]['leagues'][0]['teams'] ?? [];

        if (empty($teams)) {
            $this->warn('  No teams found.');

            return 0;
        }

        $synced = 0;

        foreach ($teams as $item) {
            $t = $item['team'];
            $existing = Team::where('external_id', (int) $t['id'])->first();

            $attrs = [
                'name' => $t['displayName'] ?? $t['name'] ?? "Team {$t['id']}",
                'country' => $config['country'],
                'country_code' => $config['country_code'],
                'league' => $config['name'],
                'logo_url' => $this->getTeamLogo($t) ?? $config['logo_url'],
                'flag_url' => null,
                'active' => true,
                'external_id' => (int) $t['id'],
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                Team::create($attrs);
            }

            $synced++;
        }

        $this->info("    {$synced} teams synced.");

        return $synced;
    }

    private function syncFixtures(
        EspnService $espn,
        Tournament $tournament,
        string $espnId,
        array $config
    ): int {
        $this->info('  Syncing fixtures...');

        $calendarData = $espn->getScoreboard($espnId);
        $calendar = $calendarData['leagues'][0]['calendar'] ?? [];

        if (empty($calendar)) {
            $this->warn('  No calendar found.');

            return 0;
        }

        $synced = 0;
        $processed = 0;

        foreach ($calendar as $dateStr) {
            if ($processed >= 15) {
                break;
            }

            $dateParam = str_replace('-', '', substr($dateStr, 0, 10));
            $boardData = $espn->getScoreboard($espnId, $dateParam);
            $events = $boardData['events'] ?? [];

            foreach ($events as $event) {
                $comp = $event['competitions'][0] ?? null;
                if (! $comp || count($comp['competitors'] ?? []) < 2) {
                    continue;
                }

                $homeComp = $comp['competitors'][0];
                $awayComp = $comp['competitors'][1];

                $homeTeam = Team::where('external_id', (int) $homeComp['team']['id'])->first();
                $awayTeam = Team::where('external_id', (int) $awayComp['team']['id'])->first();

                if (! $homeTeam || ! $awayTeam) {
                    continue;
                }

                $externalId = (int) $event['id'];
                $existing = Matches::where('external_id', $externalId)->first();

                $matchDate = null;
                if (! empty($event['date'])) {
                    $matchDate = Carbon::parse($event['date']);
                }

                $stage = $this->getOrCreateStage($tournament, 'Regular Season');

                $statusType = $comp['status']['type']['name'] ?? 'STATUS_SCHEDULED';

                $attrs = [
                    'home_team_id' => $homeTeam->team_id,
                    'away_team_id' => $awayTeam->team_id,
                    'tournament_id' => $tournament->tournament_id,
                    'stage_id' => $stage->stage_id,
                    'match_date' => $matchDate,
                    'status' => $this->mapEspnStatus($statusType),
                    'home_score' => (int) ($homeComp['score'] ?? 0),
                    'away_score' => (int) ($awayComp['score'] ?? 0),
                    'external_id' => $externalId,
                ];

                if ($existing) {
                    $existing->update($attrs);
                } else {
                    Matches::create($attrs);
                }

                $synced++;
            }

            $processed++;
            usleep(200000);
        }

        $this->info("    {$synced} fixtures synced.");

        return $synced;
    }

    private function syncPlayers(
        EspnService $espn,
        Tournament $tournament,
        string $espnId
    ): int {
        $this->info('  Syncing players...');

        $data = $espn->getTeams($espnId);
        $teams = $data['sports'][0]['leagues'][0]['teams'] ?? [];

        if (empty($teams)) {
            return 0;
        }

        $synced = 0;

        foreach ($teams as $item) {
            $t = $item['team'];
            $teamId = (int) $t['id'];
            $team = Team::where('external_id', $teamId)->first();

            if (! $team) {
                continue;
            }

            $rosterData = $espn->getRoster($espnId, $teamId);
            $athletes = $rosterData['athletes'] ?? [];

            foreach ($athletes as $athlete) {
                $existing = Player::where('external_id', (int) $athlete['id'])->first();

                $position = $athlete['position']['name'] ?? null;

                $attrs = [
                    'team_id' => $team->team_id,
                    'name' => $athlete['displayName'] ?? $athlete['fullName'] ?? '',
                    'first_name' => $athlete['firstName'] ?? null,
                    'last_name' => $athlete['lastName'] ?? null,
                    'position' => $position,
                    'nationality' => $athlete['citizenship'] ?? null,
                    'birth_date' => isset($athlete['dateOfBirth'])
                        ? Carbon::parse($athlete['dateOfBirth'])->toDateString()
                        : null,
                    'age' => $athlete['age'] ?? null,
                    'photo_url' => $this->getAthletePhoto($athlete),
                    'external_id' => (int) $athlete['id'],
                ];

                if ($existing) {
                    $existing->update($attrs);
                } else {
                    Player::create($attrs);
                }

                $synced++;
            }

            usleep(300000);
        }

        $this->info("    {$synced} players synced.");

        return $synced;
    }

    private function syncStandingsFromFdc(
        FootballDataService $fd,
        Tournament $tournament,
        array $config
    ): void {
        if (! $config['fd_id']) {
            $this->info('    Standings: no football-data.org ID for this league, skipping.');

            return;
        }

        $this->info('  Syncing standings from football-data.org...');

        $data = $fd->getStandings($config['fd_id']);

        $standings = $data['standings'] ?? [];
        if (empty($standings)) {
            $this->warn('    No standings data available.');

            return;
        }

        $stage = $this->getOrCreateStage($tournament, 'Standings');

        foreach ($standings as $group) {
            $groupName = $group['group'] ?? 'Overall';

            foreach ($group['table'] as $row) {
                $teamExternalId = $row['team']['id'] ?? null;
                if (! $teamExternalId) {
                    continue;
                }

                $team = Team::where('external_id', $teamExternalId)->first();
                if (! $team) {
                    $team = Team::create([
                        'name' => $row['team']['name'] ?? $row['team']['shortName'] ?? "Team {$teamExternalId}",
                        'country' => $config['country'],
                        'country_code' => $config['country_code'],
                        'league' => $config['name'],
                        'logo_url' => $row['team']['crest'] ?? $config['logo_url'],
                        'flag_url' => null,
                        'active' => true,
                        'external_id' => $teamExternalId,
                    ]);
                }
            }
        }

        $this->info('    Standings synced (displayed via API).');
    }

    private function getOrCreateTournament(array $config, string $season): Tournament
    {
        $externalId = $this->getLeagueExternalId($config['name']);

        $existing = Tournament::where('external_id', $externalId)
            ->where('season', $season)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Tournament::create([
            'name' => $config['name'],
            'season' => $season,
            'country' => $config['country'],
            'country_code' => $config['country_code'],
            'logo_url' => $config['logo_url'],
            'start_date' => "{$season}-08-01",
            'end_date' => (int) $season + 1 . "-06-30",
            'active' => true,
            'external_id' => $externalId,
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

    private function mapEspnStatus(string $statusType): string
    {
        return match ($statusType) {
            'STATUS_FINAL', 'STATUS_FULL_TIME' => 'finished',
            'STATUS_SCHEDULED', 'STATUS_NOT_STARTED' => 'scheduled',
            'STATUS_HALFTIME', 'STATUS_SECOND_HALF', 'STATUS_FIRST_HALF',
            'STATUS_IN_PROGRESS', 'STATUS_FULL_TIME_2ND_HALF' => 'live',
            'STATUS_CANCELED', 'STATUS_POSTPONED' => 'canceled',
            default => 'scheduled',
        };
    }

    private function getLeagueExternalId(string $leagueName): int
    {
        return match ($leagueName) {
            'Premier League' => 10001,
            'La Liga' => 10002,
            'Bundesliga' => 10003,
            'Serie A' => 10004,
            'Ligue 1' => 10005,
            'Liga MX' => 10006,
            'MLS' => 10007,
            'Brasileirão' => 10008,
            'Liga Profesional Argentina' => 10009,
            'Eredivisie' => 10010,
            default => crc32($leagueName),
        };
    }

    private function getTeamLogo(array $team): ?string
    {
        $logos = $team['logos'] ?? [];

        foreach ($logos as $logo) {
            if (isset($logo['href']) && ($logo['width'] ?? 0) >= 80) {
                return $logo['href'];
            }
        }

        return $logos[0]['href'] ?? null;
    }

    private function getAthletePhoto(array $athlete): ?string
    {
        $headshot = $athlete['headshot'] ?? null;

        if ($headshot && isset($headshot['href'])) {
            return $headshot['href'];
        }

        return null;
    }
}
