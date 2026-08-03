<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCycle;
use App\Services\InventoryCycleService;
use Illuminate\Http\Request;

class InventoryCycleController extends Controller
{
    protected $inventoryCycleService;

    public function __construct(InventoryCycleService $inventoryCycleService)
    {
        $this->inventoryCycleService = $inventoryCycleService;
    }

    public function index()
    {
        return response()->json(
            $this->inventoryCycleService->listCycles()
        );
    }

    public function store()
    {
        $cycle = $this->inventoryCycleService->startNewCycle();

        return response()->json([
            'status' => 'Inventory cycle ready',
            'cycle' => $cycle,
        ], 201);
    }

    public function close(InventoryCycle $cycle)
    {
        try {
            $cycle = $this->inventoryCycleService->closeCycle($cycle);

            return response()->json([
                'status' => 'Inventory cycle closed',
                'cycle' => $cycle,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function reopen(InventoryCycle $cycle)
    {
        try {
            $cycle = $this->inventoryCycleService->reopenCycle($cycle);

            return response()->json([
                'status' => 'Inventory cycle reopened',
                'cycle' => $cycle,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(InventoryCycle $cycle, Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
        ]);

        return response()->json(
            $this->inventoryCycleService->getCycleDetail($cycle, $validated)
        );
    }

    public function recordCount(InventoryCycle $cycle, Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'staff_input' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $item = $this->inventoryCycleService->recordStaffInput(
                $cycle,
                $validated['product_id'],
                $validated['staff_input'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'status' => 'Inventory count saved successfully',
                'item' => [
                    'product_id' => $item->product_id,
                    'staff_input' => $item->staff_input,
                    'staff_input_by' => $item->user?->full_name,
                    'variance' => $item->variance,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function verifyCount(InventoryCycle $cycle, Request $request)
    {
        abort_unless($request->user()->hasRole('Manager'), 403);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        try {
            $item = $this->inventoryCycleService->verifyCount(
                $cycle,
                $validated['product_id'],
                $request->user()->id
            );

            return response()->json([
                'status' => 'Item verified',
                'item' => $this->formatItem($item),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function unverifyCount(InventoryCycle $cycle, Request $request)
    {
        abort_unless($request->user()->hasRole('Manager'), 403);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        try {
            $item = $this->inventoryCycleService->unverifyCount($cycle, $validated['product_id']);

            return response()->json([
                'status' => 'Item verification removed',
                'item' => $this->formatItem($item),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatItem(\App\Models\InventoryCycleItem $item): array
    {
        return [
            'product_id' => $item->product_id,
            'added' => $item->added,
            'deducted' => $item->deducted,
            'non_cash_deducted' => $item->non_cash_deducted,
            'current_stock' => $item->current_stock,
            'verified_at' => $item->verified_at?->toDateTimeString(),
            'verified_by' => $item->verifiedBy?->full_name,
        ];
    }
}
