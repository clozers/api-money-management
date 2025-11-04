<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengeluaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengeluaran_id')->constrained('pengeluarans')->onDelete('cascade'); // relasi ke tabel pengeluarans
            $table->string('nama'); // nama barang
            $table->integer('qty'); // jumlah barang
            $table->integer('harga'); // harga per item
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluarans_items');
    }
};
