<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    protected $fillable = ['user_id', 'filename', 'tanggal', 'catatan', 'total', 'kategori_id'];

    public function items()
    {
        return $this->hasMany(PengeluaranItem::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function kategori()
    {
        return $this->belongsTo(KategoriPengeluaran::class, 'kategori_id');
    }
}
