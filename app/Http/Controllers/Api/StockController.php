<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'nullable|string|unique:stock_adjustments,reference_number',
            'product_id' => 'required|exists:products,id',
            'quantity_adjusted' => 'required|integer|min:1',
            'type' => 'required|in:increase,decrease',
            'reason' => 'required|in:damaged,lost,found,return,correction,expired,other',
            'notes' => 'nullable|string',
            'adjustment_date' => 'nullable|date',
        ]);

        try {
            $adjustment = $this->stockService->adjustStock($validated);

            return response()->json([
                'status' => 'Stock adjusted successfully',
                'adjustment' => $adjustment
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function movements(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'per_page' => 'nullable|integer',
        ]);

        $perPage = max(1, min((int) ($validated['per_page'] ?? 20), 200));

        $movements = $this->stockService->getStockMovements(
            $validated['product_id'] ?? null,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['search'] ?? null,
            $validated['category_id'] ?? null,
            $validated['brand_id'] ?? null,
            $perPage
        );

        return response()->json($movements);
    }

    public function inventoryValue()
    {
        $value = $this->stockService->getInventoryValue();
        return response()->json($value);
    }

    public function lowStockAlert()
    {
        $alerts = $this->stockService->getLowStockAlert();
        return response()->json($alerts);
    }

    public function report()
    {
        $report = $this->stockService->getStockReport();
        return response()->json($report);
    }
}
