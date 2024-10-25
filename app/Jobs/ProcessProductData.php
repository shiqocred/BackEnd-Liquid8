<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessProductData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $barcode;

    public function __construct($barcode)
    {
        $this->barcode = $barcode;
    }

    public function handle()
    {
        // Ambil data dari Redis menggunakan barcode
        $redisKey = 'product:' . $this->barcode;
        $data = Redis::get($redisKey);

        if ($data) {
            $inputData = json_decode($data, true);

            ProductApprove::create($inputData);

            Redis::del($redisKey);
        }
    }
}
