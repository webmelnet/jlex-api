<?php

namespace App\Services;

use App\Models\OrderQueue;
use App\Models\OrderQueueItem;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderQueueService
{
    public function createQueueOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            $orderQueue = $this->createWithRetry($data);

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

    private function createWithRetry(array $data)
    {
        $attempts = 0;

        while (true) {
            try {
                return OrderQueue::create([
                    'queue_number' => $this->generateQueueNumber(),
                    'customer_id' => $data['customer_id'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_type' => $data['customer_type'] ?? 'walk-in',
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

    public function claimQueueOrder(OrderQueue $orderQueue)
    {
        return DB::transaction(function () use ($orderQueue) {
            $locked = OrderQueue::where('id', $orderQueue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'queued') {
                throw new \Exception('This order is no longer available to claim');
            }

            $locked->claimed_by_user_id = auth()->id();
            $locked->claimed_at = now();
            $locked->status = 'claimed';
            $locked->save();

            return $locked->load(['items.product', 'customer', 'createdBy', 'claimedBy']);
        });
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
