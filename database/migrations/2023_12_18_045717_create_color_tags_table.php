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
        Schema::create('color_tags', function (Blueprint $table) {
            $table->id(); // bigInteger auto-increment primary key
            $table->string('hexa_code_color');
            $table->string('name_color');
            $table->decimal('min_price_color', 15, 2); // Angka desimal dengan 15 digit dan 2 desimal
            $table->decimal('max_price_color', 15, 2); // Angka desimal dengan 15 digit dan 2 desimal
            $table->decimal('fixed_price_color', 15, 2); // Angka desimal dengan 15 digit dan 2 desimal

            $table->timestamps(); // Menambahkan kolom created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('color_tags');
    }
};
