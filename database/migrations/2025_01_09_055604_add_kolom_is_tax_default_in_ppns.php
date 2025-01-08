<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ppns', function (Blueprint $table) {
            $table->boolean('is_tax_default')->default(false)->comment('Penanda PPN yang aktif');
            $table->unique('is_tax_default', 'unique_is_tax_default'); // Membatasi hanya satu baris bernilai true
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppns', function (Blueprint $table) {
            $table->dropUnique('unique_is_tax_default'); 
            $table->dropColumn('is_tax_default');
        });
    }
};
