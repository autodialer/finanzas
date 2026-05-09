<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';
    protected $fillable = ['negocio_id', 'nombre'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function ingresos()
    {
        return $this->hasMany(Ingreso::class);
    }
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }
}
