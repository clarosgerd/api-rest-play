<?php

namespace App\Http\Controllers;

use App\Filters\GroupPredictionsFilter;
use App\Http\Requests\StoreGroupPredictionsRequest;
use App\Http\Requests\UpdateGroupPredictionsRequest;
use App\Http\Resources\GroupPredictionsCollection;
use App\Models\GroupPredictions;
use Illuminate\Http\Request;

class GroupPredictionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new GroupPredictionsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new GroupPredictionsCollection(GroupPredictions::paginate());
        } else {
            $grouppredictions = GroupPredictions::where($queryItems)->paginate();
        }

        return new GroupPredictionsCollection($grouppredictions->appends($request->query()));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupPredictionsRequest $request)
    {
        $grouppredictions = GroupPredictions::create($request->validated());

        return response()->json([
            'message' => 'Group prediction created successfully',
            'groupprediction' => $grouppredictions,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = GroupPredictions::find($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GroupPredictions $groupPredictions)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupPredictionsRequest $request, string $id)
    {
        $grouppredictions = GroupPredictions::findOrFail($id);
        $grouppredictions->update($request->validated());

        return response()->json([
            'message' => 'Group prediction updated successfully',
            'groupprediction' => $grouppredictions,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $grouppredictions = GroupPredictions::find($id);

        $grouppredictions->delete();

        return response()->json([
            'message' => 'GroupPredictions deleted successfully',
        ]);
    }
}
