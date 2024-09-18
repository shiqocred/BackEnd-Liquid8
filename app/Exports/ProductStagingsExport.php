<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\StagingProduct;
use Maatwebsite\Excel\Concerns\FromCollection;

class ProductStagingsExport implements FromCollection, WithHeadings
{
    use Exportable;

    public function collection()
    {
        return StagingProduct::whereNotNull('new_category_product')
            ->whereNotIn('new_status_product', ['repair', 'sale', 'migrate'])->get();
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
            'Created At',
            'Updated At'
        ];
    }
}
