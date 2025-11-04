<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriPengeluaran extends Model
{
    use HasFactory;

    // Nama tabel (opsional kalau sama dengan nama model jamak)
    protected $table = 'kategori_pengeluarans';

    // Kolom yang boleh diisi
    protected $fillable = [
        'nama_kategori',
        'deskripsi',
    ];

    // Relasi ke tabel pengeluarans
    public function pengeluaran()
    {
        return $this->hasMany(Pengeluaran::class, 'kategori_id');
    }
}

