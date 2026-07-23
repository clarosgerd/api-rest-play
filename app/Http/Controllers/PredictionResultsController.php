<?php

namespace App\Http\Controllers;

use App\Filters\PredictionResultsFilter;
use App\Http\Requests\StorePredictionResultsRequest;
use App\Http\Requests\UpdatePredictionResultsRequest;
use App\Http\Resources\PredictionResultsCollection;
use App\Models\PredictionResults;
use Illuminate\Http\Request;

class PredictionResultsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new PredictionResultsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new PredictionResultsCollection(PredictionResults::paginate());
        } else {
            $predictionresults = PredictionResults::where($queryItems)->paginate();
        }

        return new PredictionResultsCollection($predictionresults->appends($request->query()));
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
    public function store(StorePredictionResultsRequest $request)
    {
        $predictionresults = PredictionResults::create($request->validated());

        return response()->json([
            'message' => 'Prediction result created successfully',
            'predictionresult' => $predictionresults,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = PredictionResults::find($id);

        return response()->json(['data' => $data]);
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
    public function update(UpdatePredictionResultsRequest $request, string $id)
    {
        $predictionresults = PredictionResults::findOrFail($id);
        $predictionresults->update($request->validated());

        return response()->json([
            'message' => 'Prediction result updated successfully',
            'predictionresult' => $predictionresults,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $predictionresults = PredictionResults::find($id);

        $predictionresults->delete();

        return response()->json([
            'message' => 'Prediction result deleted successfully',
        ]);
    }
}
