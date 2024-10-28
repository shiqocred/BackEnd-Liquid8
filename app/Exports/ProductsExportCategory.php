<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExportCategory implements FromCollection, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;

    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function collection()
    {
        return collect($this->products);
    }

    public function headings(): array
    {
        return [
            'Code Document',
            'Old Barcode Product',
            'New Barcode Product',
            'New Name Product',
            'New Quantity Product',
            'New Price Product',
            'Old Price Product',
            'New Date In Product',
            'New Status Product',
            'New Quality',
            'New Category Product',
            'New Discount',
            'Display Price',
            'Days Since Created',
        ];
    }

    public function map($product): array
    {
        return [
            $product['code_document'],
            $product['old_barcode_product'],
            $product['new_barcode_product'],
            $product['new_name_product'],
            $product['new_quantity_product'],
            $product['new_price_product'],
            $product['old_price_product'],
            $product['new_date_in_product'],
            $product['new_status_product'],
            $product['new_quality'],
            $product['new_category_product'],
            $product['new_discount'],
            $product['display_price'],
            $product['days_since_created'],
        ];
    }

    public function chunkSize(): int
    {
        return 1000; // Ukuran chunk untuk pemrosesan
    }
}
