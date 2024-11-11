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
        Schema::table('new_products', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('product__filters', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('product__bundles', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('palet_filters', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('palet_products', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('repair_products', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('repair_filters', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('product_approves', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('product_qcds', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('filter_qcds', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('filter_product_inputs', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('filter_stagings', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('product_inputs', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('staging_approves', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
        Schema::table('staging_products', function (Blueprint $table) {
            $table->enum('type_color', ['type_color1', 'type_color2'])->nullable();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_products', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('product__filters', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('product__bundles', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('palet_filters', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('palet_products', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('repair_products', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('repair_filters', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('product_approves', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('product_qcds', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('filter_qcds', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('filter_product_inputs', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('filter_stagings', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('product_inputs', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('staging_approves', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
        Schema::table('staging_products', function (Blueprint $table) {
            $table->dropColumn('type_color');
        });
    }
};
