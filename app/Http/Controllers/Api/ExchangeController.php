<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exchange;
use App\Models\Sale;
use App\Services\ExchangeService;
use Illuminate\Http\Request;

class ExchangeController extends Controller
{
    protected ExchangeService $exchangeService;

    public function __construct(ExchangeService $exchangeService)
    {
        $this->exchangeService = $exchangeService;
    }

    /**
     * Fetch a sale by invoice number or ID so the cashier can
     * see its items before picking which ones to return.
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
            return response()->json(['error' => 'This sale has been cancelled and cannot be used for an exchange.'], 422);
        }

        return response()->json($sale);
    }

    /**
     * Process an exchange.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'original_sale_id'               => 'required|exists:sales,id',
            'returned_items'                 => 'required|array|min:1',
            'returned_items.*.sale_item_id'  => 'nullable|exists:sale_items,id',
            'returned_items.*.product_id'    => 'required|exists:products,id',
            'returned_items.*.quantity'      => 'required|integer|min:1',
            'returned_items.*.price'         => 'required|numeric|min:0',
            'replacement_items'              => 'required|array|min:1',
            'replacement_items.*.product_id' => 'required|exists:products,id',
            'replacement_items.*.quantity'   => 'required|integer|min:1',
            'replacement_items.*.price'      => 'required|numeric|min:0',
            'amount_paid'                    => 'nullable|numeric|min:0',
            'payment_method'                 => 'nullable|string',
            'ewallet_reference'              => 'nullable|string',
            'notes'                          => 'nullable|string',
        ]);

        try {
            $exchange = $this->exchangeService->processExchange($validated);

            return response()->json([
                'status'   => 'Exchange processed successfully.',
                'exchange' => $exchange,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * List exchanges (optionally filtered by sale).
     */
    public function index(Request $request)
    {
        $query = Exchange::with(['items.product', 'originalSale', 'user'])
            ->orderBy('exchange_date', 'desc');

        if ($request->has('original_sale_id')) {
            $query->where('original_sale_id', $request->original_sale_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('exchange_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('exchange_date', '<=', $request->end_date);
        }

        return response()->json($query->get());
    }

    /**
     * Show a single exchange.
     */
    public function show(Exchange $exchange)
    {
        return response()->json(
            $exchange->load(['items.product', 'originalSale.items.product', 'user'])
        );
    }
}
