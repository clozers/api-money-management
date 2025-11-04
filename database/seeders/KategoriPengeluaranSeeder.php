<?php

use Illuminate\Database\Seeder;
use App\Models\KategoriPengeluaran;

class KategoriPengeluaranSeeder extends Seeder
{
    public function run()
    {
        KategoriPengeluaran::create([
            'nama_kategori' => 'Makanan & Minuman',
            'deskripsi' => 'Biaya untuk makan dan minum sehari-hari',
        ]);

        KategoriPengeluaran::create([
            'nama_kategori' => 'Transportasi',
            'deskripsi' => 'Biaya bensin, ojek online, atau tiket transportasi',
        ]);

        KategoriPengeluaran::create([
            'nama_kategori' => 'Hiburan',
            'deskripsi' => 'Biaya untuk rekreasi, film, dan langganan hiburan',
        ]);

        KategoriPengeluaran::create([
            'nama_kategori' => 'Tagihan & Utilitas',
            'deskripsi' => 'Pembayaran listrik, air, internet, telepon, dan lainnya',
        ]);

        KategoriPengeluaran::create([
            'nama_kategori' => 'Belanja',
            'deskripsi' => 'Kebutuhan pribadi, pakaian, atau perlengkapan rumah',
        ]);

        KategoriPengeluaran::create([
            'nama_kategori' => 'Kesehatan',
            'deskripsi' => 'Biaya obat, vitamin, dan pemeriksaan kesehatan',
        ]);
    }
}
