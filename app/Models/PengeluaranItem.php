<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengeluaranItem extends Model
{
    protected $fillable = ['pengeluaran_id', 'nama', 'qty', 'harga'];

    public function pengeluaran()
    {
        return $this->belongsTo(Pengeluaran::class);
    }
}
