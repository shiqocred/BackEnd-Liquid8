<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function sales()
    {
        return $this->hasMany(Sale::class, 'code_document_sale', 'code_document_sale');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}
