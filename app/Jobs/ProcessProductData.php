<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class ProcessProductData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchSize;
    protected $modelClass;

    public function __construct($batchSize, $modelClass)
    {
        $this->batchSize = $batchSize;
        $this->modelClass = $modelClass;
    }

    public function handle()
    {
        $redisKey = 'product_batch';

        
        // Ambil batch data dari Redis
        $batchData = Redis::lrange($redisKey, 0, $this->batchSize - 1);

        
        foreach ($batchData as $data) {
            $inputData = json_decode($data, true);
            

            $model = new $this->modelClass;
            $model->create($inputData);

        }

        Redis::ltrim($redisKey, $this->batchSize, -1);
    }
}
