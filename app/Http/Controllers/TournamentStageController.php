<?php

namespace App\Http\Controllers;

use App\Filters\TournamentStagesFilter;
use App\Http\Requests\BulkStoreTournamentStageRequest;
use App\Http\Requests\StoreTournamentStageRequest;
use App\Http\Requests\UpdateTournamentStageRequest;
use App\Http\Resources\TournamentStageCollection;
use App\Http\Resources\TournamentStageResource;
use App\Models\Tournament;
use App\Models\TournamentStage;
use Illuminate\Http\Request;

class TournamentStageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new TournamentStagesFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new TournamentStageCollection(TournamentStage::paginate());
        } else {
            $tournamentstages = TournamentStage::where($queryItems)->paginate();
        }

        return new TournamentStageCollection($tournamentstages->appends($request->query()));
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
    public function store(Request $request)
    {
        $tournamentstages = new TournamentStage;
        $tournamentstages->tournament_id = $request->input('tournament_id');
        $tournamentstages->name = $request->input('name');
        $tournamentstages->stage_order = $request->input('stage_order');
        $tournamentstages->save();
        return response()->json([
            'message' => 'Team created successfully',
            'task' => $tournamentstages
        ]);
    }
*/
    public function store(StoreTournamentStageRequest $request)
    {
        //  dd($request);
        return new TournamentStageResource(TournamentStage::create($request->all()));
    }

    /**
     * Display the specified resource.
     */
    public function show(TournamentStage $stage)
    {
        // dd($stage);
        // new TournamentResource ($stage);
        return new TournamentStageResource($stage);
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
    /*
    public function update(Request $request, string $id)
    {
        $tournamentstages = TournamentStage::find($id);
        $tournamentstages->tournament_id = $request->input('tournament_id');
        $tournamentstages->name = $request->input('name');
        $tournamentstages->stage_order = $request->input('stage_order');
        $tournamentstages->save();
        return response()->json([
            'message' => 'Team created successfully',
            'task' => $tournamentstages
        ]);
    }
class UpdateTournamentStageRequest extends FormRequest
*/
    public function update(UpdateTournamentStageRequest $request, TournamentStage $stage)
    {

        $stage->update($request->all());
    }

    public function bulkStore(BulkStoreTournamentStageRequest $request)
    {
        // dd($request->all());
        $bulk = collect($request->all());
        TournamentStage::insert($bulk->toArray());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tournamentstages = TournamentStage::find($id);
        $tournamentstages->delete();

        return response()->json([
            'message' => 'tournament deleted successfully',
        ]);
    }
}
