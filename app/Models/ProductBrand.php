<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductBrand extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted()
    {
        static::updated(function ($productBrand) {
            // Update pallet_brand_name dengan menggunakan relasi Eloquent
            $palletBrands = PalletBrand::where('brand_id', $productBrand->id)->get();

            foreach ($palletBrands as $palletBrand) {
                $palletBrand->pallet_brand_name = $productBrand->brand_name;
                $palletBrand->save();
            }
        });
    }
}
