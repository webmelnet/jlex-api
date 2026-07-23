<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleService
{
    public function createSale(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Generate invoice number if not provided
            if (!isset($data['invoice_number'])) {
                $data['invoice_number'] = $this->generateInvoiceNumber();
            }

            // Set sale date if not provided
            if (!isset($data['sale_date'])) {
                $data['sale_date'] = now();
            }

            // Set user_id (cashier) from authenticated user
            $data['user_id'] = auth()->id();

            // Create sale
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'],
                'customer_id' => $data['customer_id'] ?? null,
                'customer_type' => $data['customer_type'] ?? 'walk-in',
                'user_id' => $data['user_id'],
                'sale_date' => $data['sale_date'],
                'subtotal' => 0,
                'tax' => $data['tax'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'total' => 0,
                'amount_paid' => $data['amount_paid'],
                'change_amount' => 0,
                'payment_method' => $data['payment_method'],
                'ewallet_reference' => $data['ewallet_reference'] ?? null,
                'ewallet_screenshot' => $data['ewallet_screenshot'] ?? null,
                'status' => $data['status'] ?? 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create sale items
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);


                $itemSubtotal = ($item['price'] * $item['quantity']) - ($item['discount'] ?? 0);
                $subtotal += $itemSubtotal;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'subtotal' => $itemSubtotal,
                ]);

                // Update product stock
                if ($product->track_inventory) {
                    $oldQuantity = $product->updateStock($item['quantity'], 'sale');

                    // Create stock movement
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'sale',
                        'quantity' => $item['quantity'],
                        'quantity_before' => $oldQuantity,
                        'quantity_after' => $product->stock_quantity,
                        'reference_type' => 'sale_id',
                        'reference_id' => $sale->id,
                        'user_id' => $data['user_id'],
                    ]);
                }
            }

            // Update sale totals
            $sale->subtotal = $subtotal;
            $sale->total = $subtotal + $sale->tax - $sale->discount;
            $sale->change_amount = $sale->amount_paid - $sale->total;
            $sale->save();

            // Add loyalty points to customer if applicable
            if ($sale->customer_id && $sale->status === 'completed') {
                $customer = $sale->customer;
                $points = floor($sale->total / 10); // 1 point per $10
                $customer->addLoyaltyPoints($points);
            }

            return $sale->load(['items.product', 'customer', 'user']);
        });
    }

    public function cancelSale(Sale $sale)
    {
        return DB::transaction(function () use ($sale) {
            if ($sale->status === 'cancelled') {
                throw new \Exception('Sale is already cancelled');
            }

            // Restore stock for each item
            foreach ($sale->items as $item) {
                $product = $item->product;

                if ($product->track_inventory) {
                    $oldQuantity = $product->stock_quantity;
                    $product->updateStock($item->quantity, 'return');

                    // Create stock movement for return
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'return',
                        'quantity' => $item->quantity,
                        'quantity_before' => $oldQuantity,
                        'quantity_after' => $product->stock_quantity,
                        'reference_type' => 'sale_id',
                        'reference_id' => $sale->id,
                        'user_id' => auth()->id(),
                        'notes' => 'Sale cancelled',
                    ]);
                }
            }

            // Deduct loyalty points if customer exists
            if ($sale->customer_id) {
                $customer = $sale->customer;
                $points = floor($sale->total / 10);
                $customer->deductLoyaltyPoints($points);
            }

            $sale->status = 'cancelled';
            $sale->save();

            return $sale;
        });
    }

    public function getSalesReport($startDate = null, $endDate = null)
    {
        $query = Sale::with(['items.product', 'customer', 'user'])
            ->where('status', 'completed');

        if ($startDate) {
            $query->whereDate('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('sale_date', '<=', $endDate);
        }

        $sales = $query->orderBy('sale_date', 'desc')->get();

        // Calculate total revenue (profit) from all sales
        $totalRevenue = $sales->sum(function ($sale) {
            return $this->calculateSaleRevenue($sale);
        });

        // Format sales data for frontend
        $formattedSales = $sales->map(function ($sale) {
            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer_name' => $sale->customer ? $sale->customer->name : null,
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'amount_paid' => $sale->amount_paid,
                'change_amount' => $sale->change_amount,
                'payment_method' => $sale->payment_method,
                'status' => $sale->status,
                'created_at' => $sale->sale_date->toISOString(), // Map sale_date to created_at for frontend
                'items_count' => $sale->items->count(),
                'items' => $sale->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'sale_id' => $item->sale_id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'subtotal' => $item->subtotal,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                            'cost' => $item->product->cost ?? 0,
                            'price' => $item->product->price,
                        ],
                    ];
                }),
            ];
        });

        return [
            'sales' => $formattedSales,
            'count' => $sales->count(),
            'total_sale' => $sales->sum(function ($sale) {
                return $sale->subtotal + $sale->tax; // Total before discount
            }),
            'total_sales' => $sales->sum('total'), // For backward compatibility
            'total_amount' => $sales->sum('total'), // Total after discount
            'total_discount' => $sales->sum('discount'),
            'total_revenue' => $totalRevenue, // Profit
            'total_transactions' => $sales->count(),
            'average_transaction' => $sales->avg('total'),
            'total_tax' => $sales->sum('tax'),
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }

    /**
     * Calculate revenue (profit) for a single sale
     * Revenue = (Selling Price - Cost Price) × Quantity for each item
     */
    private function calculateSaleRevenue(Sale $sale)
    {
        if (!$sale->items || $sale->items->count() === 0) {
            return 0;
        }

        return $sale->items->sum(function ($item) {
            $costPrice = (float) ($item->product->cost ?? 0);
            $sellingPrice = (float) $item->price;
            $quantity = $item->quantity;

            $profit = ($sellingPrice - $costPrice) * $quantity;

            return $profit;
        });
    }

    private function generateInvoiceNumber()
    {
        // Format: YYMMDDXXXX (10 characters total)
        // YY = 2-digit year, MM = month, DD = day, XXXX = 4-digit sequence
        $date = now()->format('ymd'); // ymd gives YYMMDD (6 characters)

        $lastInvoice = Sale::where('invoice_number', 'like', "{$date}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$date}{$newNumber}"; // e.g., 2502090001 (10 characters)
    }
}
