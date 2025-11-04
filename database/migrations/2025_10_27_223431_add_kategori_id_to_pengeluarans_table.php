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
        Schema::table('pengeluarans', function (Blueprint $table) {
            // tambahkan kolom kategori_id
            $table->unsignedBigInteger('kategori_id')->after('user_id')->nullable();

            // buat foreign key-nya
            $table->foreign('kategori_id')
                ->references('id')
                ->on('kategori_pengeluarans')
                ->onDelete('set null'); // kalau kategori dihapus, nilai jadi null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('pengeluarans', function (Blueprint $table) {
            $table->dropForeign(['kategori_id']);
            $table->dropColumn('kategori_id');
        });
    }
};
