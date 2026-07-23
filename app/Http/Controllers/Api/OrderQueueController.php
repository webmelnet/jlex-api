<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderQueue;
use App\Services\OrderQueueService;
use Illuminate\Http\Request;

class OrderQueueController extends Controller
{
    protected $orderQueueService;

    public function __construct(OrderQueueService $orderQueueService)
    {
        $this->orderQueueService = $orderQueueService;
    }

    public function index(Request $request)
    {
        $query = OrderQueue::with(['items.product', 'customer', 'createdBy', 'claimedBy', 'editingBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['queued', 'claimed']);
        }

        $orderQueues = $query->orderBy('created_at', 'asc')->get();

        return response()->json($orderQueues);
    }

    public function myHistory(Request $request)
    {
        $orderQueues = OrderQueue::with(['items.product', 'customer', 'createdBy', 'claimedBy'])
            ->where('created_by_user_id', auth()->id())
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orderQueues);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|in:walk-in,phone-order',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $orderQueue = $this->orderQueueService->createQueueOrder($validated);

            return response()->json([
                'status' => 'Order added to queue successfully',
                'order_queue' => $orderQueue,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(OrderQueue $orderQueue)
    {
        return response()->json(
            $orderQueue->load(['items.product', 'customer', 'createdBy', 'claimedBy', 'editingBy'])
        );
    }

    public function update(Request $request, OrderQueue $orderQueue)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|in:walk-in,phone-order',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $orderQueue = $this->orderQueueService->updateQueueOrder($orderQueue, $validated);

            return response()->json([
                'status' => 'Order updated successfully',
                'order_queue' => $orderQueue,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function startEdit(OrderQueue $orderQueue)
    {
        try {
            $orderQueue = $this->orderQueueService->startEditQueueOrder($orderQueue);

            return response()->json([
                'status' => 'Order locked for editing',
                'order_queue' => $orderQueue,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancelEdit(OrderQueue $orderQueue)
    {
        try {
            $orderQueue = $this->orderQueueService->cancelEditQueueOrder($orderQueue);

            return response()->json([
                'status' => 'Edit lock released',
                'order_queue' => $orderQueue,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function claim(OrderQueue $orderQueue)
    {
        try {
            $orderQueue = $this->orderQueueService->claimQueueOrder($orderQueue);

            return response()->json([
                'status' => 'Order claimed successfully',
                'order_queue' => $orderQueue,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function release(OrderQueue $orderQueue)
    {
        try {
            $orderQueue = $this->orderQueueService->releaseQueueOrder($orderQueue);

            return response()->json([
                'status' => 'Order released back to queue',
                'order_queue' => $orderQueue,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(OrderQueue $orderQueue)
    {
        try {
            $orderQueue = $this->orderQueueService->cancelQueueOrder($orderQueue);

            return response()->json([
                'status' => 'Order cancelled successfully',
                'order_queue' => $orderQueue,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
