<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSettlement;
use App\Models\Sale;
use App\Models\Exchange;
use App\Models\CashDrawerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShiftSettlementController extends Controller
{
    /**
     * Record a new shift settlement (Close Shift).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $settlement = ShiftSettlement::create([
            'user_id'     => $request->user()->id,
            'settled_at'  => now(),
            'notes'       => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message'    => 'Shift closed successfully.',
            'settlement' => $settlement->load('user'),
        ], 201);
    }

    /**
     * Get today's shift settlements.
     */
    public function today()
    {
        $settlements = ShiftSettlement::today()
            ->with('user')
            ->orderBy('settled_at')
            ->get();

        // Build shift periods: each settlement is a boundary
        // Shift 1: start of day → settlement[0]
        // Shift 2: settlement[0] → settlement[1]  (or now if last)
        // etc.
        $shifts = $this->buildShifts($settlements);

        return response()->json([
            'settlements' => $settlements,
            'shifts'      => $shifts,
        ]);
    }

    /**
     * Get shift settlements for a specific date.
     */
    public function byDate(Request $request)
    {
        $request->validate(['date' => 'required|date_format:Y-m-d']);
        $date      = Carbon::parse($request->date);
        $dateStart = $date->copy()->startOfDay();
        $dateEnd   = $date->copy()->endOfDay();

        $settlements = ShiftSettlement::whereBetween('settled_at', [$dateStart, $dateEnd])
            ->with('user')
            ->orderBy('settled_at')
            ->get();

        $shifts = $this->buildShifts($settlements, $date);

        return response()->json([
            'settlements' => $settlements,
            'shifts'      => $shifts,
        ]);
    }

    private function buildShifts($settlements, ?Carbon $date = null)
    {
        $dayStart = $date ? $date->copy()->startOfDay() : Carbon::today();
        $dayEnd   = $date ? $date->copy()->endOfDay() : now();
        $isPast   = $date && $date->copy()->startOfDay()->lt(Carbon::today());

        $boundaries = collect([$dayStart])
            ->merge($settlements->pluck('settled_at'))
            ->push($dayEnd);

        $shifts = [];
        for ($i = 0; $i < $boundaries->count() - 1; $i++) {
            $from = $boundaries[$i];
            $to   = $boundaries[$i + 1];

            $sales = Sale::completed()
                ->whereBetween('sale_date', [$from, $to])
                ->with(['items.product', 'customer', 'user'])
                ->orderBy('sale_date')
                ->get();

            $exchanges = Exchange::where('status', 'completed')
                ->whereBetween('exchange_date', [$from, $to])
                ->with(['items.product', 'originalSale', 'user'])
                ->orderBy('exchange_date')
                ->get();

            $total      = $sales->sum('total');
            $cashTotal  = $sales->where('payment_method', 'cash')->sum('total');
            $gcashTotal = $sales->where('payment_method', 'ewallet')->sum('total');

            // Add extra payments from exchanges (amount_due > 0)
            $exchangeCashTotal  = $exchanges->where('payment_method', 'cash')->sum('amount_paid');
            $exchangeGcashTotal = $exchanges->where('payment_method', 'ewallet')->sum('amount_paid');

            $shiftCashTotal = round($cashTotal + $exchangeCashTotal, 2);

            $drawerEntries = CashDrawerEntry::whereBetween('created_at', [$from, $to])->get();
            $startingCash      = round($drawerEntries->where('type', 'starting_cash')->sum('amount'), 2);
            $cashAdded         = round($drawerEntries->where('type', 'cash_added')->sum('amount'), 2);
            $cashExpenses      = round($drawerEntries->where('type', 'cash_expense')->sum('amount'), 2);
            $nonCashExpenses   = round($drawerEntries->where('type', 'non_cash_expense')->sum('amount'), 2);
            $cashInDrawer      = round($startingCash + $cashAdded + $shiftCashTotal - $cashExpenses, 2);

            $shifts[] = [
                'shift_number'       => $i + 1,
                'label'              => 'Shift ' . ($i + 1),
                'from'               => $from->toIso8601String(),
                'to'                 => $to->toIso8601String(),
                'is_closed'          => $isPast || $i < $settlements->count(),
                'sales'              => $sales,
                'count'              => $sales->count(),
                'total'              => round($total, 2),
                'cash_total'         => $shiftCashTotal,
                'gcash_total'        => round($gcashTotal + $exchangeGcashTotal, 2),
                'exchanges'          => $exchanges,
                'exchanges_count'    => $exchanges->count(),
                'exchanges_paid'     => round($exchanges->sum('amount_paid'), 2),
                'starting_cash'      => $startingCash,
                'cash_added'         => $cashAdded,
                'cash_expenses'      => $cashExpenses,
                'non_cash_expenses'  => $nonCashExpenses,
                'cash_in_drawer'     => $cashInDrawer,
            ];
        }

        return $shifts;
    }
}
