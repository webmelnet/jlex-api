<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryCycle;
use App\Models\InventoryCycleItem;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryCycleService
{
    /**
     * Movement types that increase stock (folded into "Added").
     */
    private const ADD_TYPES = ['purchase', 'return', 'exchange_return'];

    /**
     * Movement types that decrease stock excluding staff sales (folded into "Deducted").
     */
    private const DEDUCT_TYPES = ['exchange_out'];

    public function listCycles(): array
    {
        $cycles = InventoryCycle::withCount('items')
            ->with(['startedBy', 'closedBy'])
            ->orderByDesc('started_at')
            ->get();

        $countedCounts = InventoryCycleItem::whereIn('inventory_cycle_id', $cycles->pluck('id'))
            ->whereNotNull('staff_input')
            ->select('inventory_cycle_id', DB::raw('COUNT(*) as counted'))
            ->groupBy('inventory_cycle_id')
            ->pluck('counted', 'inventory_cycle_id');

        return $cycles->map(function (InventoryCycle $cycle) use ($countedCounts) {
            return [
                'id' => $cycle->id,
                'period' => $cycle->period->toDateString(),
                'status' => $cycle->status,
                'started_at' => $cycle->started_at->toDateTimeString(),
                'started_by' => $cycle->startedBy?->name,
                'closed_at' => $cycle->closed_at?->toDateTimeString(),
                'closed_by' => $cycle->closedBy?->name,
                'total_products' => $cycle->items_count,
                'counted_products' => $countedCounts[$cycle->id] ?? 0,
                'is_editable' => $cycle->status === 'open',
            ];
        })->values()->all();
    }

    /**
     * Start a new cycle. Only one cycle may be open at a time — if one is
     * already open, it's returned as-is rather than creating a second one
     * (the frontend disables the "New Inventory Cycle" action in that state;
     * this is a server-side guard for direct API calls).
     */
    public function startNewCycle(): InventoryCycle
    {
        $openCycle = InventoryCycle::where('status', 'open')->first();
        if ($openCycle) {
            return $openCycle;
        }

        return DB::transaction(function () {
            $previousCycle = InventoryCycle::where('status', 'closed')
                ->orderByDesc('started_at')
                ->first();

            $cycle = InventoryCycle::create([
                'period' => $this->currentPeriod(),
                'status' => 'open',
                'started_at' => now(),
                'started_by' => auth()->id(),
            ]);

            $products = Product::where('track_inventory', true)->get(['id', 'stock_quantity']);

            $previousStaffInputs = $previousCycle
                ? InventoryCycleItem::where('inventory_cycle_id', $previousCycle->id)->pluck('staff_input', 'product_id')
                : collect();

            $now = now();

            // Added/Deducted/Non Cash Deducted/Current Stock are intentionally left at
            // 0/live-stock here, not computed from movement history — getCycleDetail()
            // recomputes them live for any item that hasn't been counted yet (staff_input
            // still null), so business activity keeps showing up while the count is in
            // progress. They only freeze once recordStaffInput() saves a count.
            $rows = $products->map(function (Product $product) use ($previousStaffInputs, $cycle, $now) {
                return [
                    'inventory_cycle_id' => $cycle->id,
                    'product_id' => $product->id,
                    'beginning_stock' => $previousStaffInputs[$product->id] ?? 0,
                    'added' => 0,
                    'deducted' => 0,
                    'non_cash_deducted' => 0,
                    'current_stock' => $product->stock_quantity,
                    'staff_input' => null,
                    'variance' => null,
                    'user_id' => null,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                InventoryCycleItem::insert($chunk);
            }

            return $cycle;
        });
    }

    public function getCycleDetail(InventoryCycle $cycle, array $filters): array
    {
        $itemsQuery = InventoryCycleItem::with(['product.category', 'product.brand'])
            ->where('inventory_cycle_id', $cycle->id);

        if (!empty($filters['search']) || !empty($filters['category_id']) || !empty($filters['brand_id'])) {
            $itemsQuery->whereHas('product', function ($q) use ($filters) {
                $this->applyProductFilters($q, $filters);
            });
        }

        $items = $itemsQuery->get()->sortBy(fn ($item) => $item->product->name)->values();

        // Items not yet counted show live Added/Deducted/Non Cash Deducted/Current Stock
        // (business keeps happening while the count is in progress). Counted items show
        // the frozen snapshot recordStaffInput() took at the moment staff saved the count.
        $uncounted = $items->whereNull('staff_input');
        $uncountedProductIds = $uncounted->pluck('product_id')->all();
        $movementsByProduct = $this->fetchMovements($uncountedProductIds);
        $periodStart = $this->previousCycle($cycle)?->started_at;
        $liveStock = Product::whereIn('id', $uncountedProductIds)->pluck('stock_quantity', 'id');

        return [
            'cycle' => [
                'id' => $cycle->id,
                'period' => $cycle->period->toDateString(),
                'status' => $cycle->status,
                'started_at' => $cycle->started_at->toDateTimeString(),
                'closed_at' => $cycle->closed_at?->toDateTimeString(),
                'is_editable' => $cycle->status === 'open',
                'can_reopen' => $cycle->status === 'closed' && $this->isLatestCycle($cycle),
            ],
            'items' => $items->map(function (InventoryCycleItem $item) use ($movementsByProduct, $periodStart, $liveStock) {
                if ($item->staff_input === null) {
                    [$added, $deducted, $nonCashDeducted] = $this->classifyMovements(
                        $movementsByProduct->get($item->product_id, collect()),
                        $periodStart
                    );
                    $currentStock = $liveStock[$item->product_id] ?? $item->current_stock;
                } else {
                    $added = $item->added;
                    $deducted = $item->deducted;
                    $nonCashDeducted = $item->non_cash_deducted;
                    $currentStock = $item->current_stock;
                }

                return [
                    'product_id' => $item->product_id,
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'category' => $item->product->category?->name,
                    'brand' => $item->product->brand?->name,
                    'beginning_stock' => $item->beginning_stock,
                    'added' => $added,
                    'deducted' => $deducted,
                    'non_cash_deducted' => $nonCashDeducted,
                    'current_stock' => $currentStock,
                    'staff_input' => $item->staff_input,
                    'variance' => $item->variance,
                ];
            })->values()->all(),
        ];
    }

    public function recordStaffInput(InventoryCycle $cycle, int $productId, int $staffInput, ?string $notes = null): InventoryCycleItem
    {
        if ($cycle->status !== 'open') {
            throw new \RuntimeException('This inventory cycle is closed and can no longer be edited.');
        }

        return DB::transaction(function () use ($cycle, $productId, $staffInput, $notes) {
            $item = InventoryCycleItem::where('inventory_cycle_id', $cycle->id)
                ->where('product_id', $productId)
                ->firstOrFail();

            $product = Product::findOrFail($productId);
            $quantityBefore = $product->stock_quantity;

            // Snapshot Added/Deducted/Non Cash Deducted/Current Stock as of right now,
            // before this save's own reconciliation movement is created below — this
            // freezes them at "the moment staff physically counted it", per getCycleDetail()
            // which otherwise keeps recomputing these live while staff_input is still null.
            $movements = $this->fetchMovements([$product->id])->get($product->id, collect());
            [$added, $deducted, $nonCashDeducted] = $this->classifyMovements(
                $movements,
                $this->previousCycle($cycle)?->started_at
            );

            // Reconcile the live system stock to what staff physically counted.
            // Logged as its own movement type (excluded from ADD_TYPES/DEDUCT_TYPES
            // above) so it isn't double-counted into a future cycle's Added/Deducted —
            // the correction is already captured via this cycle's staff_input becoming
            // the next cycle's Beginning Stock.
            if ($product->track_inventory && $staffInput !== $quantityBefore) {
                $product->stock_quantity = $staffInput;
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'inventory_cycle',
                    'quantity' => abs($staffInput - $quantityBefore),
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $staffInput,
                    'reference_type' => 'inventory_cycle_id',
                    'reference_id' => $cycle->id,
                    'user_id' => auth()->id(),
                    'notes' => $notes ?: 'Stock reconciled via Inventory Cycle count',
                ]);
            }

            $item->update([
                'added' => $added,
                'deducted' => $deducted,
                'non_cash_deducted' => $nonCashDeducted,
                'current_stock' => $quantityBefore,
                'staff_input' => $staffInput,
                'variance' => $staffInput - $quantityBefore,
                'user_id' => auth()->id(),
                'notes' => $notes,
            ]);

            return $item->fresh();
        });
    }

    public function closeCycle(InventoryCycle $cycle): InventoryCycle
    {
        if ($cycle->status !== 'open') {
            throw new \RuntimeException('This inventory cycle is already closed.');
        }

        $cycle->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);

        return $cycle->fresh();
    }

    /**
     * Reopen a closed cycle. Only allowed while it's still the most recent
     * cycle — once a newer cycle has been started, an older one can no
     * longer be reopened (that would risk two cycles open at once).
     */
    public function reopenCycle(InventoryCycle $cycle): InventoryCycle
    {
        if ($cycle->status !== 'closed') {
            throw new \RuntimeException('This inventory cycle is already open.');
        }

        if (!$this->isLatestCycle($cycle)) {
            throw new \RuntimeException('A newer inventory cycle has already been started; this cycle can no longer be reopened.');
        }

        $cycle->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $cycle->fresh();
    }

    private function isLatestCycle(InventoryCycle $cycle): bool
    {
        $latest = InventoryCycle::orderByDesc('started_at')->first();

        return $latest && $latest->id === $cycle->id;
    }

    private function previousCycle(InventoryCycle $cycle): ?InventoryCycle
    {
        return InventoryCycle::where('started_at', '<', $cycle->started_at)
            ->orderByDesc('started_at')
            ->first();
    }

    private function currentPeriod(): string
    {
        return now()->startOfMonth()->toDateString();
    }

    private function applyProductFilters($query, array $filters)
    {
        if (!empty($filters['category_id'])) {
            $categoryIds = [$filters['category_id']];
            $category = Category::with('children')->find($filters['category_id']);
            if ($category && $category->children->isNotEmpty()) {
                $categoryIds = array_merge($categoryIds, $category->children->pluck('id')->toArray());
            }
            $query->whereIn('category_id', $categoryIds);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Fetch all stock movements (with the sale's customer_type, when applicable)
     * for the given product ids, grouped by product_id.
     */
    private function fetchMovements(array $productIds)
    {
        if (empty($productIds)) {
            return collect();
        }

        return DB::table('stock_movements as sm')
            ->leftJoin('sales as s', function ($join) {
                $join->on('sm.reference_id', '=', 's.id')
                    ->where('sm.reference_type', '=', 'sale_id');
            })
            ->whereIn('sm.product_id', $productIds)
            ->select('sm.product_id', 'sm.type', 'sm.quantity', 'sm.created_at', 's.customer_type')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * Classify movements created after $periodStart into [added, deducted, nonCashDeducted].
     */
    private function classifyMovements($movements, ?Carbon $periodStart): array
    {
        $added = 0;
        $deducted = 0;
        $nonCashDeducted = 0;

        foreach ($movements as $movement) {
            if ($periodStart && Carbon::parse($movement->created_at)->lte($periodStart)) {
                continue;
            }

            if (in_array($movement->type, self::ADD_TYPES, true)) {
                $added += $movement->quantity;
            } elseif (in_array($movement->type, self::DEDUCT_TYPES, true)) {
                $deducted += $movement->quantity;
            } elseif ($movement->type === 'adjustment') {
                if ($movement->quantity >= 0) {
                    $added += $movement->quantity;
                } else {
                    $deducted += abs($movement->quantity);
                }
            } elseif ($movement->type === 'sale') {
                if ($movement->customer_type === 'staff') {
                    $nonCashDeducted += $movement->quantity;
                } else {
                    $deducted += $movement->quantity;
                }
            }
        }

        return [$added, $deducted, $nonCashDeducted];
    }
}
