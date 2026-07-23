<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Product::with(['category.parent', 'brand'])
            ->orderBy('sku')
            ->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'SKU',
            'Barcode',
            'Product Name',
            'Description',
            'Parent Category',
            'Category',
            'Brand',
            'Cost',
            'Price',
            'Stock Quantity',
            'Unit',
            'Reorder Level',
            'Stock Status',
            'Active',
            'Notes',
        ];
    }

    /**
     * Map the data for each row
     */
    public function map($product): array
    {
        // Determine stock status
        $stockStatus = 'In Stock';
        if ($product->is_out_of_stock) {
            $stockStatus = 'Out of Stock';
        } elseif ($product->is_low_stock) {
            $stockStatus = 'Low Stock';
        }

        // Handle parent/child category hierarchy
        $parentCategory = '';
        $category = '';
        
        if ($product->category) {
            if ($product->category->parent_id && $product->category->parent) {
                // Has a parent category
                $parentCategory = $product->category->parent->name;
                $category = $product->category->name;
            } else {
                // Top-level category (no parent)
                $parentCategory = '';
                $category = $product->category->name;
            }
        }

        return [
            $product->sku,
            $product->barcode ?? '',
            $product->name,
            $product->description ?? '',
            $parentCategory,
            $category,
            $product->brand?->name ?? '',
            number_format($product->cost, 2),
            number_format($product->price, 2),
            $product->stock_quantity,
            $product->unit ?? 'pcs',
            $product->reorder_level,
            $stockStatus,
            $product->is_active ? 'Yes' : 'No',
            $product->notes ?? '',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ],
        ];
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // SKU
            'B' => 15, // Barcode
            'C' => 30, // Product Name
            'D' => 40, // Description
            'E' => 20, // Parent Category
            'F' => 20, // Category
            'G' => 20, // Brand
            'H' => 12, // Cost
            'I' => 12, // Price
            'J' => 15, // Stock Quantity
            'K' => 10, // Unit
            'L' => 15, // Reorder Level
            'M' => 15, // Stock Status
            'N' => 10, // Active
            'O' => 30, // Notes
        ];
    }
}