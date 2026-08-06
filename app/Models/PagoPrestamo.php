<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoPrestamo extends Model
{
    protected $table = 'pagos_prestamo';
    protected $fillable = ['prestamo_id', 'gasto_id', 'fecha', 'monto', 'tipo', 'tiene_iva', 'monto_iva', 'cuenta_id', 'notas', 'user_id'];
    protected $casts = ['tiene_iva' => 'boolean', 'monto_iva' => 'decimal:2'];

    public function getMontoBaseAttribute(): float
    {
        return round($this->monto - $this->monto_iva, 2);
    }

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }
    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }
    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
