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
        Schema::table('migrates', function (Blueprint $table) {
            $table->string('old_barcode_product')->unique()->after('code_document_migrate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('migrates', function (Blueprint $table) {
            $table->dropUnique('migrates_old_barcode_product_unique');
            $table->dropColumn('old_barcode_product');
        });
    }
};
