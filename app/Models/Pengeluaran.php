<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengeluaran extends Model
{
    protected $fillable = ['user_id', 'filename', 'tanggal', 'total'];

    public function items()
    {
        return $this->hasMany(PengeluaranItem::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
