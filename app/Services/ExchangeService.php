<?php

namespace App\Services;

use App\Models\Exchange;
use App\Models\ExchangeItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ExchangeService
{
    /**
     * Process an item exchange.
     *
     * Rules:
     *  - replacement_total >= return_total  (no cash refunds)
     *  - amount_due = replacement_total - return_total  (customer pays the difference)
     *
     * @param array $data {
     *   original_sale_id: int,
     *   returned_items: [{ sale_item_id, product_id, quantity, price }],
     *   replacement_items: [{ product_id, quantity, price }],
     *   amount_paid: float,
     *   payment_method: string|null,
     *   ewallet_reference: string|null,
     *   notes: string|null,
     * }
     */
    public function processExchange(array $data): Exchange
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::with('items.product')->findOrFail($data['original_sale_id']);

            if ($sale->status === 'cancelled') {
                throw new \Exception('Cannot exchange items from a cancelled sale.');
            }

            // Calculate return total from the selected items
            $returnTotal = collect($data['returned_items'])->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            });

            // Calculate replacement total
            $replacementTotal = collect($data['replacement_items'])->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            });

            if ($replacementTotal < $returnTotal) {
                throw new \Exception(
                    'Replacement total (' . number_format($replacementTotal, 2) .
                    ') must be equal to or greater than the return total (' .
                    number_format($returnTotal, 2) . ').'
                );
            }

            $amountDue = round($replacementTotal - $returnTotal, 2);

            // Create Exchange record
            $exchange = Exchange::create([
                'exchange_number'   => $this->generateExchangeNumber(),
                'original_sale_id'  => $sale->id,
                'user_id'           => auth()->id(),
                'exchange_date'     => now(),
                'return_total'      => $returnTotal,
                'replacement_total' => $replacementTotal,
                'amount_due'        => $amountDue,
                'amount_paid'       => $data['amount_paid'] ?? 0,
                'payment_method'    => $data['payment_method'] ?? null,
                'ewallet_reference' => $data['ewallet_reference'] ?? null,
                'status'            => 'completed',
                'notes'             => $data['notes'] ?? null,
            ]);

            // Process returned items — restock them
            foreach ($data['returned_items'] as $item) {
                ExchangeItem::create([
                    'exchange_id'          => $exchange->id,
                    'product_id'           => $item['product_id'],
                    'original_sale_item_id' => $item['sale_item_id'] ?? null,
                    'type'                 => 'returned',
                    'quantity'             => $item['quantity'],
                    'price'                => $item['price'],
                    'subtotal'             => $item['price'] * $item['quantity'],
                ]);

                $product = Product::find($item['product_id']);
                if ($product && $product->track_inventory) {
                    $oldQty = $product->stock_quantity;
                    // Return increases stock
                    $product->stock_quantity += $item['quantity'];
                    $product->save();

                    StockMovement::create([
                        'product_id'      => $product->id,
                        'type'            => 'exchange_return',
                        'quantity'        => $item['quantity'],
                        'quantity_before' => $oldQty,
                        'quantity_after'  => $product->stock_quantity,
                        'reference_type'  => 'exchange_id',
                        'reference_id'    => $exchange->id,
                        'user_id'         => auth()->id(),
                        'notes'           => 'Item returned via exchange ' . $exchange->exchange_number,
                    ]);
                }
            }

            // Process replacement items — deduct from stock
            foreach ($data['replacement_items'] as $item) {
                ExchangeItem::create([
                    'exchange_id' => $exchange->id,
                    'product_id'  => $item['product_id'],
                    'type'        => 'replacement',
                    'quantity'    => $item['quantity'],
                    'price'       => $item['price'],
                    'subtotal'    => $item['price'] * $item['quantity'],
                ]);

                $product = Product::findOrFail($item['product_id']);
                if ($product->track_inventory) {
                    $oldQty = $product->stock_quantity;
                    // Replacement reduces stock
                    $product->stock_quantity -= $item['quantity'];
                    $product->save();

                    StockMovement::create([
                        'product_id'      => $product->id,
                        'type'            => 'exchange_out',
                        'quantity'        => $item['quantity'],
                        'quantity_before' => $oldQty,
                        'quantity_after'  => $product->stock_quantity,
                        'reference_type'  => 'exchange_id',
                        'reference_id'    => $exchange->id,
                        'user_id'         => auth()->id(),
                        'notes'           => 'Item given as replacement via exchange ' . $exchange->exchange_number,
                    ]);
                }
            }

            return $exchange->load([
                'items.product',
                'originalSale',
                'user',
            ]);
        });
    }

    private function generateExchangeNumber(): string
    {
        $date = now()->format('ymd');

        $last = Exchange::where('exchange_number', 'like', "EX{$date}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = $last
            ? str_pad((int) substr($last->exchange_number, -4) + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        return "EX{$date}{$seq}"; // e.g. EX2603100001
    }
}
