<?php

namespace App\Exports;

use App\Models\StagingProduct;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;

class ProductStagingsExport implements FromQuery, WithHeadings
{
    use Exportable;

    public function query()
    {
        return StagingProduct::query()
        ->whereNull('new_tag_product');
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
            'Days Since Created'
        ];
    }
}
