<?php

namespace App\Models;

use App\Models\PaletImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Palet extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function paletProducts()
    {
        return $this->hasMany(PaletProduct::class);
    }
    public function paletImages()
    {
        return $this->hasMany(PaletImage::class, 'palet_id');
    }
    public function paletBrands()
    {
        return $this->hasMany(PaletBrand::class, 'palet_id');
    }
}
