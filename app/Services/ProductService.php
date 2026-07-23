<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Brand;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ProductService
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function createProduct(array $data)
    {
        DB::beginTransaction();
        try {
            // Extract image data before creating product
            $singleImage = $data['image'] ?? null;
            $multipleImages = $data['images'] ?? [];
            unset($data['image'], $data['images']);

            // Generate SKU if not provided
            if (!isset($data['sku']) || empty($data['sku'])) {
                $data['sku'] = $this->generateSKU($data['brand_id'] ?? null);
            }

            // Create product
            $product = Product::create(attributes: $data);

            // Handle single image upload
            if ($singleImage && is_file($singleImage)) {
                $this->imageService->uploadAndAttach(
                    $product,
                    $singleImage,
                    true, // Set as primary
                    null, // alt_text
                    'products'
                );
            }

            // Handle multiple images upload
            if (!empty($multipleImages) && is_array($multipleImages)) {
                $validFiles = array_filter($multipleImages, fn($img) => is_file($img));
                
                if (!empty($validFiles)) {
                    $this->imageService->uploadAndAttachMultiple(
                        $product,
                        $validFiles,
                        !$singleImage // First is primary only if no single image was uploaded
                    );
                }
            }

            // Create initial stock movement if stock_quantity > 0
            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $product->stock_quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $product->stock_quantity,
                    'reference_type' => 'initial_stock',
                    'user_id' => auth()->id(),
                    'notes' => 'Initial stock entry',
                ]);
            }

            DB::commit();
            return $product->load(['category', 'brand', 'images']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createBulkProducts(array $productsData)
    {
        $createdProducts = [];

        // Pre-load brand codes to avoid N+1 queries
        $brandIds = array_unique(array_filter(array_column($productsData, 'brand_id')));
        $brandCodes = Brand::whereIn('id', $brandIds)->pluck('code', 'id')->toArray();

        // Track SKU counters per brand prefix to avoid repeated DB hits
        $skuCounters = [];

        DB::beginTransaction();
        try {
            foreach ($productsData as $data) {
                // Generate SKU if not provided
                if (!isset($data['sku']) || empty($data['sku'])) {
                    $brandId = !empty($data['brand_id']) ? $data['brand_id'] : null;
                    $prefix = ($brandId && isset($brandCodes[$brandId]))
                        ? strtoupper($brandCodes[$brandId])
                        : 'JLX';

                    if (!isset($skuCounters[$prefix])) {
                        $skuCounters[$prefix] = $this->getLastSkuNumber($prefix);
                    }

                    $skuCounters[$prefix]++;
                    $data['sku'] = $prefix . '-' . str_pad($skuCounters[$prefix], 6, '0', STR_PAD_LEFT);
                }

                // Set default values
                $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
                $data['reorder_level'] = $data['reorder_level'] ?? 0;
                $data['unit'] = $data['unit'] ?? 'pcs';
                $data['track_inventory'] = $data['track_inventory'] ?? true;
                $data['is_active'] = $data['is_active'] ?? true;
                $data['category_id'] = !empty($data['category_id']) ? $data['category_id'] : null;
                $data['brand_id'] = !empty($data['brand_id']) ? $data['brand_id'] : null;

                // Create the product (no images in bulk for now)
                $product = Product::create($data);

                // Create initial stock movement if stock_quantity > 0
                if ($product->stock_quantity > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'adjustment',
                        'quantity' => $product->stock_quantity,
                        'quantity_before' => 0,
                        'quantity_after' => $product->stock_quantity,
                        'reference_type' => 'bulk_initial_stock',
                        'user_id' => auth()->id(),
                        'notes' => 'Bulk import initial stock entry',
                    ]);
                }

                $createdProducts[] = $product->load(['category', 'brand', 'images']);
            }

            DB::commit();
            return $createdProducts;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateProduct(Product $product, array $data)
    {
        DB::beginTransaction();
        try {
            $oldStockQuantity = $product->stock_quantity;

            // Extract image data
            $singleImage = $data['image'] ?? null;
            $multipleImages = $data['images'] ?? [];
            unset($data['image'], $data['images']);

            // If brand changed and no explicit SKU provided, regenerate SKU
            if (
                isset($data['brand_id']) &&
                $data['brand_id'] != $product->brand_id &&
                (!isset($data['sku']) || empty($data['sku']))
            ) {
                $data['sku'] = $this->generateSKU($data['brand_id']);
            }

            // Update product data
            $product->update($data);

            // Handle single image replacement (replaces primary image)
            if ($singleImage && is_file($singleImage)) {
                $this->imageService->replacePrimaryImage($product, $singleImage, null, 'products');
            }

            // Handle multiple images (add to existing images)
            if (!empty($multipleImages) && is_array($multipleImages)) {
                $validFiles = array_filter($multipleImages, fn($img) => is_file($img));
                
                if (!empty($validFiles)) {
                    $this->imageService->uploadAndAttachMultiple(
                        $product,
                        $validFiles,
                        !$product->primaryImage // First is primary if no primary exists
                    );
                }
            }

            // Track stock changes
            if (isset($data['stock_quantity']) && $oldStockQuantity != $data['stock_quantity']) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => abs($data['stock_quantity'] - $oldStockQuantity),
                    'quantity_before' => $oldStockQuantity,
                    'quantity_after' => $data['stock_quantity'],
                    'reference_type' => 'manual_adjustment',
                    'user_id' => auth()->id(),
                    'notes' => 'Stock updated via product edit',
                ]);
            }

            DB::commit();
            return $product->fresh(['category', 'brand', 'images']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteProduct(Product $product)
    {
        DB::beginTransaction();
        try {
            $product->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function forceDeleteProduct(Product $product)
    {
        DB::beginTransaction();
        try {
            if (!$product->relationLoaded('images')) {
                $product->load('images');
            }

            if ($product->images && $product->images->count() > 0) {
                $this->imageService->deleteAllModelImages($product);
            }

            $product->forceDelete();
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getLowStockProducts()
    {
        return Product::lowStock()
            ->with(['category', 'brand', 'images'])
            ->get();
    }

    public function getOutOfStockProducts()
    {
        return Product::outOfStock()
            ->with(['category', 'brand', 'images'])
            ->get();
    }

    public function searchProducts($query)
    {
        return Product::where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->orWhere('barcode', 'like', "%{$query}%")
            ->with(['category', 'brand', 'images'])
            ->get();
    }

    /**
     * Get the next SKU for a given brand or default prefix.
     * This method generates the NEXT sequential SKU (not the last one).
     * 
     * @param int|null $brandId Brand ID to generate SKU for. If null, uses 'JLX' prefix
     * @return string The next SKU in format {PREFIX}-{6-digit-number} e.g. NKE-000042
     */
    public function getNextSku(?int $brandId = null): string
    {
        $prefix = 'JLX';

        if ($brandId) {
            $brand = Brand::find($brandId);
            if ($brand && $brand->code) {
                $prefix = strtoupper($brand->code);
            }
        }

        $nextNumber = $this->getLastSkuNumber($prefix) + 1;

        return $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the last SKU for a given brand (by brand_id), or for the default 'JLX' prefix.
     * This returns the actual last SKU that exists in the database.
     *
     * @param int|null $brandId Brand ID. If null, uses 'JLX' prefix
     * @return string|null The last SKU found, or null if none exist
     */
    public function getLastSkuForBrand(?int $brandId = null): ?string
    {
        $prefix = 'JLX';

        if ($brandId) {
            $brand = Brand::find($brandId);
            if ($brand && $brand->code) {
                $prefix = strtoupper($brand->code);
            }
        }

        $lastProduct = Product::withTrashed()->where('sku', 'like', $prefix . '-%')
            ->orderByRaw('CAST(SUBSTRING(sku, ' . (strlen($prefix) + 2) . ') AS UNSIGNED) DESC')
            ->first();

        return $lastProduct ? $lastProduct->sku : null;
    }

    /**
     * Add image to product
     */
    public function addImage(Product $product, $file, bool $isPrimary = false, ?string $altText = null)
    {
        return $this->imageService->uploadAndAttach($product, $file, $isPrimary, $altText, 'products');
    }

    /**
     * Remove image from product
     */
    public function removeImage(Product $product, int $imageId)
    {
        $image = $product->images()->find($imageId);
        
        if ($image) {
            return $this->imageService->deleteImage($image);
        }

        return false;
    }

    /**
     * Set primary image for product
     */
    public function setPrimaryImage(Product $product, int $imageId)
    {
        $image = $product->images()->find($imageId);
        
        if ($image) {
            return $this->imageService->setAsPrimary($image);
        }

        return null;
    }

    /**
     * Reorder product images
     */
    public function reorderImages(Product $product, array $imageIds)
    {
        $productImageIds = $product->images->pluck('id')->toArray();
        $validImageIds = array_intersect($imageIds, $productImageIds);

        return $this->imageService->reorder($validImageIds);
    }

    /**
     * Generate a SKU using the brand's 3-character code as prefix.
     * Falls back to 'JLX' if no brand or brand has no code.
     *
     * Format: {PREFIX}-{6-digit-number}  e.g. NKE-000042
     *
     * @param int|null $brandId Brand ID to generate SKU for
     * @return string The generated SKU
     */
    private function generateSKU(?int $brandId = null): string
    {
        $prefix = 'JLX';

        if ($brandId) {
            $brand = Brand::find($brandId);
            if ($brand && $brand->code) {
                $prefix = strtoupper($brand->code);
            }
        }

        $nextNumber = $this->getLastSkuNumber($prefix) + 1;

        return $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the last sequential number used for a given SKU prefix.
     * For example, if prefix is 'NKE' and last SKU is 'NKE-000042', returns 42.
     * 
     * @param string $prefix The SKU prefix (e.g., 'NKE', 'JLX')
     * @return int The last sequential number, or 0 if no products with this prefix exist
     */
    private function getLastSkuNumber(string $prefix): int
    {
        $prefixLength = strlen($prefix) + 1; // +1 for the dash

        $lastProduct = Product::withTrashed()->where('sku', 'like', $prefix . '-%')
            ->orderByRaw('CAST(SUBSTRING(sku, ' . ($prefixLength + 1) . ') AS UNSIGNED) DESC')
            ->first();

        if (!$lastProduct) {
            return 0;
        }

        preg_match('/' . preg_quote($prefix, '/') . '-(\d+)/', $lastProduct->sku, $matches);

        return $matches ? (int) $matches[1] : 0;
    }
}