<?php
namespace App\Jobs;

use App\Exports\ProductsExportCategory;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;
    protected $filePath;

    /**
     * Create a new job instance.
     *
     * @param string $model
     * @param string $filePath
     */
    public function __construct($model, $filePath)
    {
        $this->model = $model;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cacheKey = 'products_export_' . $this->model;
        $ttl = 60 * 60; // Cache untuk 1 jam

        // Cek apakah data sudah ada di Redis
        $cachedData = Redis::get($cacheKey);

        if ($cachedData) {
            // Data ditemukan di Redis, decode dan simpan ke koleksi
            $products = collect(json_decode($cachedData, true));
        } else {
            // Jika belum ada di Redis, lakukan query dan proses chunking
            $products = $this->model::query()
                ->whereNull('new_tag_product')
                ->whereNotIn('new_status_product', ['dump', 'expired', 'sale', 'migrate', 'repair'])
                ->chunk(1000, function ($chunk) use ($cacheKey, $ttl) {
                    $mappedProducts = $chunk->map(function ($product) {
                        return $this->mapProduct($product);
                    })->toArray();

                    // Simpan hasil ke Redis
                    Redis::setex($cacheKey, $ttl, json_encode($mappedProducts));

                    // Proses data dengan Excel export
                    Excel::store(new ProductsExportCategory($mappedProducts), $this->filePath);
                });
        }
    }

    /**
     * Method to map product fields for export.
     *
     * @param $product
     * @return array
     */
    protected function mapProduct($product)
    {
        return [
            'code_document' => $product->code_document,
            'old_barcode_product' => $product->old_barcode_product,
            'new_barcode_product' => $product->new_barcode_product,
            'new_name_product' => $product->new_name_product,
            'new_quantity_product' => $product->new_quantity_product,
            'new_price_product' => $product->new_price_product,
            'old_price_product' => $product->old_price_product,
            'new_date_in_product' => $product->new_date_in_product,
            'new_status_product' => $product->new_status_product,
            'new_quality' => $product->new_quality,
            'new_category_product' => $product->new_category_product,
            'new_discount' => $product->new_discount,
            'display_price' => $product->display_price,
            'days_since_created' => now()->diffInDays($product->created_at),
        ];
    }
}