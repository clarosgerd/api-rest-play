<?php

namespace App\Http\Controllers;

use App\Jobs\RunSyncJob;
use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Player;
use App\Models\SyncRun;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SyncController extends Controller
{
    /**
     * Sync manual por el dueño de SU torneo público (16/10/2026, pedido del
     * usuario) — a diferencia de sync()/status() (admin-only), esto lo
     * puede disparar el dueño de un grupo ligado a este torneo, no solo un
     * admin global. Mismo patrón de autorización que
     * ManualMatchController::canManageResults().
     */
    public function syncTournament(Tournament $tournament): JsonResponse
    {
        if (! $this->canSyncTournament($tournament)) {
            return response()->json(['message' => 'No tenés permiso para sincronizar este torneo.'], 403);
        }

        if (! $tournament->external_id || ! $tournament->league_category) {
            return response()->json([
                'message' => 'Este torneo no tiene una fuente externa configurada — usá el ingreso manual de resultados.',
            ], 422);
        }

        $exitCode = match ($tournament->league_category) {
            'conmebol' => Artisan::call('conmebol:sync', [
                '--competition' => $tournament->external_id === 13 ? 'libertadores' : 'sudamericana',
            ]),
            'european' => Artisan::call('football:sync', [
                '--competition' => (string) $tournament->external_id,
                '--type' => 'matches',
            ]),
            default => 1,
        };

        return response()->json([
            'success' => $exitCode === 0,
            'message' => $exitCode === 0
                ? "Sincronización de \"{$tournament->name}\" completada."
                : 'La sincronización falló.',
            'log' => Artisan::output(),
        ]);
    }

    private function canSyncTournament(Tournament $tournament): bool
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($isAdmin || $tournament->created_by === $userId) {
            return true;
        }

        $groupIds = Groups::where('tournament_id', $tournament->tournament_id)->pluck('group_id');

        return GroupMembers::whereIn('group_id', $groupIds)
            ->where('user_id', $userId)
            ->where('is_admin', 1)
            ->where('status', 'accepted')
            ->exists();
    }

    public function status(): JsonResponse
    {
        $tournaments = Tournament::count();
        $teams = Team::count();
        $players = Player::count();
        $matches = Matches::count();

        $worldCup = Tournament::where('external_id', 1)->first();

        $leagues = Tournament::whereNotNull('external_id')
            ->where('external_id', '>=', 10001)
            ->where('external_id', '<=', 10010)
            ->get()
            ->map(fn ($t) => [
                'tournament_id' => $t->tournament_id,
                'name' => $t->name,
                'season' => $t->season,
                'country' => $t->country,
                'logo_url' => $t->logo_url,
                'last_synced_at' => $t->last_synced_at?->toISOString(),
                'matches' => Matches::where('tournament_id', $t->tournament_id)->count(),
                'teams' => Team::where('league', $t->name)->count(),
                'players' => Player::whereHas('team', fn ($q) => $q->where('league', $t->name))->count(),
            ]);

        // Also include football-data.org competitions (IDs 2000-2999) and CONMEBOL
        $conmebol = Tournament::whereIn('external_id', [11, 13])
            ->get()
            ->map(fn ($t) => [
                'tournament_id' => $t->tournament_id,
                'name' => $t->name,
                'season' => $t->season,
                'country' => $t->country,
                'logo_url' => $t->logo_url,
                'last_synced_at' => $t->last_synced_at?->toISOString(),
                'matches' => Matches::where('tournament_id', $t->tournament_id)->count(),
                'teams' => Team::where('league', $t->name)->count(),
            ]);

        $footballData = Tournament::whereBetween('external_id', [2000, 2999])
            ->get()
            ->map(fn ($t) => [
                'tournament_id' => $t->tournament_id,
                'name' => $t->name,
                'season' => $t->season,
                'country' => $t->country,
                'logo_url' => $t->logo_url,
                'last_synced_at' => $t->last_synced_at?->toISOString(),
                'matches' => Matches::where('tournament_id', $t->tournament_id)->count(),
                'teams' => Team::where('league', $t->name)->count(),
            ]);

        return response()->json([
            'counts' => [
                'tournaments' => $tournaments,
                'teams' => $teams,
                'players' => $players,
                'matches' => $matches,
            ],
            'world_cup' => $worldCup ? [
                'tournament_id' => $worldCup->tournament_id,
                'name' => $worldCup->name,
                'season' => $worldCup->season,
                'last_synced_at' => $worldCup->last_synced_at?->toISOString(),
            ] : null,
            'leagues' => $leagues,
            'conmebol' => $conmebol,
            'football_data' => $footballData,
        ]);
    }

    /**
     * Encola el sync en vez de corerlo síncrono (17/10/2026) — con las 13
     * competiciones del free tier + el sleep(7) entre llamadas para no
     * pasar el rate-limit, esto tarda 3-5 min: muy por encima del
     * max_execution_time=60 de la request HTTP. El job real corre vía
     * RunSyncJob, el frontend pollea el estado con runStatus().
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:all,teams,fixtures,players,standings,matches',
            'league' => 'nullable|string|in:eng.1,esp.1,ger.1,ita.1,fra.1,mex.1,usa.1,bra.1,arg.1,ned.1',
            'season' => 'nullable|integer|min:2020|max:2030',
            'source' => 'nullable|in:espn,worldcup,football,all',
            'force' => 'nullable|boolean',
        ]);

        $type = $validated['type'];
        $league = $validated['league'] ?? null;
        $season = $validated['season'] ?? (int) date('Y');
        $source = $validated['source'] ?? 'espn';
        $force = (bool) ($validated['force'] ?? false);

        [$command, $args] = match ($source) {
            'all' => ['sync:all', array_filter([
                '--type' => $type === 'matches' ? 'fixtures' : $type,
                '--season' => (string) $season,
                '--force' => $force ?: null,
            ])],
            'worldcup' => ['worldcup:sync', array_filter([
                '--type' => $type === 'fixtures' ? 'matches' : $type,
                '--season' => $season,
            ])],
            'football' => ['football:sync', array_filter([
                '--type' => $type === 'fixtures' ? 'matches' : $type,
                '--force' => $force ?: null,
            ])],
            default => ['espn:sync', array_filter([
                '--type' => $type,
                '--season' => (string) $season,
                '--force' => $force ?: null,
                '--league' => $league,
            ])],
        };

        $run = SyncRun::create([
            'source' => $source,
            'command' => $command,
            'arguments' => $args,
            'status' => 'queued',
        ]);

        RunSyncJob::dispatch($run->sync_run_id);

        return response()->json([
            'run_id' => $run->sync_run_id,
            'status' => $run->status,
            'type' => $type,
            'season' => $season,
            'source' => $source,
        ], 202);
    }

    /**
     * Polling del resultado de un sync encolado (ver sync()). Mismo shape
     * (success/message/log) que ya devolvía la llamada síncrona, para no
     * tener que rediseñar el bloque de resultado del panel.
     */
    public function runStatus(SyncRun $syncRun): JsonResponse
    {
        $message = match (true) {
            $syncRun->status === 'failed' => 'La sincronización falló.',
            ! $syncRun->isFinished() => null,
            $syncRun->source === 'all' => 'Full sync (football-data.org + ESPN) completed.',
            $syncRun->source === 'worldcup' => 'World Cup '.($syncRun->arguments['--season'] ?? date('Y')).' sync completed.',
            $syncRun->source === 'football' => 'football-data.org sync completed.',
            default => ($syncRun->arguments['--league'] ?? null)
                ? "{$syncRun->arguments['--league']} sync completed."
                : 'All leagues sync completed.',
        };

        return response()->json([
            'run_id' => $syncRun->sync_run_id,
            'status' => $syncRun->status,
            'success' => $syncRun->status === 'completed',
            'message' => $message,
            'log' => $syncRun->log,
            'error' => $syncRun->error,
        ]);
    }
}
