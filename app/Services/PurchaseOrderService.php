<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function createPurchaseOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Generate PO number if not provided
            if (!isset($data['po_number'])) {
                $data['po_number'] = $this->generatePONumber();
            }

            // Set user_id from authenticated user
            $data['user_id'] = auth()->id();

            // Create purchase order
            $po = PurchaseOrder::create([
                'po_number' => $data['po_number'],
                'supplier_id' => $data['supplier_id'],
                'user_id' => $data['user_id'],
                'order_date' => $data['order_date'] ?? now(),
                'expected_date' => $data['expected_date'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'subtotal' => 0,
                'tax' => $data['tax'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'shipping' => $data['shipping'] ?? 0,
                'total' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create purchase order items
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['cost'] * $item['quantity_ordered'];
                $subtotal += $itemSubtotal;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'cost' => $item['cost'],
                    'subtotal' => $itemSubtotal,
                ]);
            }

            // Update PO totals
            $po->subtotal = $subtotal;
            $po->total = $subtotal + $po->tax + $po->shipping - $po->discount;
            $po->save();

            return $po->load(['items.product', 'supplier', 'user']);
        });
    }

    public function updatePurchaseOrder(PurchaseOrder $po, array $data)
    {
        return DB::transaction(function () use ($po, $data) {
            // Update PO details
            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'order_date' => $data['order_date'] ?? $po->order_date,
                'expected_date' => $data['expected_date'] ?? $po->expected_date,
                'status' => $data['status'] ?? $po->status,
                'tax' => $data['tax'] ?? $po->tax,
                'discount' => $data['discount'] ?? $po->discount,
                'shipping' => $data['shipping'] ?? $po->shipping,
                'notes' => $data['notes'] ?? $po->notes,
            ]);

            // Update items if provided
            if (isset($data['items'])) {
                // Delete existing items
                $po->items()->delete();

                // Create new items
                $subtotal = 0;
                foreach ($data['items'] as $item) {
                    $itemSubtotal = $item['cost'] * $item['quantity_ordered'];
                    $subtotal += $itemSubtotal;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id' => $item['product_id'],
                        'quantity_ordered' => $item['quantity_ordered'],
                        'quantity_received' => $item['quantity_received'] ?? 0,
                        'cost' => $item['cost'],
                        'subtotal' => $itemSubtotal,
                    ]);
                }

                $po->subtotal = $subtotal;
                $po->total = $subtotal + $po->tax + $po->shipping - $po->discount;
                $po->save();
            } else {
                // Recalculate totals
                $po->calculateTotals();
            }

            return $po->load(['items.product', 'supplier', 'user']);
        });
    }

    public function receiveItems(PurchaseOrder $po, array $items)
    {
        return DB::transaction(function () use ($po, $items) {
            foreach ($items as $itemData) {
                $poItem = PurchaseOrderItem::where('purchase_order_id', $po->id)
                    ->where('product_id', $itemData['product_id'])
                    ->firstOrFail();

                $quantityToReceive = $itemData['quantity_received'];

                // Validate quantity
                $remainingQuantity = $poItem->quantity_ordered - $poItem->quantity_received;
                if ($quantityToReceive > $remainingQuantity) {
                    throw new \Exception("Cannot receive more than ordered quantity");
                }

                // Receive the items
                $poItem->receiveQuantity($quantityToReceive);
            }

            // Check if all items are received
            $po->checkIfFullyReceived();

            return $po->load(['items.product', 'supplier']);
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $po)
    {
        if ($po->status === 'received') {
            throw new \Exception('Cannot cancel a received purchase order');
        }

        $po->status = 'cancelled';
        $po->save();

        return $po;
    }

    private function generatePONumber()
    {
        $date = now()->format('Ymd');
        $lastPO = PurchaseOrder::where('po_number', 'like', "PO-{$date}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPO) {
            $lastNumber = (int) substr($lastPO->po_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "PO-{$date}-{$newNumber}";
    }
}
