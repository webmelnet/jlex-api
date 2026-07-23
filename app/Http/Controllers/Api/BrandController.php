<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    protected $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index(Request $request)
    {
        $query = Brand::with('image')->orderBy('sort_order')->orderBy('id');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return response()->json($query->get());
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:brands,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('ids') as $i => $id) {
                Brand::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['message' => 'Reordered']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'size:3', 'unique:brands,code', 'regex:/^[A-Z0-9]{3}$/'],
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);


        $brand = $this->brandService->createBrand($validated);

        return response()->json([
            'status' => 'Brand created successfully',
            'brand'  => $brand,
        ], 201);
    }

    public function show(Brand $brand)
    {
        return response()->json($brand->load(['products', 'image']));
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'code'        => ['nullable', 'string', 'size:3', 'unique:brands,code,' . $brand->id, 'regex:/^[A-Z0-9]{3}$/'],
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $oldCode = $brand->code;


        $brand = $this->brandService->updateBrand($brand, $validated);

        // If brand code changed, update SKUs of all associated products
        if ($oldCode && $oldCode !== $brand->code) {
            $brand->products()->each(function ($product) use ($oldCode, $brand) {
                if ($product->sku && str_starts_with($product->sku, $oldCode . '-')) {
                    $product->update([
                        'sku' => $brand->code . '-' . substr($product->sku, strlen($oldCode) + 1),
                    ]);
                }
            });
        }

        return response()->json([
            'status' => 'Brand updated successfully',
            'brand'  => $brand,
        ]);
    }

    public function destroy(Brand $brand)
    {
        $this->brandService->deleteBrand($brand);
        return response()->json(null, 204);
    }

    public function restore($id)
    {
        $brand = Brand::withTrashed()->findOrFail($id);
        $brand->restore();
        return response()->json($brand->load('image'), 200);
    }

    public function forceDelete($id)
    {
        $brand = Brand::withTrashed()->findOrFail($id);
        $this->brandService->forceDeleteBrand($brand);
        return response()->json(null, 204);
    }

    public function trashedBrands()
    {
        $brands = Brand::onlyTrashed()->with('image')->get();
        return response()->json($brands);
    }

    public function removeImage(Brand $brand)
    {
        $this->brandService->removeImage($brand);
        return response()->json(['status' => 'Image removed successfully']);
    }
}