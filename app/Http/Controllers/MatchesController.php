<?php

namespace App\Http\Controllers;

use App\Filters\MatchesFilter;
use App\Http\Requests\BulkStoreMatchesRequest;
use App\Http\Requests\StoreMatchesRequest;
use App\Http\Requests\UpdateMatchesRequest;
use App\Http\Resources\MatchesCollection;
use App\Http\Resources\MatchesResource;
use App\Models\Matches;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MatchesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $filter = new MatchesFilter;
        $queryItems = $filter->transform($request);
        $matches = Matches::where($queryItems);

        $includeStage = $request->query('includeStage');
        $includeHome = $request->query('includeHome');
        $includeAway = $request->query('includeAway');

        if ($includeStage || $includeHome || $includeAway) {
            $relations = [];
            if ($includeStage) $relations[] = 'stage';
            if ($includeHome) $relations[] = 'home';
            if ($includeAway) $relations[] = 'away';
            $matches = $matches->with($relations);
        }
        return new MatchesCollection($matches->paginate()->appends($request->query()));
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
        $matches = new Matches;
        $matches->away_team_id = $request->input('away_team_id');
        $matches->tournament_id = $request->input('tournament_id');
        $matches->stage_id = $request->input('stage_id');
        $matches->match_date = $request->input('match_date');
        $matches->league = $request->input('league');
        $matches->season = $request->input('season');
        $matches->status = $request->input('status');
        $matches->home_score = $request->input('home_score');
        $matches->away_score = $request->input('away_score');
        $matches->is_final = $request->input('is_final');
        $matches->save();
        return response()->json([
            'message' => 'Team created successfully',
            'task' => $matches
        ]);
    }*/
    public function store(StoreMatchesRequest $request)
    {
        return new MatchesResource(Matches::create($request->all()));
    }

    /**
     * Display the specified resource.
     */

    /* public function show(string $id)
    {
        $data = Matches::find($id);
        return response()->json(['data' => $data]);
    }*/
    public function show(Matches $match)
    {
        $includeStage = request()->query('includeStage');
        $includeHome = request()->query('includeHome');
        $includeAway = request()->query('includeAway');

        if ($includeStage || $includeHome || $includeAway) {
            $relations = [];
            if ($includeStage) $relations[] = 'stage';
            if ($includeHome) $relations[] = 'home';
            if ($includeAway) $relations[] = 'away';
            return new MatchesResource($match->loadMissing($relations));
        }

        return new MatchesResource($match);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Matches $matches)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    /*
    public function update(Request $request, String $id)
    {
        //
        $matches = Matches::find($id);

        $matches->away_team_id = $request->input('away_team_id');
        $matches->tournament_id = $request->input('tournament_id');
        $matches->stage_id = $request->input('stage_id');
        $matches->match_date = $request->input('match_date');
        $matches->league = $request->input('league');
        $matches->season = $request->input('season');
        $matches->status = $request->input('status');
        $matches->home_score = $request->input('home_score');
        $matches->away_score = $request->input('away_score');
        $matches->is_final = $request->input('is_final');
        $matches->save();
        return response()->json([
            'message' => 'Matches created successfully',
            'matches' => $matches

        ]);
    }*/

    public function update(UpdateMatchesRequest $request, Matches $match)
    {

        // dd($request);
        $match->update($request->all());
    }

    public function bulkStore(BulkStoreMatchesRequest $request)
    {
        // dd($request->all());
        $bulk = collect($request->all())->map(function ($arr, $key) {
            return Arr::except($arr, ['match_id']);
        });
        // dump($bulk->toArray());
        Matches::insert($bulk->toArray());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $matches = Matches::find($id);

        $matches->delete();

        return response()->json([
            'message' => 'Match deleted successfully',
        ]);
    }
}
