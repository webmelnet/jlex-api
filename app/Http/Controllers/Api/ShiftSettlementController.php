<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSettlement;
use App\Models\Sale;
use App\Models\Exchange;
use App\Models\SaleReturn;
use App\Models\CashDrawerEntry;
use App\Models\AppSetting;
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

    /**
     * Get a daily cash drawer summary for each day in a date range. Each day
     * is aggregated as a single combined shift (sums across whatever shifts
     * actually occurred that day), used by the Sales dashboard's Cash Drawer
     * table so it reads consistently for both single-day and multi-day filters.
     */
    public function byRange(Request $request)
    {
        $request->validate([
            'start' => 'required|date_format:Y-m-d',
            'end'   => 'required|date_format:Y-m-d|after_or_equal:start',
        ]);

        $defaultStartingCash = (float) (AppSetting::asObject()['default_starting_cash'] ?? 0);

        $start = Carbon::parse($request->start)->startOfDay();
        $end   = Carbon::parse($request->end)->startOfDay();

        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd   = $date->copy()->endOfDay();

            $sales = Sale::completed()->whereBetween('sale_date', [$dayStart, $dayEnd])->get();

            $exchanges = Exchange::where('status', 'completed')
                ->whereBetween('exchange_date', [$dayStart, $dayEnd])
                ->get();

            $cashSales   = round($sales->where('payment_method', 'cash')->sum('total')
                + $exchanges->where('payment_method', 'cash')->sum('amount_paid'), 2);
            $ewalletSales = round($sales->where('payment_method', 'ewallet')->sum('total')
                + $exchanges->where('payment_method', 'ewallet')->sum('amount_paid'), 2);
            $grandTotalSales = round($sales->sum('total'), 2);

            $drawerEntries = CashDrawerEntry::whereBetween('created_at', [$dayStart, $dayEnd])->get();

            $startingCashEntries = $drawerEntries->where('type', 'starting_cash');
            $startingCash = $startingCashEntries->isEmpty()
                ? round($defaultStartingCash, 2)
                : round($startingCashEntries->sum('amount'), 2);
            $cashAdded       = round($drawerEntries->where('type', 'cash_added')->sum('amount'), 2);
            $cashExpenses    = round($drawerEntries->where('type', 'cash_expense')->sum('amount'), 2);
            $nonCashExpenses = round($drawerEntries->where('type', 'non_cash_expense')->sum('amount'), 2);
            $cashRefunds     = round($drawerEntries->where('type', 'cash_refund')->sum('amount'), 2);
            $cashInDrawer    = round($startingCash + $cashAdded + $cashSales - $cashExpenses - $cashRefunds, 2);

            $cashExpenseEntries = $drawerEntries->where('type', 'cash_expense')
                ->map(fn ($entry) => [
                    'id'          => $entry->id,
                    'amount'      => (float) $entry->amount,
                    'description' => $entry->description,
                ])
                ->values();

            $days[] = [
                'date'                  => $date->toDateString(),
                'starting_cash'         => $startingCash,
                'cash_added'            => $cashAdded,
                'cash_sales'            => $cashSales,
                'ewallet_sales'         => $ewalletSales,
                'grand_total_sales'     => $grandTotalSales,
                'cash_expenses'         => $cashExpenses,
                'cash_expense_entries'  => $cashExpenseEntries,
                'non_cash_expenses'     => $nonCashExpenses,
                'cash_refunds'          => $cashRefunds,
                'cash_in_drawer'        => $cashInDrawer,
            ];
        }

        return response()->json(['days' => $days]);
    }

    /**
     * Get cash expense entries within a date range (for multi-day reporting,
     * where per-shift breakdowns aren't shown).
     */
    public function cashExpenses(Request $request)
    {
        $request->validate([
            'start' => 'required|date_format:Y-m-d',
            'end'   => 'required|date_format:Y-m-d|after_or_equal:start',
        ]);

        $start = Carbon::parse($request->start)->startOfDay();
        $end   = Carbon::parse($request->end)->endOfDay();

        $entries = CashDrawerEntry::where('type', 'cash_expense')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get(['id', 'amount', 'description', 'created_at'])
            ->map(fn ($entry) => [
                'id'          => $entry->id,
                'date'        => $entry->created_at->toIso8601String(),
                'amount'      => (float) $entry->amount,
                'description' => $entry->description,
            ]);

        return response()->json([
            'entries' => $entries,
            'total'   => round($entries->sum('amount'), 2),
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

        $defaultStartingCash = (float) (AppSetting::asObject()['default_starting_cash'] ?? 0);

        $shifts = [];
        for ($i = 0; $i < $boundaries->count() - 1; $i++) {
            $from = $boundaries[$i];
            $to   = $boundaries[$i + 1];

            $sales = Sale::completed()
                ->whereBetween('sale_date', [$from, $to])
                ->with(['items.product', 'customer', 'user', 'staffUser', 'queue.createdBy', 'returns.user'])
                ->orderBy('sale_date')
                ->get();

            $exchanges = Exchange::where('status', 'completed')
                ->whereBetween('exchange_date', [$from, $to])
                ->with(['items.product', 'originalSale', 'user'])
                ->orderBy('exchange_date')
                ->get();

            $returns = SaleReturn::where('status', 'completed')
                ->whereBetween('return_date', [$from, $to])
                ->with(['items.product', 'sale', 'user'])
                ->orderBy('return_date')
                ->get();

            $total      = $sales->sum('total');
            $cashTotal  = $sales->where('payment_method', 'cash')->sum('total');
            $gcashTotal = $sales->where('payment_method', 'ewallet')->sum('total');

            // Add extra payments from exchanges (amount_due > 0)
            $exchangeCashTotal  = $exchanges->where('payment_method', 'cash')->sum('amount_paid');
            $exchangeGcashTotal = $exchanges->where('payment_method', 'ewallet')->sum('amount_paid');

            $shiftCashTotal = round($cashTotal + $exchangeCashTotal, 2);

            $drawerEntries = CashDrawerEntry::whereBetween('created_at', [$from, $to])->get();
            $startingCashEntries = $drawerEntries->where('type', 'starting_cash');
            // No entry logged yet for this shift — fall back to the configured
            // default so the drawer total is correct without a manual step.
            // Once a cashier logs an actual entry, that takes over.
            $startingCash      = $startingCashEntries->isEmpty()
                ? round($defaultStartingCash, 2)
                : round($startingCashEntries->sum('amount'), 2);
            $cashAdded         = round($drawerEntries->where('type', 'cash_added')->sum('amount'), 2);
            $cashExpenses      = round($drawerEntries->where('type', 'cash_expense')->sum('amount'), 2);
            $nonCashExpenses   = round($drawerEntries->where('type', 'non_cash_expense')->sum('amount'), 2);
            $cashRefunds       = round($drawerEntries->where('type', 'cash_refund')->sum('amount'), 2);
            $cashInDrawer      = round($startingCash + $cashAdded + $shiftCashTotal - $cashExpenses - $cashRefunds, 2);

            $cashExpenseEntries = $drawerEntries->where('type', 'cash_expense')
                ->map(fn ($entry) => ['id' => $entry->id, 'amount' => (float) $entry->amount, 'description' => $entry->description])
                ->values();
            $nonCashExpenseEntries = $drawerEntries->where('type', 'non_cash_expense')
                ->map(fn ($entry) => ['id' => $entry->id, 'amount' => (float) $entry->amount, 'description' => $entry->description])
                ->values();
            $cashExpensesDescription = $cashExpenseEntries->pluck('description')->filter()->implode(', ');
            $nonCashExpensesDescription = $nonCashExpenseEntries->pluck('description')->filter()->implode(', ');

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
                'returns'            => $returns,
                'returns_count'      => $returns->count(),
                'returns_total'      => round($returns->sum('refund_total'), 2),
                'starting_cash'      => $startingCash,
                'cash_added'         => $cashAdded,
                'cash_expenses'      => $cashExpenses,
                'cash_expenses_description' => $cashExpensesDescription,
                'cash_expense_entries' => $cashExpenseEntries,
                'non_cash_expenses'  => $nonCashExpenses,
                'non_cash_expenses_description' => $nonCashExpensesDescription,
                'non_cash_expense_entries' => $nonCashExpenseEntries,
                'cash_refunds'       => $cashRefunds,
                'cash_in_drawer'     => $cashInDrawer,
            ];
        }

        // On a past day, the period after the final settlement up to midnight
        // is not a real shift unless something actually happened in it — it's
        // just an artifact of always closing the boundary list at day's end.
        // Drop it so callers that pick "the last shift" (e.g. the cash drawer
        // summary) land on the shift that actually holds the day's activity.
        while ($isPast && count($shifts) > 1 && $this->isEmptyShift(end($shifts))) {
            array_pop($shifts);
        }

        return $shifts;
    }

    private function isEmptyShift(array $shift): bool
    {
        return $shift['count'] === 0
            && $shift['exchanges_count'] === 0
            && $shift['returns_count'] === 0
            && $shift['cash_added'] == 0
            && $shift['cash_expenses'] == 0
            && $shift['non_cash_expenses'] == 0
            && $shift['cash_refunds'] == 0;
    }
}
