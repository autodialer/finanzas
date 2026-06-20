<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table = 'gastos';
    protected $fillable = ['negocio_id', 'categoria_id', 'proveedor_id', 'cuenta_id', 'user_id', 'monto', 'fecha', 'concepto', 'forma_pago', 'notas', 'tiene_iva', 'monto_iva', 'tiene_propina', 'porcentaje_propina', 'monto_propina'];

    protected $casts = ['tiene_iva' => 'boolean', 'monto_iva' => 'decimal:2', 'tiene_propina' => 'boolean', 'monto_propina' => 'decimal:2'];

    public function getMontoBaseAttribute(): float
    {
        return round($this->monto - $this->monto_iva, 2);
    }

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
