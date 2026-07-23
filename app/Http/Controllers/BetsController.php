<?php

namespace App\Http\Controllers;

use App\Filters\BetsFilter;
use App\Http\Requests\StoreBetsRequest;
use App\Http\Requests\UpdateBetsRequest;
use App\Http\Resources\BetsCollection;
use App\Models\Bets;
use Illuminate\Http\Request;

class BetsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $filter = new BetsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new BetsCollection(Bets::paginate());
        } else {
            $bets = Bets::where($queryItems)->paginate();
        }

        return new BetsCollection($bets->appends($request->query()));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBetsRequest $request)
    {
        $data = $request->validated();
        $data['placed_at'] = $data['placed_at'] ?? now();
        $bets = Bets::create($data);

        return response()->json([
            'message' => 'Bet created successfully',
            'bet' => $bets,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Bets::find($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bets $bets)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBetsRequest $request, string $id)
    {
        $bets = Bets::findOrFail($id);
        $bets->update($request->validated());

        return response()->json([
            'message' => 'Bet updated successfully',
            'bet' => $bets,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $bets = Bets::find($id);

        $bets->delete();

        return response()->json([
            'message' => 'bets deleted successfully',
        ]);
    }
}
