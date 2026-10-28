<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    protected $table = 'cuentas';
    protected $fillable = ['negocio_id', 'banco_id', 'tipo', 'nombre', 'numero'];

    // Nunca se guarda el número completo de una tarjeta (15 o 16 dígitos): solo los últimos 4
    public function setNumeroAttribute($valor): void
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);
        $this->attributes['numero'] = in_array(strlen($digitos), [15, 16], true) ? substr($digitos, -4) : $valor;
    }

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
