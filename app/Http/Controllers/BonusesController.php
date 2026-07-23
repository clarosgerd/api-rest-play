<?php

namespace App\Http\Controllers;

use App\Filters\BonusesFilter;
use App\Http\Requests\StoreBonusesRequest;
use App\Http\Requests\UpdateBonusesRequest;
use App\Http\Resources\BonusesCollection;
use App\Models\Bonuses;
use Illuminate\Http\Request;

class BonusesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new BonusesFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new BonusesCollection(Bonuses::paginate());
        } else {
            $bonuses = Bonuses::where($queryItems)->paginate();
        }

        return new BonusesCollection($bonuses->appends($request->query()));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBonusesRequest $request)
    {
        $bonuses = Bonuses::create($request->validated());

        return response()->json([
            'message' => 'Bonus created successfully',
            'bonus' => $bonuses,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Bonuses::find($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bonuses $bonuses)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBonusesRequest $request, Bonuses $bonuses)
    {
        $bonuses->update($request->validated());

        return response()->json([
            'message' => 'Bonus updated successfully',
            'bonus' => $bonuses,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $bonuses = Bonuses::find($id);

        $bonuses->delete();

        return response()->json([
            'message' => 'Bonus deleted successfully',
        ]);
    }
}
