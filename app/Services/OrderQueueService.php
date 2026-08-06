<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OrderQueue;
use App\Models\OrderQueueItem;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderQueueService
{
    // How long an edit lock stays valid before it's considered stale (e.g. tab closed mid-edit)
    private const EDIT_LOCK_MINUTES = 5;

    public function createQueueOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            $subtotal = $this->calculateItemsSubtotal($data['items']);
            [$loyaltyPointsUsed, $loyaltyDiscount] = $this->resolveLoyaltyRedemption($data, $subtotal);

            $orderQueue = $this->createWithRetry($data, $loyaltyPointsUsed, $loyaltyDiscount);

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                OrderQueueItem::create([
                    'order_queue_id' => $orderQueue->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->effective_price,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $orderQueue->load(['items.product', 'customer', 'createdBy']);
        });
    }

    private function createWithRetry(array $data, int $loyaltyPointsUsed = 0, float $loyaltyDiscount = 0)
    {
        $attempts = 0;

        while (true) {
            try {
                return OrderQueue::create([
                    'queue_number' => $this->generateQueueNumber(),
                    'customer_id' => $data['customer_id'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_type' => $data['customer_type'] ?? 'walk-in',
                    'loyalty_points_used' => $loyaltyPointsUsed,
                    'loyalty_discount' => $loyaltyDiscount,
                    'created_by_user_id' => auth()->id(),
                    'status' => 'queued',
                    'notes' => $data['notes'] ?? null,
                ]);
            } catch (QueryException $e) {
                $attempts++;
                if ($attempts >= 2) {
                    throw $e;
                }
            }
        }
    }

    private function calculateItemsSubtotal(array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $subtotal += $product->effective_price * $item['quantity'];
        }

        return $subtotal;
    }

    /**
     * Redemption rate is fixed at 1 point = ₱1, capped by both the
     * customer's balance and the order subtotal (points are whole numbers,
     * so the discount can't exceed the floor of the subtotal either).
     *
     * @return array{0: int, 1: float} [loyaltyPointsUsed, loyaltyDiscount]
     */
    private function resolveLoyaltyRedemption(array $data, float $subtotal): array
    {
        $requested = (int) ($data['loyalty_points_used'] ?? 0);

        if ($requested <= 0) {
            return [0, 0];
        }

        if (empty($data['customer_id'])) {
            throw new \Exception('A customer must be selected to redeem loyalty points');
        }

        $customer = Customer::findOrFail($data['customer_id']);

        if ($requested > $customer->loyalty_points) {
            throw new \Exception('Customer does not have enough loyalty points');
        }

        $pointsUsed = min($requested, (int) floor($subtotal), (int) $customer->loyalty_points);

        return [$pointsUsed, (float) $pointsUsed];
    }

    public function updateQueueOrder(OrderQueue $orderQueue, array $data)
    {
        return DB::transaction(function () use ($orderQueue, $data) {
            $locked = OrderQueue::where('id', $orderQueue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'queued') {
                throw new \Exception('Only orders that are still queued can be edited');
            }

            $subtotal = $this->calculateItemsSubtotal($data['items']);
            [$loyaltyPointsUsed, $loyaltyDiscount] = $this->resolveLoyaltyRedemption($data, $subtotal);

            $locked->customer_id = $data['customer_id'] ?? null;
            $locked->customer_name = $data['customer_name'] ?? null;
            $locked->customer_type = $data['customer_type'] ?? 'walk-in';
            $locked->loyalty_points_used = $loyaltyPointsUsed;
            $locked->loyalty_discount = $loyaltyDiscount;
            $locked->notes = $data['notes'] ?? null;
            $locked->editing_by_user_id = null;
            $locked->editing_started_at = null;
            $locked->save();

            $locked->items()->delete();

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                OrderQueueItem::create([
                    'order_queue_id' => $locked->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->effective_price,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $locked->load(['items.product', 'customer', 'createdBy', 'claimedBy']);
        });
    }

    public function startEditQueueOrder(OrderQueue $orderQueue)
    {
        return DB::transaction(function () use ($orderQueue) {
            $locked = OrderQueue::where('id', $orderQueue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'queued') {
                throw new \Exception('Only orders that are still queued can be edited');
            }

            if ($this->isEditLockActive($locked) && $locked->editing_by_user_id !== auth()->id()) {
                $editorName = optional($locked->editingBy)->name ?? 'another user';
                throw new \Exception("This order is already being edited by {$editorName}");
            }

            $locked->editing_by_user_id = auth()->id();
            $locked->editing_started_at = now();
            $locked->save();

            return $locked->load(['items.product', 'customer', 'createdBy', 'claimedBy', 'editingBy']);
        });
    }

    public function cancelEditQueueOrder(OrderQueue $orderQueue)
    {
        return DB::transaction(function () use ($orderQueue) {
            $locked = OrderQueue::where('id', $orderQueue->id)->lockForUpdate()->firstOrFail();

            if ($locked->editing_by_user_id === auth()->id()) {
                $locked->editing_by_user_id = null;
                $locked->editing_started_at = null;
                $locked->save();
            }

            return $locked;
        });
    }

    public function claimQueueOrder(OrderQueue $orderQueue)
    {
        return DB::transaction(function () use ($orderQueue) {
            $locked = OrderQueue::where('id', $orderQueue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'queued') {
                throw new \Exception('This order is no longer available to claim');
            }

            if ($this->isEditLockActive($locked)) {
                $editorName = optional($locked->editingBy)->name ?? 'another user';
                throw new \Exception("This order is currently being edited by {$editorName} and cannot be claimed");
            }

            $locked->claimed_by_user_id = auth()->id();
            $locked->claimed_at = now();
            $locked->status = 'claimed';
            $locked->save();

            return $locked->load(['items.product', 'customer', 'createdBy', 'claimedBy']);
        });
    }

    private function isEditLockActive(OrderQueue $orderQueue): bool
    {
        return $orderQueue->editing_by_user_id !== null
            && $orderQueue->editing_started_at !== null
            && $orderQueue->editing_started_at->gt(now()->subMinutes(self::EDIT_LOCK_MINUTES));
    }

    public function releaseQueueOrder(OrderQueue $orderQueue)
    {
        return DB::transaction(function () use ($orderQueue) {
            $locked = OrderQueue::where('id', $orderQueue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'claimed') {
                throw new \Exception('This order is not currently claimed');
            }

            $locked->claimed_by_user_id = null;
            $locked->claimed_at = null;
            $locked->status = 'queued';
            $locked->save();

            return $locked;
        });
    }

    public function cancelQueueOrder(OrderQueue $orderQueue)
    {
        if ($orderQueue->status === 'completed') {
            throw new \Exception('This order has already been completed and cannot be cancelled');
        }

        $orderQueue->status = 'cancelled';
        $orderQueue->save();

        return $orderQueue;
    }

    public function completeQueueOrder(OrderQueue $orderQueue, Sale $sale)
    {
        if ($orderQueue->status !== 'claimed') {
            throw new \Exception('This order is not in a claimable checkout state');
        }

        $orderQueue->status = 'completed';
        $orderQueue->sale_id = $sale->id;
        $orderQueue->save();

        return $orderQueue;
    }

    private function generateQueueNumber()
    {
        // Format: PQ + YYMMDD + 4-digit sequence
        $date = now()->format('ymd');

        $lastQueue = OrderQueue::where('queue_number', 'like', "PQ{$date}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastQueue) {
            $lastNumber = (int) substr($lastQueue->queue_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "PQ{$date}{$newNumber}";
    }
}
