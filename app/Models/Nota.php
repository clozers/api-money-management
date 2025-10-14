<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    protected $fillable = ['user_id', 'filename', 'tanggal', 'total'];

    public function items()
    {
        return $this->hasMany(NotaItem::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
