<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function adjustStock(array $data)
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);
            
            // Generate reference number
            if (!isset($data['reference_number'])) {
                $data['reference_number'] = $this->generateReferenceNumber();
            }

            $quantityBefore = $product->stock_quantity;
            $quantityAdjusted = $data['quantity_adjusted'];
            $type = $data['type']; // 'increase' or 'decrease'

            // Calculate new quantity
            if ($type === 'increase') {
                $quantityAfter = $quantityBefore + $quantityAdjusted;
            } else {
                $quantityAfter = $quantityBefore - $quantityAdjusted;
                if ($quantityAfter < 0) {
                    throw new \Exception('Stock quantity cannot be negative');
                }
            }

            // Create stock adjustment record
            $adjustment = StockAdjustment::create([
                'reference_number' => $data['reference_number'],
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
                'quantity_before' => $quantityBefore,
                'quantity_adjusted' => $quantityAdjusted,
                'quantity_after' => $quantityAfter,
                'type' => $type,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'adjustment_date' => $data['adjustment_date'] ?? now(),
            ]);

            // Update product stock
            $product->stock_quantity = $quantityAfter;
            $product->save();

            // Create stock movement
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $quantityAdjusted,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => 'stock_adjustment_id',
                'reference_id' => $adjustment->id,
                'user_id' => auth()->id(),
                'notes' => $data['reason'] . ': ' . ($data['notes'] ?? ''),
            ]);

            return $adjustment->load('product');
        });
    }

    public function getStockMovements($productId = null, $startDate = null, $endDate = null)
    {
        $query = StockMovement::with(['product', 'user']);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getInventoryValue()
    {
        return Product::selectRaw('
            SUM(cost * stock_quantity) as total_cost,
            SUM(price * stock_quantity) as total_retail_value,
            COUNT(*) as total_products,
            SUM(stock_quantity) as total_units,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_products,
            SUM(CASE WHEN stock_verified = 1 THEN 1 ELSE 0 END) as stock_verified_count
        ')->first();
    }

    public function getLowStockAlert()
    {
        return Product::lowStock()
            ->with(['category', 'brand'])
            ->get()
            ->map(function ($product) {
                return [
                    'product' => $product,
                    'stock_quantity' => $product->stock_quantity,
                    'reorder_level' => $product->reorder_level,
                    'deficit' => $product->reorder_level - $product->stock_quantity,
                ];
            });
    }

    public function getStockReport()
    {
        $products = Product::with(['category', 'brand'])->get();

        return [
            'total_products' => $products->count(),
            'active_products' => $products->where('is_active', true)->count(),
            'low_stock_products' => $products->filter(fn($p) => $p->is_low_stock)->count(),
            'out_of_stock_products' => $products->filter(fn($p) => $p->is_out_of_stock)->count(),
            'total_inventory_value' => $products->sum(fn($p) => $p->cost * $p->stock_quantity),
            'total_retail_value' => $products->sum(fn($p) => $p->price * $p->stock_quantity),
            'products' => $products,
        ];
    }

    private function generateReferenceNumber()
    {
        $date = now()->format('Ymd');
        $lastAdjustment = StockAdjustment::where('reference_number', 'like', "ADJ-{$date}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastAdjustment) {
            $lastNumber = (int) substr($lastAdjustment->reference_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "ADJ-{$date}-{$newNumber}";
    }
}
