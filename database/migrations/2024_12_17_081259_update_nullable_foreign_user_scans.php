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

            // Ubah kolom agar dapat menerima NULL
            $table->unsignedBigInteger('format_barcode_id')->nullable()->change();

            // Tambahkan foreign key baru dengan ON DELETE SET NULL
            $table->foreign('format_barcode_id')
                ->references('id')
                ->on('format_barcodes')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('user_scans', function (Blueprint $table) {
            // Hapus foreign key dengan ON DELETE SET NULL
            $table->dropForeign(['format_barcode_id']);

            // Ubah kolom kembali agar tidak nullable
            $table->unsignedBigInteger('format_barcode_id')->nullable(false)->change();

            // Tambahkan kembali foreign key lama
            $table->foreign('format_barcode_id')
                ->references('id')
                ->on('format_barcodes');
        });
    }
};
