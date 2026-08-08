<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\CashDrawerEntry;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Build the per-line preview (remaining returnable quantity + advisory
     * policy warning) shown to the cashier before submitting. Reused as the
     * basis for validation in processReturn() so the numbers always match.
     */
    public function previewSale(Sale $sale): array
    {
        $lines = $sale->items->map(function ($item) use ($sale) {
            return [
                'sale_item_id'        => $item->id,
                'product_id'          => $item->product_id,
                'product'             => $item->product,
                'quantity'            => $item->quantity,
                'returned_quantity'   => $item->returned_quantity,
                'returnable_quantity' => $item->remainingReturnableQuantity(),
                'price'               => $item->price,
                'return_policy'       => $item->product->return_policy ?? null,
                'policy_warning'      => $this->policyWarning($item->product, $sale->sale_date),
            ];
        });

        return [
            'sale'  => $sale,
            'lines' => $lines,
        ];
    }

    /**
     * Process a return.
     *
     * @param array $data {
     *   sale_id: int,
     *   items: [{ sale_item_id, quantity }],
     *   notes: string|null,
     *   acknowledge_policy_warnings: bool|null,
     * }
     */
    public function processReturn(array $data): SaleReturn
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::with('items.product')->findOrFail($data['sale_id']);

            if ($sale->status === 'cancelled') {
                throw new \Exception('Cannot return items from a cancelled sale.');
            }
            if ($sale->status === 'refunded') {
                throw new \Exception('This sale has already been fully refunded.');
            }

            $lines = [];
            $refundTotal = 0;
            $hasWarning = false;

            foreach ($data['items'] as $row) {
                $saleItem = $sale->items->firstWhere('id', $row['sale_item_id']);
                if (!$saleItem) {
                    throw new \Exception('Item does not belong to this sale.');
                }

                $remaining = $saleItem->remainingReturnableQuantity();
                if ($row['quantity'] > $remaining) {
                    throw new \Exception(
                        "Cannot return {$row['quantity']} of {$saleItem->product->name}; only {$remaining} remaining."
                    );
                }

                $warning = $this->policyWarning($saleItem->product, $sale->sale_date);
                $hasWarning = $hasWarning || (bool) $warning;

                // Price is derived from the sale_item server-side, never
                // trusted from client input, so a tampered payload can't
                // inflate the refund.
                $subtotal = $saleItem->price * $row['quantity'];
                $refundTotal += $subtotal;

                $lines[] = [
                    'saleItem'     => $saleItem,
                    'product'      => $saleItem->product,
                    'quantity'     => $row['quantity'],
                    'price'        => $saleItem->price,
                    'subtotal'     => $subtotal,
                    'return_policy'=> $saleItem->product->return_policy ?? null,
                    'warning'      => $warning,
                ];
            }

            $return = SaleReturn::create([
                'return_number'   => $this->generateReturnNumber(),
                'sale_id'         => $sale->id,
                'user_id'         => auth()->id(),
                'return_date'     => now(),
                'refund_total'    => round($refundTotal, 2),
                'status'          => 'completed',
                'policy_override' => $hasWarning,
                'notes'           => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                ReturnItem::create([
                    'return_id'                => $return->id,
                    'sale_item_id'              => $line['saleItem']->id,
                    'product_id'                => $line['product']->id,
                    'quantity'                  => $line['quantity'],
                    'price'                     => $line['price'],
                    'subtotal'                  => $line['subtotal'],
                    'return_policy_at_return'   => $line['return_policy'],
                    'policy_warning'            => $line['warning'],
                ]);

                $product = $line['product'];
                if ($product->track_inventory) {
                    $oldQty = $product->stock_quantity;
                    $product->updateStock($line['quantity'], 'sale_return');

                    StockMovement::create([
                        'product_id'      => $product->id,
                        'type'            => 'sale_return',
                        'quantity'        => $line['quantity'],
                        'quantity_before' => $oldQty,
                        'quantity_after'  => $product->stock_quantity,
                        'reference_type'  => 'return_id',
                        'reference_id'    => $return->id,
                        'user_id'         => auth()->id(),
                        'notes'           => 'Returned via ' . $return->return_number,
                    ]);
                }

                $line['saleItem']->increment('returned_quantity', $line['quantity']);
            }

            CashDrawerEntry::create([
                'type'        => 'cash_refund',
                'amount'      => $return->refund_total,
                'description' => "Refund - Invoice {$sale->invoice_number} - {$return->return_number}",
                'user_id'     => auth()->id(),
            ]);

            $sale->refresh();
            $fullyReturned = $sale->items->every(fn ($item) => $item->returned_quantity >= $item->quantity);
            if ($fullyReturned) {
                $sale->status = 'refunded';
                $sale->save();
            }

            return $return->load(['items.product', 'sale', 'user']);
        });
    }

    private function policyWarning(Product $product, $saleDate): ?string
    {
        if ($product->return_policy === 'no_return') {
            return 'no_return';
        }

        if ($product->return_policy === 'return_24h' && now()->gt($saleDate->copy()->addHours(24))) {
            return 'window_expired';
        }

        return null;
    }

    private function generateReturnNumber(): string
    {
        $date = now()->format('ymd');

        $last = SaleReturn::where('return_number', 'like', "RT{$date}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = $last
            ? str_pad((int) substr($last->return_number, -4) + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        return "RT{$date}{$seq}"; // e.g. RT2608080001
    }
}
