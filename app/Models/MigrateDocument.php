<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MigrateDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function migrates()
    {
        return $this->hasMany(Migrate::class, 'code_document_migrate', 'code_document_migrate');
    }
}
