<?php

namespace App\Http\Controllers;

use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SyncController extends Controller
{
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

        if ($source === 'all') {
            $exitCode = Artisan::call('sync:all', array_filter([
                '--type' => $type === 'matches' ? 'fixtures' : $type,
                '--season' => (string) $season,
                '--force' => $force ?: null,
            ]));

            return response()->json([
                'success' => $exitCode === 0,
                'message' => 'Full sync (football-data.org + ESPN) completed.',
                'type' => $type,
                'season' => $season,
                'source' => $source,
                'log' => Artisan::output(),
            ]);
        }

        if ($source === 'worldcup') {
            $exitCode = Artisan::call('worldcup:sync', [
                '--type' => $type === 'fixtures' ? 'matches' : $type,
                '--season' => $season,
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => "World Cup {$season} sync completed.",
                'type' => $type,
                'season' => $season,
                'source' => $source,
                'log' => Artisan::output(),
            ]);
        }

        if ($source === 'football') {
            $exitCode = Artisan::call('football:sync', array_filter([
                '--type' => $type === 'fixtures' ? 'matches' : $type,
                '--force' => $force ?: null,
            ]));

            return response()->json([
                'success' => $exitCode === 0,
                'message' => 'football-data.org sync completed.',
                'type' => $type,
                'season' => $season,
                'source' => $source,
                'log' => Artisan::output(),
            ]);
        }

        $args = array_filter([
            '--type' => $type,
            '--season' => (string) $season,
            '--force' => $force ?: null,
        ]);

        if ($league) {
            $args['--league'] = $league;
        }

        $exitCode = Artisan::call('espn:sync', $args);

        return response()->json([
            'success' => $exitCode === 0,
            'message' => $league
                ? "{$league} sync completed."
                : "All leagues sync completed.",
            'type' => $type,
            'season' => $season,
            'source' => $source,
            'log' => Artisan::output(),
        ]);
    }
}
