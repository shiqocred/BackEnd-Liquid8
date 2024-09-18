<?php 
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\StagingProduct;

class ProductStagingsExport implements FromQuery, WithHeadings, WithChunkReading
{
    use Exportable;

    public function query()
    {
        return StagingProduct::whereNotNull('new_category_product')
            ->whereNotIn('new_status_product', ['repair', 'sale', 'migrate']);
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

    public function chunkSize(): int
    {
        return 1000;
    }
}

?>