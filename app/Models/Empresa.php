<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = ['negocio_id', 'nombre'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }

    public function empleados()
    {
        return $this->hasMany(Empleado::class);
    }

    public function cuentas()
    {
        return $this->hasMany(Cuenta::class);
    }

    public function periodosNomina()
    {
        return $this->hasMany(PeriodoNomina::class);
    }
}
