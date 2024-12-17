<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('user_scans', function (Blueprint $table) {
            // Hapus foreign key lama
            $table->dropForeign(['format_barcode_id']);

            // Tambahkan foreign key baru dengan ON DELETE CASCADE
            $table->foreign('format_barcode_id')
                ->references('id')
                ->on('format_barcodes')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('user_scans', function (Blueprint $table) {
            // Hapus foreign key dengan ON DELETE CASCADE
            $table->dropForeign(['format_barcode_id']);

            // Tambahkan kembali foreign key lama
            $table->foreign('format_barcode_id')
                ->references('id')
                ->on('format_barcodes');
        });
    }
};
