<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Exportable;

class ProductsExportCategory implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;
    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    } 
 
    public function query()
    {
        return $this->model::query()
            ->whereNull('new_tag_product')->whereNotIn('new_status_product', ['dump', 'expired', 'sale', 'migrate', 'repair']);
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
            'New Tag Product',
            'New Discount',
            'Display Price',
            'Days Since Created',
        ];
    }

    public function map($product): array
    {
        return [
            $product->code_document,
            $product->old_barcode_product,
            $product->new_barcode_product,
            $product->new_name_product,
            $product->new_quantity_product,
            $product->new_price_product,
            $product->old_price_product,
            $product->new_date_in_product,
            $product->new_status_product,
            $product->new_quality,
            $product->new_category_product,
            $product->new_tag_product,
            $product->new_discount,
            $product->display_price,
            $product->days_since_created,
        ];
    }

    /**
     * Chunk size per read operation
     */
    public function chunkSize(): int
    {
        return 1000;
    }
}