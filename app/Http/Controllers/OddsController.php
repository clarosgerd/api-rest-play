<?php

namespace App\Http\Controllers;

use App\Filters\OddsFilter;
use App\Http\Requests\StoreOddsRequest;
use App\Http\Requests\UpdateOddsRequest;
use App\Http\Resources\OddsCollection;
use App\Models\Odds;
use Illuminate\Http\Request;

class OddsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new OddsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new OddsCollection(Odds::paginate());
        } else {
            $odds = Odds::where($queryItems)->paginate();
        }

        return new OddsCollection($odds->appends($request->query()));
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
    public function store(StoreOddsRequest $request)
    {
        $data = $request->validated();
        $data['last_update'] = $data['last_update'] ?? now();
        $odds = Odds::create($data);

        return response()->json([
            'message' => 'Odds created successfully',
            'odds' => $odds,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Odds::find($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOddsRequest $request, string $id)
    {
        $odds = Odds::findOrFail($id);
        $odds->update($request->validated());

        return response()->json([
            'message' => 'Odds updated successfully',
            'odds' => $odds,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $odds = Odds::find($id);

        $odds->delete();

        return response()->json([
            'message' => 'Odds deleted successfully',
        ]);
    }
}
