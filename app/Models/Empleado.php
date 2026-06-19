<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $fillable = ['negocio_id', 'empresa_id', 'nombre', 'cargo', 'salario', 'activo', 'periodo_pago'];

    protected $casts = ['activo' => 'boolean', 'salario' => 'decimal:2'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function nominas()
    {
        return $this->hasMany(Nomina::class);
    }
}
