<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashDrawerEntry;
use Illuminate\Http\Request;

class CashDrawerEntryController extends Controller
{
    /**
     * Record a cash drawer entry (starting cash, cash added, cash expense, non-cash expense).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'        => 'required|in:starting_cash,cash_added,cash_expense,non_cash_expense',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $entry = CashDrawerEntry::create([
            'type'        => $validated['type'],
            'amount'      => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'user_id'     => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Cash drawer entry recorded successfully.',
            'entry'   => $entry->load('user'),
        ], 201);
    }

    /**
     * Update the amount/description of an existing cash drawer entry.
     */
    public function update(Request $request, CashDrawerEntry $cashDrawerEntry)
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $cashDrawerEntry->update([
            'amount'      => $validated['amount'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Cash drawer entry updated successfully.',
            'entry'   => $cashDrawerEntry->load('user'),
        ]);
    }
}
