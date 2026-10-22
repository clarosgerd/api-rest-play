<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Services\ApiFootballService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncConmebolCommand extends Command
{
    protected $signature = 'conmebol:sync
        {--competition= : libertadores or sudamericana (default: both)}
        {--season= : Year like 2026 (default: current year)}
        {--topscorers : Also sync top scorers from API-Football}';

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

            $this->syncGroupStandings($apiFootball, $config, $season);

            if ($this->option('topscorers')) {
                $this->syncTopScorers($apiFootball, $config, $season);
            }
        }

        $this->newLine();
        $this->info('CONMEBOL sync complete.');

        return 0;
    }

    private function syncTopScorers(ApiFootballService $api, array $config, string $season): int
    {
        $this->info("  Syncing top scorers for {$config['name']} {$season}...");

        $tournament = Tournament::where('external_id', $config['api_football_id'])->first();
        if (! $tournament) {
            $this->warn('  Tournament not found. Run the regular sync first.');

            return 0;
        }

        $response = $api->getTopScorers($config['api_football_id'], (int) $season);

        if (empty($response['response'])) {
            $this->warn('  No top scorers returned by API-Football.');

            return 0;
        }

        $synced = 0;
        foreach ($response['response'] as $entry) {
            $playerData = $entry['player'];
            $stat = $entry['statistics'][0] ?? null;

            if (! $stat) {
                continue;
            }

            $teamExternalId = $stat['team']['id'] ?? null;
            $team = $teamExternalId ? Team::where('external_id', $teamExternalId)->first() : null;

            $externalId = $playerData['id'];
            $seasonValue = (string) $season;

            $attributes = [
                'team_id' => $team?->team_id,
                'name' => $playerData['name'] ?? '',
                'first_name' => $playerData['firstname'] ?? null,
                'last_name' => $playerData['lastname'] ?? null,
                'position' => $stat['games']['position'] ?? null,
                'nationality' => $playerData['nationality'] ?? null,
                'birth_date' => $playerData['birth']['date'] ?? null,
                'age' => $playerData['age'] ?? null,
                'photo_url' => $playerData['photo'] ?? null,
                'goals' => (int) ($stat['goals']['total'] ?? 0),
                'assists' => (int) ($stat['goals']['assists'] ?? 0),
                'games_played' => (int) ($stat['games']['appearences'] ?? 0),
                'games_minutes' => (int) ($stat['games']['minutes'] ?? 0),
                'tournament_id' => $tournament->tournament_id,
                'season' => $seasonValue,
            ];

            Player::updateOrCreate(
                ['external_id' => $externalId, 'tournament_id' => $tournament->tournament_id, 'season' => $seasonValue],
                $attributes
            );

            $synced++;
        }

        $this->info("  Synced {$synced} top scorers for {$config['name']} {$season}.");
        return $synced;
    }

    /**
     * Grupos/standings reales de Libertadores/Sudamericana (22/10/2026) —
     * dejado armado para cuando haya plan de pago de API-Football: hoy el
     * plan free devuelve `errors.plan` para la temporada actual y solo
     * responde para 2022-2024 (confirmado a mano contra la API real). El
     * mismo problema de "se pedía y se tiraba" que tenía el sync de
     * football-data.org (ver SyncFootballDataCommand::persistGroupStandings)
     * nunca llegó a existir acá porque este comando no pedía standings en
     * absoluto; ahora sí los pide y, si la respuesta trae datos, los
     * persiste en tournamentgroups/tournamentgroupteams.
     */
    private function syncGroupStandings(ApiFootballService $api, array $config, string $season): int
    {
        $tournament = Tournament::where('external_id', $config['api_football_id'])->first();
        if (! $tournament) {
            $this->warn('  Standings: tournament not found. Run the regular sync first.');

            return 0;
        }

        $response = $api->getStandings($config['api_football_id'], (int) $season);

        if (! empty($response['errors']['plan'])) {
            $this->warn("  Standings: {$response['errors']['plan']}");

            return 0;
        }

        $groups = $response['response'][0]['league']['standings'] ?? [];
        if (empty($groups)) {
            $this->warn('  No group standings returned by API-Football.');

            return 0;
        }

        // Stage dedicado a los grupos (no es el mismo que usan los partidos,
        // que acá se guardan por jornada: "Group Stage - 1", "Group Stage -
        // 2", ...). Mismo criterio de este comando: getOrCreateStage() ya se
        // llama libremente por cada round sin exigir que exista antes.
        $stage = $this->getOrCreateStage($tournament, 'Group Stage');
        $now = now();
        $groupsSynced = 0;

        foreach ($groups as $groupRows) {
            $groupName = $groupRows[0]['group'] ?? null;
            if (! $groupName) {
                continue;
            }

            $tournamentGroup = TournamentGroup::updateOrCreate(
                ['stage_id' => $stage->stage_id, 'name' => $groupName],
                ['status' => 'approved', 'group_order' => $this->groupOrder($groupName)]
            );
            $groupsSynced++;

            foreach ($groupRows as $row) {
                $teamExternalId = $row['team']['id'] ?? null;
                if (! $teamExternalId) {
                    continue;
                }

                $team = Team::where('external_id', $teamExternalId)->first();
                if (! $team) {
                    $team = Team::create([
                        'name' => $row['team']['name'] ?? "Team {$teamExternalId}",
                        'country' => 'CONMEBOL',
                        'country_code' => 'CONMEBOL',
                        'league' => $config['name'],
                        'logo_url' => $row['team']['logo'] ?? null,
                        'flag_url' => null,
                        'active' => true,
                        'external_id' => $teamExternalId,
                        'last_synced_at' => $now,
                    ]);
                }

                TournamentGroupTeam::updateOrCreate(
                    ['group_id' => $tournamentGroup->group_id, 'team_id' => $team->team_id],
                    [
                        'position_final' => $row['rank'] ?? null,
                        'points' => $row['points'] ?? 0,
                        'goal_diff' => $row['goalsDiff'] ?? 0,
                        'goals_for' => $row['all']['goals']['for'] ?? 0,
                        'goals_against' => $row['all']['goals']['against'] ?? 0,
                        'matches_played' => $row['all']['played'] ?? 0,
                        'wins' => $row['all']['win'] ?? 0,
                        'draws' => $row['all']['draw'] ?? 0,
                        'losses' => $row['all']['lose'] ?? 0,
                    ]
                );
            }
        }

        $this->info("  Synced {$groupsSynced} groups.");

        return $groupsSynced;
    }

    /**
     * "Group A" -> 1, "Group B" -> 2, etc. Solo ordena la UI, no identifica
     * al grupo (eso lo hace 'name').
     */
    private function groupOrder(string $groupName): int
    {
        if (preg_match('/([A-Z])$/', $groupName, $m)) {
            return ord($m[1]) - ord('A') + 1;
        }

        return 1;
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
            $now = now();
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
                    'last_synced_at' => $now,
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
        $now = now();

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
                'last_synced_at' => $now,
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
            'league_category' => 'conmebol',
            'last_synced_at' => now(),
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
