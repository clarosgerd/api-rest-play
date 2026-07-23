<?php

namespace App\Http\Controllers;

use App\Filters\GroupBetsFilter;
use App\Http\Requests\StoreGroupBetsRequest;
use App\Http\Requests\UpdateGroupBetsRequest;
use App\Http\Resources\GroupBetsCollection;
use App\Models\GroupBets;
use Illuminate\Http\Request;

class GroupBetsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new GroupBetsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new GroupBetsCollection(GroupBets::paginate());
        } else {
            $groupbets = GroupBets::where($queryItems)->paginate();
        }

        return new GroupBetsCollection($groupbets->appends($request->query()));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupBetsRequest $request)
    {
        $data = $request->validated();
        $data['created_at'] = $data['created_at'] ?? now();
        $groupbets = GroupBets::create($data);

        return response()->json([
            'message' => 'Group bet created successfully',
            'groupbet' => $groupbets,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = GroupBets::find($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GroupBets $groupBets)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupBetsRequest $request, string $id)
    {
        $groupbets = GroupBets::findOrFail($id);
        $groupbets->update($request->validated());

        return response()->json([
            'message' => 'Group bet updated successfully',
            'groupbet' => $groupbets,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $groupbets = GroupBets::find($id);

        $groupbets->delete();

        return response()->json([
            'message' => 'bets deleted successfully',
        ]);
    }
}
