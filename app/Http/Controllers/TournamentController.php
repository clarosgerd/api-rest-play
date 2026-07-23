<?php

namespace App\Http\Controllers;

use App\Filters\TournamentFilter;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Http\Resources\TournamentCollection;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\Request;

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
        $tournament->actived = $tournament->actived ? 0 : 1;
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
}
