<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\StockMovement;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading, SkipsEmptyRows
{
    private $categoryCache = [];
    private $brandCache = [];
    private $rowNumber = 1; // Start at 1 (header is row 0)

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->rowNumber++;

        try {
            // Get or create category
            $categoryId = $this->getCategoryId($row['parent_category'], $row['category']);

            // Get or create brand
            $brandId = $this->getBrandId($row['brand']);

            // Check if product exists (for update)
            $product = null;
            if (!empty($row['sku'])) {
                $product = Product::where('sku', $row['sku'])->first();
            }

            // Parse boolean values
            $isActive = $this->parseBoolean($row['active']);
            $trackInventory = true; // Default to true

            // Clean numeric values
            $cost = $this->cleanNumericValue($row['cost']);
            $price = $this->cleanNumericValue($row['price']);
            $stockQuantity = (int) ($row['stock_quantity'] ?? 0);
            $reorderLevel = (int) ($row['reorder_level'] ?? 0);

            $data = [
                'sku' => $row['sku'] ?: $this->generateSKU(),
                'barcode' => $row['barcode'] ?? null,
                'name' => $row['product_name'],
                'description' => $row['description'] ?? null,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'cost' => $cost,
                'price' => $price,
                'stock_quantity' => $stockQuantity,
                'reorder_level' => $reorderLevel,
                'unit' => $row['unit'] ?? 'pcs',
                'track_inventory' => $trackInventory,
                'is_active' => $isActive,
                'notes' => $row['notes'] ?? null,
            ];

            if ($product) {
                // Update existing product
                $oldStockQuantity = $product->stock_quantity;
                $product->update($data);

                // Track stock changes
                if ($oldStockQuantity != $stockQuantity) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'adjustment',
                        'quantity' => abs($stockQuantity - $oldStockQuantity),
                        'quantity_before' => $oldStockQuantity,
                        'quantity_after' => $stockQuantity,
                        'reference_type' => 'import_adjustment',
                        'user_id' => auth()->id(),
                        'notes' => 'Stock updated via import',
                    ]);
                }

                return null; // Don't create new, we updated existing
            } else {
                // Create new product
                return new Product($data);
            }

        } catch (\Exception $e) {
            Log::error("Error importing product at row {$this->rowNumber}: " . $e->getMessage(), [
                'row' => $row,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get or create category based on parent/child hierarchy
     */
    private function getCategoryId($parentCategoryName, $categoryName)
    {
        if (empty($categoryName)) {
            return null;
        }

        // Create cache key
        $cacheKey = trim($parentCategoryName) . '|' . trim($categoryName);

        // Check cache first
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        // If no parent category, find or create as top-level category
        if (empty($parentCategoryName)) {
            $category = Category::firstOrCreate(
                ['name' => trim($categoryName), 'parent_id' => null],
                [
                    'is_active' => true,
                    'description' => 'Auto-created from import'
                ]
            );

            $this->categoryCache[$cacheKey] = $category->id;
            return $category->id;
        }

        // Has parent category - find or create parent first
        $parentCategory = Category::firstOrCreate(
            ['name' => trim($parentCategoryName), 'parent_id' => null],
            [
                'is_active' => true,
                'description' => 'Auto-created parent from import'
            ]
        );

        // Then find or create child category
        $category = Category::firstOrCreate(
            [
                'name' => trim($categoryName),
                'parent_id' => $parentCategory->id
            ],
            [
                'is_active' => true,
                'description' => 'Auto-created from import'
            ]
        );

        $this->categoryCache[$cacheKey] = $category->id;
        return $category->id;
    }

    /**
     * Get or create brand
     */
    private function getBrandId($brandName)
    {
        if (empty($brandName)) {
            return null;
        }

        $brandName = trim($brandName);

        // Check cache first
        if (isset($this->brandCache[$brandName])) {
            return $this->brandCache[$brandName];
        }

        $brand = Brand::firstOrCreate(
            ['name' => $brandName],
            [
                'is_active' => true,
                'description' => 'Auto-created from import'
            ]
        );

        $this->brandCache[$brandName] = $brand->id;
        return $brand->id;
    }

    /**
     * Parse boolean values from various formats
     */
    private function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim($value));
        return in_array($value, ['yes', 'true', '1', 'active', 'y']);
    }

    /**
     * Clean numeric value (remove commas, etc.)
     */
    private function cleanNumericValue($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remove commas and any other non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        return (float) $cleaned;
    }

    /**
     * Generate SKU for products without one
     */
    private function generateSKU()
    {
        $lastProduct = Product::where('sku', 'like', 'DNS-%')
            ->orderByRaw('CAST(SUBSTRING(sku, 5) AS UNSIGNED) DESC')
            ->first();

        if (!$lastProduct) {
            return 'DNS-000001';
        }

        preg_match('/DNS-(\d+)/', $lastProduct->sku, $matches);
        if (!$matches) {
            return 'DNS-000001';
        }

        $lastNumber = (int) $matches[1];
        $nextNumber = $lastNumber + 1;

        return 'DNS-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'product_name' => 'required|string|max:255',
            'cost' => 'required',
            'price' => 'required',
            'stock_quantity' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Custom validation messages
     */
    public function customValidationMessages()
    {
        return [
            'product_name.required' => 'Product name is required (column: Product Name)',
            'cost.required' => 'Cost is required (column: Cost)',
            'price.required' => 'Price is required (column: Price)',
        ];
    }

    /**
     * Batch insert size
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Chunk reading size
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Map header row to snake_case
     */
    public function headingRow(): int
    {
        return 1;
    }
}