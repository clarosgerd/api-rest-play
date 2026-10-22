<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFootballDataCommand extends Command
{

    protected $signature = 'football:sync
        {--competition= : Specific competition ID to sync}
        {--type=all : What to sync: all, competitions, teams, matches, standings}
        {--force : Ignore last_synced_at and re-sync everything}';

    protected $description = 'Sync football data from football-data.org (13 free-tier competitions, current seasons)';

    /**
     * Competitions available in the free tier (verified 2026-07). Pública
     * para que SyncController::competitions() la exponga al panel admin sin
     * duplicar la lista en el frontend.
     */
    public const FREE_COMPETITIONS = [
        2021 => 'Premier League',
        2014 => 'Primera Division',
        2002 => 'Bundesliga',
        2019 => 'Serie A',
        2015 => 'Ligue 1',
        2003 => 'Eredivisie',
        2017 => 'Primeira Liga',
        2016 => 'Championship',
        2013 => 'Campeonato Brasileiro Série A',
        2001 => 'UEFA Champions League',
        2152 => 'Copa Libertadores',
        2000 => 'FIFA World Cup',
        2018 => 'European Championship',
    ];

    /**
     * Incremental sync thresholds (hours).
     */
    private const TEAMS_TTL = 24;

    private const MATCHES_TTL = 3;

    private const STANDINGS_TTL = 6;

    /**
     * Free tier: 10 requests/minute → sleep 7s between calls to stay safe.
     */
    private const THROTTLE_SECONDS = 7;

    public function handle(FootballDataService $api)
    {
        $token = config('services.football_data.token');

        if (empty($token)) {
            $this->error('FOOTBALL_DATA_API_TOKEN not set in .env');

            return 1;
        }

        $type = $this->option('type');
        $competitionId = $this->option('competition') ? (int) $this->option('competition') : null;
        $force = (bool) $this->option('force');

        $this->info("football-data.org sync: {$type}".($force ? ' (FORCED)' : ''));

        match ($type) {
            'competitions' => $this->syncCompetitions($api),
            'teams' => $this->syncTeams($api, $competitionId, $force),
            'matches' => $this->syncMatches($api, $competitionId, $force),
            'standings' => $this->syncStandings($api, $competitionId, $force),
            'all' => $this->syncAll($api, $competitionId, $force),
            default => $this->error("Unknown sync type: {$type}"),
        };

        return 0;
    }

    private function syncAll(FootballDataService $api, ?int $competitionId, bool $force): void
    {
        $this->syncCompetitions($api);
        $this->syncTeams($api, $competitionId, $force);
        $this->syncMatches($api, $competitionId, $force);
        $this->syncStandings($api, $competitionId, $force);
    }

    private function syncCompetitions(FootballDataService $api): void
    {
        $data = $api->getCompetitions();

        if (empty($data['competitions'])) {
            $this->error('No competitions found or API request failed.');

            return;
        }

        $synced = 0;
        $now = now();

        foreach ($data['competitions'] as $comp) {
            $existing = Tournament::where('external_id', $comp['id'])->first();

            $tournamentData = [
                'name' => $comp['name'] ?? $comp['code'] ?? "Competition {$comp['id']}",
                'season' => isset($comp['currentSeason']['startDate'])
                    ? substr($comp['currentSeason']['startDate'], 0, 4)
                    : date('Y'),
                'country' => $comp['area']['name'] ?? '',
                'country_code' => $comp['area']['code'] ?? '',
                'logo_url' => $comp['emblem'] ?? $comp['emblemUrl'] ?? null,
                'flag_url' => $comp['area']['flag'] ?? null,
                'start_date' => $comp['currentSeason']['startDate'] ?? date('Y-m-d'),
                'end_date' => $comp['currentSeason']['endDate'] ?? date('Y-m-d'),
                'active' => true,
                'external_id' => $comp['id'],
                // Copa Libertadores (2152) viaja por football-data.org pero es
                // CONMEBOL para el filtro de disponibilidad por plan — el resto
                // de FREE_COMPETITIONS son ligas/torneos europeos reales.
                'league_category' => (int) $comp['id'] === 2152 ? 'conmebol' : 'european',
                'last_synced_at' => $now,
            ];

            if ($existing) {
                $existing->update($tournamentData);
            } else {
                Tournament::create($tournamentData);
            }

            $synced++;
        }

        $this->info("  Synced {$synced} competitions.");
    }

    private function syncTeams(FootballDataService $api, ?int $competitionId, bool $force): void
    {
        $first = true;

        foreach ($this->targetCompetitions($competitionId) as $comp) {
            $lastTeamSync = Team::where('league', $comp->name)
                ->whereNotNull('last_synced_at')
                ->max('last_synced_at');

            if (! $force && $lastTeamSync && Carbon::parse($lastTeamSync)->gt(now()->subHours(self::TEAMS_TTL))) {
                $this->line("  Skipping teams for {$comp->name} (synced ".Carbon::parse($lastTeamSync)->diffForHumans().')');

                continue;
            }

            $this->throttle($first);
            $this->syncTeamsForCompetition($api, $comp);
        }
    }

    private function syncTeamsForCompetition(FootballDataService $api, Tournament $tournament): void
    {
        $this->info("  Teams: {$tournament->name}");

        $data = $api->getCompetitionTeams($tournament->external_id);

        if (empty($data['teams'])) {
            $this->warn("    No teams found for {$tournament->name}");

            return;
        }

        $synced = 0;
        $now = now();

        foreach ($data['teams'] as $teamData) {
            $existing = Team::where('external_id', $teamData['id'])->first();

            $teamAttributes = [
                'name' => $teamData['name'] ?? $teamData['shortName'] ?? "Team {$teamData['id']}",
                'country' => $teamData['area']['name'] ?? $tournament->country,
                'country_code' => $teamData['area']['code'] ?? $tournament->country_code,
                'league' => $tournament->name,
                'logo_url' => $teamData['crest'] ?? $teamData['crestUrl'] ?? null,
                'flag_url' => $teamData['area']['flag'] ?? null,
                'active' => true,
                'external_id' => $teamData['id'],
                'last_synced_at' => $now,
            ];

            if ($existing) {
                $existing->update($teamAttributes);
            } else {
                Team::create($teamAttributes);
            }

            $synced++;
        }

        $tournament->update(['last_synced_at' => $now]);
        $this->info("    {$synced} teams synced.");
    }

    private function syncMatches(FootballDataService $api, ?int $competitionId, bool $force): void
    {
        $first = true;

        foreach ($this->targetCompetitions($competitionId) as $comp) {
            if ($this->syncMatchesForCompetition($api, $comp, $force, $first)) {
                // did NOT skip → count as an API call
            }
        }
    }

    private function syncMatchesForCompetition(FootballDataService $api, Tournament $tournament, bool $force, bool &$first = true): bool
    {
        // For matches we check the newest match sync time instead of the tournament's
        $lastMatchSync = Matches::where('tournament_id', $tournament->tournament_id)
            ->whereNotNull('last_synced_at')
            ->max('last_synced_at');

        if (! $force && $lastMatchSync && Carbon::parse($lastMatchSync)->gt(now()->subHours(self::MATCHES_TTL))) {
            $this->line("  Skipping matches for {$tournament->name} (synced ".Carbon::parse($lastMatchSync)->diffForHumans().')');

            return false;
        }

        $this->info("  Matches: {$tournament->name}");

        $this->throttle($first);

        $data = $api->getCompetitionMatches($tournament->external_id, [
            'status' => 'SCHEDULED,TIMED,LIVE,IN_PLAY,PAUSED,FINISHED',
        ]);

        if (empty($data['matches'])) {
            $this->warn("    No matches found for {$tournament->name}");

            return true;
        }

        $synced = 0;
        $now = now();

        foreach ($data['matches'] as $matchData) {
            $homeTeam = Team::where('external_id', $matchData['homeTeam']['id'])->first();
            $awayTeam = Team::where('external_id', $matchData['awayTeam']['id'])->first();

            if (! $homeTeam || ! $awayTeam) {
                continue;
            }

            $status = match ($matchData['status']) {
                'SCHEDULED', 'TIMED' => 'scheduled',
                'IN_PLAY', 'PAUSED', 'LIVE' => 'live',
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
                'last_synced_at' => $now,
            ];

            if ($existing) {
                $existing->update($matchAttributes);
            } else {
                Matches::create($matchAttributes);
            }

            $synced++;
        }

        $this->info("    {$synced} matches synced.");

        return true;
    }

    private function syncStandings(FootballDataService $api, ?int $competitionId, bool $force): void
    {
        $first = true;

        foreach ($this->targetCompetitions($competitionId) as $comp) {
            $lastTeamSync = Team::where('league', $comp->name)
                ->whereNotNull('last_synced_at')
                ->max('last_synced_at');

            if (! $force && $lastTeamSync && Carbon::parse($lastTeamSync)->gt(now()->subHours(self::STANDINGS_TTL))) {
                $this->line("  Skipping standings for {$comp->name} (synced ".Carbon::parse($lastTeamSync)->diffForHumans().')');

                continue;
            }

            $this->throttle($first);

            $data = $api->getStandings($comp->external_id);

            if (empty($data['standings'])) {
                $this->warn("  No standings for {$comp->name}");

                continue;
            }

            // Ensure teams from standings exist in DB, and persist the group
            // (grupos/fases/equipos, 22/10/2026) — antes esta respuesta solo
            // se usaba para crear equipos faltantes; la tabla de posiciones
            // por grupo (ya disponible en $group['table']) se descartaba.
            $now = now();
            foreach ($data['standings'] as $group) {
                $teamsByExternalId = [];

                foreach ($group['table'] as $row) {
                    $teamExternalId = $row['team']['id'] ?? null;
                    if (! $teamExternalId) {
                        continue;
                    }

                    $team = Team::where('external_id', $teamExternalId)->first();
                    if (! $team) {
                        $team = Team::create([
                            'name' => $row['team']['name'] ?? $row['team']['shortName'] ?? "Team {$teamExternalId}",
                            'country' => $comp->country,
                            'country_code' => $comp->country_code,
                            'league' => $comp->name,
                            'logo_url' => $row['team']['crest'] ?? null,
                            'flag_url' => null,
                            'active' => true,
                            'external_id' => $teamExternalId,
                            'last_synced_at' => $now,
                        ]);
                    }

                    $teamsByExternalId[$teamExternalId] = $team;
                }

                $this->persistGroupStandings($comp, $group, $teamsByExternalId);
            }

            $this->info("  Standings: {$comp->name} OK");
        }
    }

    /**
     * Persiste un grupo de la fase de grupos (y su tabla de posiciones) desde
     * la respuesta de /standings de football-data.org. Solo TOTAL (no
     * HOME/AWAY) y solo si el stage ya existe — si todavía no se
     * sincronizaron partidos de esa fase, no hay stage_id donde colgar el
     * grupo, así que se salta con un warning en vez de crearlo a ciegas.
     *
     * @param array<string, Team> $teamsByExternalId
     */
    private function persistGroupStandings(Tournament $comp, array $group, array $teamsByExternalId): void
    {
        $groupName = $group['group'] ?? null;
        if (! $groupName || ($group['type'] ?? 'TOTAL') !== 'TOTAL') {
            return;
        }

        $stageName = $group['stage'] ?? 'GROUP_STAGE';
        $stage = TournamentStage::where('tournament_id', $comp->tournament_id)
            ->where('name', $stageName)
            ->first();

        if (! $stage) {
            $this->warn("    Sin stage '{$stageName}' todavía para {$comp->name} — grupo '{$groupName}' no se guarda hasta que corra el sync de partidos.");

            return;
        }

        $tournamentGroup = TournamentGroup::updateOrCreate(
            ['stage_id' => $stage->stage_id, 'name' => $groupName],
            ['status' => 'approved', 'group_order' => $this->groupOrder($groupName)]
        );

        foreach ($group['table'] as $row) {
            $teamExternalId = $row['team']['id'] ?? null;
            $team = $teamExternalId ? ($teamsByExternalId[$teamExternalId] ?? null) : null;
            if (! $team) {
                continue;
            }

            TournamentGroupTeam::updateOrCreate(
                ['group_id' => $tournamentGroup->group_id, 'team_id' => $team->team_id],
                [
                    'position_final' => $row['position'] ?? null,
                    'points' => $row['points'] ?? 0,
                    'goal_diff' => $row['goalDifference'] ?? 0,
                    'goals_for' => $row['goalsFor'] ?? 0,
                    'goals_against' => $row['goalsAgainst'] ?? 0,
                    'matches_played' => $row['playedGames'] ?? 0,
                    'wins' => $row['won'] ?? 0,
                    'draws' => $row['draw'] ?? 0,
                    'losses' => $row['lost'] ?? 0,
                ]
            );
        }
    }

    /**
     * "GROUP_A" -> 1, "GROUP_B" -> 2, etc. Si no matchea ese patrón (torneos
     * sin letra, poco común), se deja 1 — group_order es solo para ordenar
     * la UI, no identifica al grupo (eso lo hace 'name').
     */
    private function groupOrder(string $groupName): int
    {
        if (preg_match('/([A-Z])$/', $groupName, $m)) {
            return ord($m[1]) - ord('A') + 1;
        }

        return 1;
    }

    /**
     * Resolve which tournaments to process: specific one, or all free-tier ones in DB.
     *
     * @return \Illuminate\Support\Collection<int, Tournament>
     */
    private function targetCompetitions(?int $competitionId)
    {
        if ($competitionId) {
            $comp = Tournament::where('external_id', $competitionId)->first();

            if (! $comp) {
                $this->error("Competition with external_id {$competitionId} not found. Run --type=competitions first.");

                return collect();
            }

            return collect([$comp]);
        }

        return Tournament::whereIn('external_id', array_keys(self::FREE_COMPETITIONS))->get();
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

    /**
     * Sleep between API calls to respect the free-tier 10 req/min limit.
     * Skips the sleep for the first call in a sequence.
     */
    private function throttle(bool &$first): void
    {
        if ($first) {
            $first = false;

            return;
        }

        sleep(self::THROTTLE_SECONDS);
    }
}
