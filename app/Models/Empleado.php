<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $fillable = ['negocio_id', 'nombre', 'cargo', 'salario', 'activo'];

    protected $casts = ['activo' => 'boolean', 'salario' => 'decimal:2'];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }

    public function nominas()
    {
        return $this->hasMany(Nomina::class);
    }
}
