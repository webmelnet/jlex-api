<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeaturedProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeaturedProductController extends Controller
{
    /**
     * GET /api/website/featured-products
     * Public — returns active featured products ordered by sort_order.
     */
    public function index()
    {
        $featured = FeaturedProduct::with([
            'product' => fn ($q) => $q->with(['category', 'brand', 'images'])->where('is_active', true),
        ])
            ->orderBy('sort_order')
            ->get()
            ->pluck('product')
            ->filter()
            ->values();

        return response()->json($featured);
    }

    /**
     * GET /api/website/featured-products/all
     * Authenticated — returns all featured product IDs and sort orders for the admin UI.
     */
    public function adminIndex()
    {
        $featured = FeaturedProduct::with([
            'product' => fn ($q) => $q->with(['category', 'brand', 'images']),
        ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($fp) => array_merge($fp->product->toArray(), ['sort_order' => $fp->sort_order]))
            ->filter()
            ->values();

        return response()->json($featured);
    }

    /**
     * POST /api/website/featured-products
     * Authenticated — replaces the full featured products list.
     *
     * Body: { product_ids: [3, 1, 7, ...] }  (order = sort_order)
     */
    public function sync(Request $request)
    {
        $request->validate([
            'product_ids'   => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $request->product_ids;

        DB::transaction(function () use ($ids) {
            FeaturedProduct::query()->delete();

            $rows = array_map(fn ($id, $idx) => [
                'product_id' => $id,
                'sort_order' => $idx,
                'created_at' => now(),
                'updated_at' => now(),
            ], $ids, array_keys($ids));

            FeaturedProduct::insert($rows);
        });

        return response()->json(['message' => 'Featured products updated successfully.']);
    }
}
