<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $fillable = ['negocio_id', 'nombre', 'telefono', 'email'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }
}
