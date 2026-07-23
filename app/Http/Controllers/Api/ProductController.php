<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ProductService;
use App\Exports\ProductsExport;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('category_id')) {
            $categoryIds = [$request->category_id];
            $category = Category::with('children')->find($request->category_id);
            if ($category && $category->children->isNotEmpty()) {
                $categoryIds = array_merge($categoryIds, $category->children->pluck('id')->toArray());
            }
            $query->whereIn('category_id', $categoryIds);
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->has('stock_status')) {
            if ($request->stock_status === 'low') {
                $query->lowStock();
            } elseif ($request->stock_status === 'out') {
                $query->outOfStock();
            }
        }

        $products = $query->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_mode' => 'nullable|in:manual,scheduled,stock',
            'sale_start_at' => 'nullable|date|required_if:sale_mode,scheduled',
            'sale_end_at' => 'nullable|date|after:sale_start_at|required_if:sale_mode,scheduled',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'images.*' => 'nullable|image|max:2048',
            'track_inventory' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'stock_verified' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'expiration_date' => 'nullable|date',
        ]);

        $product = $this->productService->createProduct($validated);

        return response()->json([
            'status' => 'Product created successfully',
            'product' => $product->load(['category', 'brand'])
        ], 201);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:255',
            'products.*.cost' => 'required|numeric|min:0',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.sku' => 'nullable|string',
            'products.*.barcode' => 'nullable|string',
            'products.*.description' => 'nullable|string',
            'products.*.category_id' => 'nullable|exists:categories,id',
            'products.*.brand_id' => 'nullable|exists:brands,id',
            'products.*.stock_quantity' => 'nullable|integer|min:0',
            'products.*.reorder_level' => 'nullable|integer|min:0',
            'products.*.unit' => 'nullable|string',
            'products.*.track_inventory' => 'nullable|boolean',
            'products.*.is_active' => 'nullable|boolean',
            'products.*.notes' => 'nullable|string',
        ]);

        $products = $request->input('products');
        
        $skus = array_filter(array_column($products, 'sku'));
        $barcodes = array_filter(array_column($products, 'barcode'));
        
        if (count($skus) !== count(array_unique($skus))) {
            return response()->json([
                'message' => 'Duplicate SKUs found in the batch',
                'errors' => ['products' => ['Each SKU must be unique within the batch']]
            ], 422);
        }
        
        if (count($barcodes) !== count(array_unique($barcodes))) {
            return response()->json([
                'message' => 'Duplicate barcodes found in the batch',
                'errors' => ['products' => ['Each barcode must be unique within the batch']]
            ], 422);
        }
        
        if (!empty($skus)) {
            $existingSkus = Product::whereIn('sku', $skus)->pluck('sku')->toArray();
            if (!empty($existingSkus)) {
                return response()->json([
                    'message' => 'Some SKUs already exist in the database',
                    'errors' => ['products' => ['SKUs already exist: ' . implode(', ', $existingSkus)]]
                ], 422);
            }
        }
        
        if (!empty($barcodes)) {
            $existingBarcodes = Product::whereIn('barcode', $barcodes)->pluck('barcode')->toArray();
            if (!empty($existingBarcodes)) {
                return response()->json([
                    'message' => 'Some barcodes already exist in the database',
                    'errors' => ['products' => ['Barcodes already exist: ' . implode(', ', $existingBarcodes)]]
                ], 422);
            }
        }

        try {
            DB::beginTransaction();
            
            $createdProducts = $this->productService->createBulkProducts($products);
            
            DB::commit();

            return response()->json([
                'message' => 'Successfully created ' . count($createdProducts) . ' products',
                'products' => $createdProducts,
                'count' => count($createdProducts)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error creating products: ' . $e->getMessage(),
                'errors' => ['products' => [$e->getMessage()]]
            ], 422);
        }
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'brand', 'images']));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_mode' => 'nullable|in:manual,scheduled,stock',
            'sale_start_at' => 'nullable|date|required_if:sale_mode,scheduled',
            'sale_end_at' => 'nullable|date|after:sale_start_at|required_if:sale_mode,scheduled',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'images.*' => 'nullable|image|max:2048',
            'track_inventory' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'stock_verified' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'expiration_date' => 'nullable|date',
        ]);

        $product = $this->productService->updateProduct($product, $validated);

        return response()->json([
            'status' => 'Product updated successfully',
            'product' => $product->load(['category', 'brand'])
        ]);
    }

    public function destroy(Product $product)
    {
        $this->productService->deleteProduct($product);
        return response()->json(null, 204);
    }

    public function lowStock()
    {
        $products = $this->productService->getLowStockProducts();
        return response()->json($products);
    }

    public function outOfStock()
    {
        $products = $this->productService->getOutOfStockProducts();
        return response()->json($products);
    }

    public function nearExpiration(Request $request)
    {
        $days = $request->input('days', 30);
        $products = Product::with(['category', 'brand', 'primaryImage'])
            ->nearExpiration($days)
            ->orderBy('expiration_date')
            ->get();
        return response()->json($products);
    }

    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $products = $this->productService->searchProducts($request->input('query'));
        return response()->json($products);
    }

    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return response()->json($product->load(['category', 'brand']), 200);
    }

    public function forceDelete($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->load('images');
        $this->productService->forceDeleteProduct($product);
        return response()->json(null, 204);
    }

    public function trashedProducts()
    {
        $products = Product::onlyTrashed()->with(['category', 'brand'])->get();
        return response()->json($products);
    }

    /**
     * Get the next SKU for a given brand or default prefix
     * 
     * Query parameters:
     * - brand_id: (optional) Brand ID to generate SKU for specific brand. If not provided, uses 'DNS' prefix
     * 
     * Returns the next sequential SKU based on the brand's 3-character code
     * Format: {PREFIX}-{6-digit-number}  e.g. NKE-000042
     */
    public function getLastSku(Request $request)
    {
        // Accept an optional brand_id to get the last SKU for a specific brand prefix
        $brandId = $request->query('brand_id');
        $nextSku = $this->productService->getNextSku($brandId);
        
        return response()->json([
            'sku' => $nextSku,
            'brand_id' => $brandId
        ]);
    }

    public function export()
    {
        return Excel::download(new ProductsExport, 'products_' . date('Y-m-d_His') . '.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $import = new ProductsImport();
            
            Excel::import($import, $file);

            $newProducts = Product::whereDoesntHave('stockMovements')
                ->where('stock_quantity', '>', 0)
                ->get();

            foreach ($newProducts as $product) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $product->stock_quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $product->stock_quantity,
                    'reference_type' => 'import_initial_stock',
                    'user_id' => auth()->id(),
                    'notes' => 'Initial stock from import',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Products imported successfully',
                'success' => true
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            DB::rollBack();
            
            $failures = $e->failures();
            $errors = [];
            
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }
            
            return response()->json([
                'message' => 'Validation errors in import file',
                'errors' => $errors,
                'success' => false
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error importing products: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}