<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PalletImage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['file_path'];

    public function getFilePathAttribute()
    {
        return Storage::url('product-images/' . $this->attributes['filename']);
    }
}
