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
        Schema::create('list_product_b_p_s', function (Blueprint $table) {
            $table->id();
            $table->foreignId('new_product_id')->constrained('new_products');
            $table->foreignId('bundle_id')->constrained('bundles');
            $table->foreignId('palet_id')->constrained('palets');
       
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_product_b_p_s');
    }
};
