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
    private $brandCache = []; // brand name => ['id' => .., 'code' => ..]
    private $skuCounters = []; // sku prefix => last used number
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

            // Get or create brand (new brands get an auto-generated 3-letter code)
            $brand = $this->getBrand($row['brand']);
            $brandId = $brand['id'] ?? null;
            $skuPrefix = $brand['code'] ?? 'JLX';

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
            $reorderLevel = (int) ($row['reorder_level'] ?? 0);

            // Only accept a valid, non-negative integer for stock_quantity.
            // Invalid values (blank, non-numeric, negative) skip the stock update entirely.
            $rawStockQuantity = $row['stock_quantity'] ?? null;
            $stockQuantity = null;
            if ($rawStockQuantity !== null && $rawStockQuantity !== '' && is_numeric($rawStockQuantity) && (int) $rawStockQuantity >= 0) {
                $stockQuantity = (int) $rawStockQuantity;
            } elseif ($rawStockQuantity !== null && $rawStockQuantity !== '') {
                Log::warning("Invalid stock_quantity '{$rawStockQuantity}' at row {$this->rowNumber}, skipping stock update");
            }

            $data = [
                'sku' => $row['sku'] ?: $this->generateSKU($skuPrefix),
                'barcode' => $row['barcode'] ?? null,
                'name' => $row['product_name'],
                'description' => $row['description'] ?? null,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'cost' => $cost,
                'price' => $price,
                'reorder_level' => $reorderLevel,
                'unit' => $row['unit'] ?? 'pcs',
                'track_inventory' => $trackInventory,
                'is_active' => $isActive,
                'notes' => $row['notes'] ?? null,
            ];

            if ($product) {
                // Update existing product
                $oldStockQuantity = $product->stock_quantity;
                if ($stockQuantity !== null) {
                    $data['stock_quantity'] = $stockQuantity;
                }
                $product->update($data);

                // Track stock changes
                if ($stockQuantity !== null && $oldStockQuantity != $stockQuantity) {
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
                // Create new product (default stock to 0 when the imported value was invalid)
                $data['stock_quantity'] = $stockQuantity ?? 0;
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
     * Get or create brand. New brands are created with an auto-generated
     * 3-letter code so imported products get a brand-based SKU prefix.
     *
     * @return array{id: int, code: string}|null
     */
    private function getBrand($brandName)
    {
        if (empty($brandName)) {
            return null;
        }

        $brandName = trim($brandName);

        // Check cache first
        if (isset($this->brandCache[$brandName])) {
            return $this->brandCache[$brandName];
        }

        $brand = Brand::where('name', $brandName)->first();

        if (!$brand) {
            $brand = Brand::create([
                'name' => $brandName,
                'code' => $this->generateBrandCode($brandName),
                'is_active' => true,
                'description' => 'Auto-created from import',
            ]);
        }

        $info = [
            'id' => $brand->id,
            'code' => $brand->code ? strtoupper($brand->code) : 'JLX',
        ];

        $this->brandCache[$brandName] = $info;
        return $info;
    }

    /**
     * Generate a unique 3-letter brand code from the brand name, e.g.
     * "Coca Cola Company" -> "CCC", "Nike" -> "NIK", "3M" -> "3MX".
     */
    private function generateBrandCode(string $brandName): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($brandName)),
            fn($w) => preg_replace('/[^A-Za-z0-9]/', '', $w) !== ''
        ));

        $letters = preg_replace('/[^A-Za-z0-9]/', '', $brandName);

        if (count($words) >= 3) {
            $code = strtoupper($words[0][0] . $words[1][0] . $words[2][0]);
        } elseif (count($words) === 2) {
            $code = strtoupper($words[0][0] . substr(preg_replace('/[^A-Za-z0-9]/', '', $words[1]), 0, 2));
        } else {
            $code = strtoupper(substr($letters, 0, 3));
        }

        $code = str_pad($code, 3, 'X');

        if (!Brand::withTrashed()->where('code', $code)->exists()) {
            return $code;
        }

        // Collision: try successive letters from the brand name
        for ($i = 3; $i < strlen($letters); $i++) {
            $candidate = strtoupper(substr($letters, 0, 2) . $letters[$i]);
            if (!Brand::withTrashed()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Final fallback: replace the last character with a digit
        for ($n = 1; $n <= 9; $n++) {
            $candidate = substr($code, 0, 2) . $n;
            if (!Brand::withTrashed()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return strtoupper(substr(md5($brandName . microtime()), 0, 3));
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
     * Generate the next SKU for a given brand prefix (e.g. "NKE-000042").
     * Falls back to the 'JLX' prefix when a product has no brand.
     */
    private function generateSKU(string $prefix = 'JLX'): string
    {
        if (!isset($this->skuCounters[$prefix])) {
            $this->skuCounters[$prefix] = $this->getLastSkuNumber($prefix);
        }

        $this->skuCounters[$prefix]++;

        return $prefix . '-' . str_pad($this->skuCounters[$prefix], 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the last sequential number used for a given SKU prefix.
     */
    private function getLastSkuNumber(string $prefix): int
    {
        $lastProduct = Product::withTrashed()->where('sku', 'like', $prefix . '-%')
            ->orderByRaw('CAST(SUBSTRING(sku, ' . (strlen($prefix) + 2) . ') AS UNSIGNED) DESC')
            ->first();

        if (!$lastProduct) {
            return 0;
        }

        preg_match('/' . preg_quote($prefix, '/') . '-(\d+)/', $lastProduct->sku, $matches);

        return $matches ? (int) $matches[1] : 0;
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
            'stock_quantity' => 'nullable',
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