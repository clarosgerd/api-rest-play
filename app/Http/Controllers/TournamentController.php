<?php

namespace App\Http\Controllers;

use App\Filters\TournamentFilter;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Http\Resources\TournamentCollection;
use App\Http\Resources\TournamentResource;
use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TournamentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //

        $filter = new TournamentFilter;
        $queryItems = $filter->transform($request);
        $tournament = Tournament::where($queryItems);
        $includeTournamentStages = $request->query('includeStage');
        if ($includeTournamentStages) {
            $tournament = $tournament->with('tournamentstages');
        }

        return new TournamentCollection($tournament->paginate()->appends($request->query()));
    }

    /**
     * Torneos públicos que el usuario puede elegir hoy (16/10/2026, pedido
     * del usuario) — camino 1 del onboarding ("elegir torneo configurado").
     * Temporada = `active` (ya existente, sin campo nuevo). Plan = filtra
     * por `league_category` (poblado por los comandos de sync) contra
     * `getTierLimits()['external_leagues']`. Los torneos demo/privados
     * (`league_category` null) quedan siempre visibles para cualquiera.
     */
    public function availableForMe(Request $request)
    {
        $user = $request->user();
        $allowedLeagues = $user->getTierLimits()['external_leagues'];
        $unlocksAll = in_array('all', $allowedLeagues, true);
        // 'conmebol'/'sudamericana'/'south_america' en getTierLimits() son
        // sinónimos del único valor real que persisten los comandos de sync.
        $unlocksConmebol = $unlocksAll || array_intersect(
            ['conmebol', 'sudamericana', 'south_america'],
            $allowedLeagues
        ) !== [];

        // Publicar un torneo sincronizado (20/10/2026, pedido real del
        // usuario) — reusa `status`/`publish()` que ya existían sin uso acá:
        // los comandos de sync dejan el torneo en 'draft' (default de la
        // migración), así que recién aparece "disponible" cuando un admin lo
        // publica (`status` pasa a 'published'/'live').
        $query = Tournament::where('active', true)
            ->whereIn('status', ['published', 'live']);

        // Bug real encontrado al revisar esto: el torneo privado de un
        // usuario cualquiera aparecía en la lista de TODOS los demás,
        // contradiciendo "privado = solo por invitación". Los demo/seed
        // (created_by null) siguen siendo globales a propósito; solo se
        // excluye el privado AJENO de otro usuario.
        $query->where(function ($q) use ($user) {
            $q->whereNull('created_by')->orWhere('created_by', $user->user_id);
        });

        if (! $unlocksAll) {
            $query->where(function ($q) use ($unlocksConmebol) {
                $q->whereNull('league_category');
                if ($unlocksConmebol) {
                    $q->orWhere('league_category', 'conmebol');
                }
            });
        }

        return new TournamentCollection($query->paginate()->appends($request->query()));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    /*  public function store(Request $request)
    {
        $tournament = new Tournament;
        $tournament->name = $request->input('name');
        $tournament->description = $request->input('season');
        $tournament->league = $request->input('country');
        $tournament->logo_url = $request->input('logo_url');
        $tournament->flag_url = $request->input('flag_url');
        $tournament->country_code = $request->input('country_code');
        $tournament->country_code = $request->input('start_date');
        $tournament->country_code = $request->input('end_date');
        $tournament->country_code = $request->input('active');
        $tournament->save();
        return response()->json([
            'message' => 'Team created successfully',
            'task' => $tournament
        ]);
    }
*/

    public function store(StoreTournamentRequest $request)
    {
        $data = $request->all();
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'draft';

        return new TournamentResource(Tournament::create($data));
    }

    /**
     * Display the specified resource.
     */
    public function show(Tournament $tournament)
    {
        //   dd($tournament->query());
        $includeStage = request()->query('includeStage');
        if ($includeStage) {
            return new TournamentResource($tournament->loadMissing('tournamentstages'));
        }

        return new TournamentResource($tournament);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */

    /*  public function update(Request $request, string $id)
    {
        $tournament = Tournament::find($id);
        $tournament->name = $request->input('name');
        $tournament->season = $request->input('season');
        $tournament->country = $request->input('country');
        $tournament->logo_url = $request->input('logo_url');
        $tournament->flag_url = $request->input('flag_url');
        $tournament->country_code = $request->input('country_code');
        $tournament->start_date = $request->input('start_date');
        $tournament->end_date = $request->input('end_date');
        $tournament->active = $request->input('active');
        $tournament->save();
        return response()->json([
            'message' => 'Team created successfully',
            'task' => $tournament
        ]);
    }*/

    public function update(UpdateTournamentRequest $request, Tournament $tournament)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($tournament->created_by && $tournament->created_by !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para editar este torneo.'], 403);
        }

        if ($tournament->created_by && $tournament->created_by === $userId && ! $tournament->isDraft()) {
            $allowed = ['start_date', 'end_date'];
            $data = array_intersect_key($request->all(), array_flip($allowed));
            $tournament->update($data);

            return new TournamentResource($tournament->fresh());
        }

        $tournament->update($request->all());

        return new TournamentResource($tournament->fresh());
    }

    public function destroy($id)
    {
        $tournament = Tournament::find($id);
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($tournament->created_by && $tournament->created_by !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para eliminar este torneo.'], 403);
        }

        if ($tournament->created_by && ! $tournament->isDraft() && ! $isAdmin) {
            return response()->json(['message' => 'Solo se pueden eliminar torneos en borrador.'], 403);
        }

        $tournament->delete();

        return response()->json([
            'message' => 'tournament deleted successfully',
        ]);
    }

    public function toggleActive(Tournament $tournament)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($tournament->created_by && $tournament->created_by !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para modificar este torneo.'], 403);
        }

        $tournament->active = $tournament->active ? 0 : 1;
        $tournament->save();

        return new TournamentResource($tournament->fresh());
    }

    public function publish(Tournament $tournament)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($tournament->created_by !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para publicar este torneo.'], 403);
        }

        if (! $tournament->isDraft()) {
            return response()->json(['message' => 'Solo se pueden publicar torneos en borrador.'], 422);
        }

        $tournament->update(['status' => 'published', 'active' => 1]);

        return new TournamentResource($tournament->fresh());
    }

    public function goLive(Tournament $tournament)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($tournament->created_by !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para activar este torneo.'], 403);
        }

        if (! $tournament->isPublished()) {
            return response()->json(['message' => 'Solo se pueden activar torneos publicados.'], 422);
        }

        $tournament->update(['status' => 'live']);

        return new TournamentResource($tournament->fresh());
    }

    public function close(Tournament $tournament)
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($tournament->created_by !== $userId && ! $isAdmin) {
            return response()->json(['message' => 'No tienes permiso para cerrar este torneo.'], 403);
        }

        if (! $tournament->isLive()) {
            return response()->json(['message' => 'Solo se pueden cerrar torneos activos.'], 422);
        }

        $tournament->update(['status' => 'closed']);

        return new TournamentResource($tournament->fresh());
    }

    public function assignManager(Request $request, Tournament $tournament)
    {
        $isAdmin = auth()->user()->role === 'admin';

        if (! $isAdmin) {
            return response()->json(['message' => 'Solo administradores pueden asignar managers.'], 403);
        }

        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
        ]);

        $user = User::findOrFail($request->user_id);

        $group = Groups::where('tournament_id', $tournament->tournament_id)->first();

        if (! $group) {
            $group = new Groups;
            $group->tournament_id = $tournament->tournament_id;
            $group->name = $tournament->name . ' - Predictores';
            $group->description = 'Grupo de predicciones para ' . $tournament->name;
            $group->creator_id = auth()->id();
            $group->is_public = 0;
            $group->invitation_code = Str::upper(Str::random(7));
            $group->max_members = 50;
            $group->active = 1;
            $group->bet_amount = 0;
            $group->prize_1st_pct = 60;
            $group->prize_2nd_pct = 30;
            $group->prize_3rd_pct = 10;
            $group->pool_active = 0;
            $group->exact_score_points = 10;
            $group->match_result_points = 5;
            $group->correct_goal_points = 3;
            $group->unique_exact_points = 15;
            $group->multiplier_dieciseisavos = 1;
            $group->multiplier_octavos = 1;
            $group->multiplier_cuartos = 1;
            $group->multiplier_semifinal = 2;
            $group->multiplier_final = 3;
            $group->created_at = Carbon::now();
            $group->save();
        }

        $existing = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $user->user_id)
            ->first();

        if ($existing) {
            $existing->is_admin = 1;
            $existing->status = 'accepted';
            $existing->save();
        } else {
            $member = new GroupMembers;
            $member->group_id = $group->group_id;
            $member->user_id = $user->user_id;
            $member->joined_at = Carbon::now();
            $member->is_admin = 1;
            $member->status = 'accepted';
            $member->save();
        }

        return response()->json([
            'message' => 'Manager asignado correctamente.',
            'group_id' => $group->group_id,
            'user' => [
                'user_id' => $user->user_id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
            ],
        ]);
    }

    public function removeManager(Request $request, Tournament $tournament)
    {
        $isAdmin = auth()->user()->role === 'admin';

        if (! $isAdmin) {
            return response()->json(['message' => 'Solo administradores pueden remover managers.'], 403);
        }

        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
        ]);

        $group = Groups::where('tournament_id', $tournament->tournament_id)->first();

        if (! $group) {
            return response()->json(['message' => 'No existe grupo vinculado a este torneo.'], 404);
        }

        $member = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $request->user_id)
            ->first();

        if (! $member) {
            return response()->json(['message' => 'El usuario no es miembro del grupo.'], 404);
        }

        $member->is_admin = 0;
        $member->save();

        return response()->json(['message' => 'Manager removido correctamente.']);
    }

    public function managers(Tournament $tournament)
    {
        $group = Groups::where('tournament_id', $tournament->tournament_id)->first();

        if (! $group) {
            return response()->json(['managers' => []]);
        }

        $managers = GroupMembers::where('group_id', $group->group_id)
            ->where('is_admin', 1)
            ->where('status', 'accepted')
            ->with('user')
            ->get()
            ->map(function ($m) {
                return [
                    'member_id' => $m->member_id,
                    'user_id' => $m->user_id,
                    'name' => $m->user->first_name . ' ' . $m->user->last_name,
                    'email' => $m->user->email,
                ];
            });

        return response()->json(['managers' => $managers]);
    }
}
