<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductScan extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    // protected $appends = ['file_path'];

    // public function getFilePathAttribute()
    // {
    //     return Storage::url('product-images/' . $this->attributes['filename']);
    // }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
