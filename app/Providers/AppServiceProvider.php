<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
           // Mengambil semua produk dengan status 'display'
           DB::listen(function ($new_products) {
            Log::info($new_products->sql, $new_products->bindings, $new_products->time);
        });
    }
}
