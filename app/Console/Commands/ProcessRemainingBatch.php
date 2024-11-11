<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\ProductApprove;

class ProcessRemainingBatch extends Command
{
    protected $signature = 'batch:processRemaining';
    protected $description = 'Process remaining batch data in Redis if count is less than batch size';

    public function handle()
    {
        $batchSize = 7;
        $redisKey = 'product_batch';

        $batchData = Redis::lrange($redisKey, 0, $batchSize - 1);

        if (!empty($batchData)) {
            foreach ($batchData as $data) {
                $inputData = json_decode($data, true);

                if ($inputData) {
                    ProductApprove::create($inputData);
                }
            }

            Redis::ltrim($redisKey, $batchSize, -1);

            \Log::info("Processed batch of size: " . count($batchData));
        } else {
            \Log::info("No data to process in Redis.");
        }
    }
}
