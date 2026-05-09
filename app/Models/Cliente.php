<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $fillable = ['negocio_id', 'vendedor_id', 'nombre', 'telefono', 'email'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function vendedor()
    {
        return $this->belongsTo(Vendedor::class);
    }
    public function ingresos()
    {
        return $this->hasMany(Ingreso::class);
    }
}
