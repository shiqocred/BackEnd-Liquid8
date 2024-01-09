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
        Schema::create('new_products', function (Blueprint $table) {
            $table->id(); 
            $table->string('code_document'); 
            $table->string('old_barcode_product');
            $table->string('new_barcode_product')->unique()->nullable();
            $table->string('new_name_product')->nullable();
            $table->integer('new_quantity_product');
            $table->decimal('new_price_product', 15, 2); 
            $table->date('new_date_in_product');
            $table->enum('new_status_product', ['display', 'expired', 'promo', 'bundle', 'palet']);
            $table->json('new_quality');
            $table->string('new_category_product')->nullable();
            $table->string('new_tag_product')->nullable();

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('new_products');
    }
};
