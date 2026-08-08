<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    protected ReturnService $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    /**
     * Typeahead search for a sale to return, by invoice number or
     * customer name. Returns a short list of candidates; the cashier
     * picks one and lookupSale() fetches the full preview for it.
     */
    public function searchSales(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $query = $request->input('query');

        $sales = Sale::with('customer')
            ->where('status', 'completed')
            ->where(function ($q) use ($query) {
                $q->where('invoice_number', 'like', "%{$query}%")
                    ->orWhereHas('customer', function ($cq) use ($query) {
                        $cq->whereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$query}%"]);
                    });
            })
            ->orderBy('sale_date', 'desc')
            ->limit(10)
            ->get(['id', 'invoice_number', 'sale_date', 'total', 'status', 'customer_id']);

        return response()->json($sales);
    }

    /**
     * Fetch a sale by invoice number so the cashier can see its items,
     * remaining returnable quantities, and any return-policy warnings
     * before picking what to return.
     */
    public function lookupSale(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string',
        ]);

        $sale = Sale::with('items.product')
            ->where('invoice_number', $request->invoice_number)
            ->first();

        if (!$sale) {
            return response()->json(['error' => 'Invoice not found.'], 404);
        }

        if ($sale->status === 'cancelled') {
            return response()->json(['error' => 'This sale has been cancelled and cannot be returned.'], 422);
        }

        if ($sale->status === 'refunded') {
            return response()->json(['error' => 'This sale has already been fully refunded.'], 422);
        }

        return response()->json($this->returnService->previewSale($sale));
    }

    /**
     * Process a return.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id'                     => 'required|exists:sales,id',
            'items'                       => 'required|array|min:1',
            'items.*.sale_item_id'        => 'required|exists:sale_items,id',
            'items.*.quantity'            => 'required|integer|min:1',
            'notes'                       => 'nullable|string',
            'acknowledge_policy_warnings' => 'nullable|boolean',
        ]);

        try {
            $return = $this->returnService->processReturn($validated);

            return response()->json([
                'status' => 'Return processed successfully.',
                'return' => $return,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * List returns (optionally filtered by sale / date range).
     */
    public function index(Request $request)
    {
        $query = SaleReturn::with(['items.product', 'sale', 'user'])
            ->orderBy('return_date', 'desc');

        if ($request->has('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('return_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('return_date', '<=', $request->end_date);
        }

        return response()->json($query->get());
    }

    /**
     * Show a single return.
     */
    public function show(SaleReturn $return)
    {
        return response()->json(
            $return->load(['items.product', 'sale.items.product', 'user'])
        );
    }
}
