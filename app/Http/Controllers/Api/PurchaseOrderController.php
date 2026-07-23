<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    protected $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['items.product', 'supplier', 'user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('order_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('order_date', '<=', $request->end_date);
        }

        $purchaseOrders = $query->orderBy('order_date', 'desc')->get();

        return response()->json($purchaseOrders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'po_number' => 'nullable|string|unique:purchase_orders,po_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'status' => 'nullable|in:pending,ordered,partial,received,cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.cost' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $po = $this->purchaseOrderService->createPurchaseOrder($validated);

            return response()->json([
                'status' => 'Purchase order created successfully',
                'purchase_order' => $po
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json($purchaseOrder->load(['items.product', 'supplier', 'user']));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'status' => 'nullable|in:pending,ordered,partial,received,cancelled',
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.cost' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $po = $this->purchaseOrderService->updatePurchaseOrder($purchaseOrder, $validated);

            return response()->json([
                'status' => 'Purchase order updated successfully',
                'purchase_order' => $po
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function receiveItems(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_received' => 'required|integer|min:1',
        ]);

        try {
            $po = $this->purchaseOrderService->receiveItems($purchaseOrder, $validated['items']);

            return response()->json([
                'status' => 'Items received successfully',
                'purchase_order' => $po
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        try {
            $po = $this->purchaseOrderService->cancelPurchaseOrder($purchaseOrder);

            return response()->json([
                'status' => 'Purchase order cancelled successfully',
                'purchase_order' => $po
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $po = PurchaseOrder::withTrashed()->findOrFail($id);
        $po->restore();
        return response()->json($po->load(['items.product', 'supplier', 'user']), 200);
    }

    public function forceDelete($id)
    {
        $po = PurchaseOrder::withTrashed()->findOrFail($id);
        $po->forceDelete();
        return response()->json(null, 204);
    }

    public function trashedPurchaseOrders()
    {
        $pos = PurchaseOrder::onlyTrashed()->with(['items.product', 'supplier', 'user'])->get();
        return response()->json($pos);
    }
}
