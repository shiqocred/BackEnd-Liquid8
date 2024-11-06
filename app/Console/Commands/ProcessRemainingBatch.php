<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\ProductApprove;
use App\Jobs\ProcessProductData;


class ProcessRemainingBatch extends Command
{
    
    protected $signature = 'batch:processRemaining';
    protected $description = 'Process remaining batch data in Redis if count is less than batch size';

    public function handle()
    {
        $batchSize = 7;
        $redisKey = 'product_batch';

        $currentBatchCount = Redis::llen($redisKey);

        if ($currentBatchCount > 0 && $currentBatchCount < $batchSize) {
            // \Log::info("Processing remaining batch data with size: $currentBatchCount");

            ProcessProductData::dispatch($currentBatchCount, ProductApprove::class);
        }
    }
}
