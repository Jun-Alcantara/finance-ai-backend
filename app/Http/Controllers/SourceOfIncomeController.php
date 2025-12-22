<?php

namespace App\Http\Controllers;

use App\Models\SourceOfIncome;
use Illuminate\Http\Request;

class SourceOfIncomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $sourceOfIncomes = SourceOfIncome::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $sourceOfIncomes]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $sourceOfIncome = SourceOfIncome::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
        ]);

        return response()->json(['data' => $sourceOfIncome], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SourceOfIncome $sourceOfIncome)
    {
        if ($sourceOfIncome->user_id !== request()->user()->id) {
            abort(403);
        }

        return response()->json(['data' => $sourceOfIncome]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SourceOfIncome $sourceOfIncome)
    {
        if ($sourceOfIncome->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $sourceOfIncome->update($validated);

        return response()->json(['data' => $sourceOfIncome]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SourceOfIncome $sourceOfIncome)
    {
        if ($sourceOfIncome->user_id !== request()->user()->id) {
            abort(403);
        }

        // Check if there are any incomes associated with this source
        if ($sourceOfIncome->incomes()->exists()) {
            return response()->json(['message' => 'Cannot delete source of income because it is used by existing incomes.'], 422);
        }

        $sourceOfIncome->delete();

        return response()->json(null, 204);
    }
}
