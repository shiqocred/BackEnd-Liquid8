<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class New_product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function Promos()
    {
        return $this->hasMany(Promo::class);
    }

    protected $appends = ['days_since_created'];

    public function getDaysSinceCreatedAttribute()
    {
        return Carbon::parse($this->created_at)->diffInDays(Carbon::now()) . ' Hari';
    }
}
