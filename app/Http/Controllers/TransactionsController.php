<?php

namespace App\Http\Controllers;

use App\Filters\TransactionsFilter;
use App\Http\Requests\StoreTransactionsRequest;
use App\Http\Requests\UpdateTransactionsRequest;
use App\Http\Resources\TransactionsCollection;
use App\Models\Transactions;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = new TransactionsFilter;
        $queryItems = $filter->transform($request);
        if (count($queryItems) == 0) {
            return new TransactionsCollection(Transactions::paginate());
        } else {
            $transactions = Transactions::where($queryItems)->paginate();
        }

        return new TransactionsCollection($transactions->appends($request->query()));
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
    public function store(StoreTransactionsRequest $request)
    {
        $transactions = Transactions::create($request->validated());

        return response()->json([
            'message' => 'Transaction created successfully',
            'transaction' => $transactions,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Transactions::find($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transactions $transactions)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransactionsRequest $request, string $id)
    {
        $transactions = Transactions::findOrFail($id);
        $transactions->update($request->validated());

        return response()->json([
            'message' => 'Transaction updated successfully',
            'transaction' => $transactions,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $transactions = Transactions::find($id);

        $transactions->delete();

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }
}
