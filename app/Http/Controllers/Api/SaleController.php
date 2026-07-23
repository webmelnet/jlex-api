<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderQueue;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\S3UploadService;
use App\Services\OrderQueueService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    protected $saleService;
    protected $s3UploadService;
    protected $orderQueueService;

    public function __construct(SaleService $saleService, S3UploadService $s3UploadService, OrderQueueService $orderQueueService)
    {
        $this->saleService = $saleService;
        $this->s3UploadService = $s3UploadService;
        $this->orderQueueService = $orderQueueService;
    }

    public function index(Request $request)
    {
        $query = Sale::with(['items.product', 'customer', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by cashier
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->orderBy('sale_date', 'desc')->get();

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'nullable|string|unique:sales,invoice_number',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_type' => 'nullable|string|in:walk-in,phone-order',
            'sale_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'ewallet_reference' => 'required_if:payment_method,gcash|nullable|string',
            'ewallet_screenshot' => 'nullable|string',
            'status' => 'nullable|in:completed,pending,cancelled',
            'notes' => 'nullable|string',
            'order_queue_id' => 'nullable|exists:order_queues,id',
        ]);

        $orderQueueId = $validated['order_queue_id'] ?? null;
        unset($validated['order_queue_id']);

        $orderQueue = null;
        if ($orderQueueId) {
            $orderQueue = OrderQueue::findOrFail($orderQueueId);

            // Checked before the sale is created — a rejection here must never
            // leave a sale created / stock deducted against a rejected checkout.
            if ($orderQueue->claimed_by_user_id !== auth()->id()) {
                return response()->json([
                    'error' => 'This order is claimed by another cashier',
                ], 403);
            }
        }

        try {
            $sale = $this->saleService->createSale($validated);

            // Load relationships for the receipt
            $sale->load(['items.product', 'customer', 'user']);

            if ($orderQueue) {
                try {
                    $this->orderQueueService->completeQueueOrder($orderQueue, $sale);
                } catch (\Exception $e) {
                    // The sale already succeeded (payment taken, stock deducted);
                    // don't fail the response over a queue bookkeeping error.
                    report($e);
                }
            }

            return response()->json([
                'status' => 'Sale created successfully',
                'sale' => $sale // This is what we need for printing
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function uploadGcashScreenshot(Request $request)
    {
        $request->validate([
            'screenshot' => 'required|image|max:5120',
        ]);

        try {
            $result = $this->s3UploadService->uploadFile(
                $request->file('screenshot'),
                'gcash-screenshots'
            );

            return response()->json(['url' => $result['url']]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(Sale $sale)
    {
        return response()->json($sale->load(['items.product', 'customer', 'user']));
    }

    public function cancel(Sale $sale)
    {
        try {
            $sale = $this->saleService->cancelSale($sale);

            return response()->json([
                'status' => 'Sale cancelled successfully',
                'sale' => $sale->load(['items.product', 'customer', 'user'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $report = $this->saleService->getSalesReport(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($report);
    }

    public function todaySales()
    {
        $sales = Sale::today()
            ->completed()
            ->with(['items.product', 'customer', 'user'])
            ->get();

        // Calculate Total Sale (before discount) = subtotal + tax
        $totalSale = $sales->sum(function ($sale) {
            return $sale->subtotal + $sale->tax;
        });

        // Calculate Total Revenue (after discount) = total (which is subtotal + tax - discount)
        $totalRevenue = $sales->sum('total');

        // Calculate total discount applied
        $totalDiscount = $sales->sum('discount');

        return response()->json([
            'sales' => $sales,
            'count' => $sales->count(),
            'total_sale' => round($totalSale, 2), // Total before discount
            'total_discount' => round($totalDiscount, 2), // Total discount applied
            'total_revenue' => round($totalRevenue, 2), // Total after discount (actual revenue)
        ]);
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $sale = Sale::withTrashed()->findOrFail($id);
        $sale->restore();
        return response()->json($sale->load(['items.product', 'customer', 'user']), 200);
    }

    public function forceDelete($id)
    {
        $sale = Sale::withTrashed()->findOrFail($id);
        $sale->forceDelete();
        return response()->json(null, 204);
    }

    public function trashedSales()
    {
        $sales = Sale::onlyTrashed()->with(['items.product', 'customer', 'user'])->get();
        return response()->json($sales);
    }
}
