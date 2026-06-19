<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    protected $table = 'ingresos';
    protected $fillable = ['negocio_id', 'categoria_id', 'cliente_id', 'cuenta_id', 'user_id', 'monto', 'fecha', 'concepto', 'forma_pago', 'notas', 'tiene_iva', 'monto_iva'];

    protected $casts = ['tiene_iva' => 'boolean', 'monto_iva' => 'decimal:2'];

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
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
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
