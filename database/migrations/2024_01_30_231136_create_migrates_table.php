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
        Schema::create('migrates', function (Blueprint $table) {
            $table->id();
            $table->string('code_document_migrate');
            $table->string('new_barcode_product')->unique();
            $table->string('new_name_product');
            $table->bigInteger('new_qty_product');
            $table->bigInteger('new_price_product');
            $table->string('new_tag_product')->nullable();
            $table->enum('status_migrate', ['proses', 'selesai']);
            $table->string('status_product_before');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migrates');
    }
};
