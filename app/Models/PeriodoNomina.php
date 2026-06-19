<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodoNomina extends Model
{
    protected $table = 'periodos_nomina';

    protected $fillable = ['negocio_id', 'empresa_id', 'cuenta_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'tipo_periodo'];

    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function nominas()
    {
        return $this->hasMany(Nomina::class, 'periodo_id');
    }

    public function getTotalAttribute()
    {
        return $this->nominas->sum('monto');
    }
}
