<?php

namespace App\Http\Controllers;

use App\Filters\TeamFilter;
use App\Http\Requests\BulkStoreTeamRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamCollection;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $filter = new TeamFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new TeamCollection(Team::paginate());
        } else {
            $teams = Team::where($queryItems)->paginate();
        }

        return new TeamCollection($teams->appends($request->query()));
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
    /*
    public function store( Request $request ) {
        $team = new Team;
        $team->name = $request->input( 'name' );
        $team->country = $request->input( 'country' );
        $team->league = $request->input( 'league' );
        $team->logo_url = $request->input( 'logo_url' );
        $team->flag_url = $request->input( 'flag_url' );
        $team->country_code = $request->input( 'country_code' );
        $team->save();
        return response()->json( [
            'message' => 'Team created successfully',
            'task' => $team
        ] );
    }
*/

    public function store(StoreTeamRequest $request)
    {
        $data = $request->all();
        $data['created_by'] = auth()->id();

        return new TeamResource(Team::create($data));
    }
    /**
     * Display the specified resource.
     */

    /* public function show(string $id)
    {
        $data = Team::find($id);
        return response()->json(['data' => $data]);
    }*/

    public function show(Team $team)
    {
        //   dd($tournament->query());
        return new TeamResource($team);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id) {}

    /**
     * Update the specified resource in storage.
     */

    /*  public function update(Request $request, string $id)
    {
        $team = Team::find($id);
        $team->name = $request->input('name');
        $team->country = $request->input('country');
        $team->league = $request->input('league');
        $team->logo_url = $request->input('logo_url');
        $team->flag_url = $request->input('flag_url');
        $team->country_code = $request->input('country_code');
        $team->save();
        return response()->json([
            'message' => 'team updated successfully',
            'task' => $team
        ]);
    }*/
    public function update(UpdateTeamRequest $request, Team $team)
    {
        $team->update($request->all());

        return new TeamResource($team->fresh());
    }

    public function bulkStore(BulkStoreTeamRequest $request)
    {
        $userId = auth()->id();
        $bulk = collect($request->all())->map(function ($arr) use ($userId) {
            $arr['created_by'] = $userId;

            return Arr::except($arr, ['team_id']);
        });
        Team::insert($bulk->toArray());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $team = Team::find($id);
        $team->delete();

        return response()->json([
            'message' => 'team deleted successfully',
        ]);
    }

    public function toggleActive(Team $team)
    {
        $team->active = $team->active ? 0 : 1;
        $team->save();

        return new TeamResource($team->fresh());
    }
}
