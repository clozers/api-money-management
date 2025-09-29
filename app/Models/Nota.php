<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    protected $fillable = ['filename', 'tanggal', 'total'];

    public function items()
    {
        return $this->hasMany(NotaItem::class);
    }
}
