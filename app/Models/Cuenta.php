<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    protected $table = 'cuentas';
    protected $fillable = ['negocio_id', 'banco_id', 'tipo', 'nombre', 'numero'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function banco()
    {
        return $this->belongsTo(Banco::class);
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
