<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MigrateBulky extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function migrateBulkyProducts()
    {
        return $this->hasMany(MigrateBulkyProduct::class);
    }
}
