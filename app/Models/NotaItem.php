<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaItem extends Model
{
    protected $fillable = ['nota_id', 'nama', 'qty', 'harga'];

    public function nota()
    {
        return $this->belongsTo(Nota::class);
    }
}
