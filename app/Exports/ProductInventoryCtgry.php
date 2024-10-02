<?php

namespace App\Exports;

use App\Models\Bundle;
use App\Models\New_product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProductInventoryCtgry implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;

    protected $query; // Menyimpan query untuk filter

    public function __construct(Request $request)
    {
        $this->query = $request->input('q'); // Ambil input query
    }


    public function query()
    {
        $productQuery = New_product::select(
            'id',
            'new_barcode_product',
            'new_name_product',
            'new_category_product',
            'new_price_product',
            'created_at',
            'new_status_product',
            'display_price',
            'new_date_in_product'
        )
            ->whereNotNull('new_category_product')
            ->whereNotIn('new_status_product', ['repair', 'sale', 'migrate']);

        $bundleQuery = Bundle::select(
            'id',
            'barcode_bundle as new_barcode_product',
            'name_bundle as new_name_product',
            'category as new_category_product',
            'total_price_custom_bundle as new_price_product',
            'created_at',
            DB::raw("CASE WHEN product_status = 'not sale' THEN 'display' ELSE product_status END as new_status_product"),
            'total_price_custom_bundle as display_price',
            'created_at as new_date_in_product'
        )->where('total_price_custom_bundle', '>=', 100000);


       return $productQuery->union($bundleQuery)
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
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
            'created_at',
            'updated_at',
            'New Discount',
            'Display Price',
            'Days Since Created',
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
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
            $product->created_at,
            $product->updated_at,
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
        return 500;
    }
}
