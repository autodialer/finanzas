<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    protected $table = 'prestamos';
    protected $fillable = [
        'negocio_id', 'banco_id', 'tipo', 'concepto', 'monto_original',
        'tasa_interes', 'plazo_meses', 'fecha_inicio', 'notas', 'user_id',
    ];

    public function negocio()
    {
        return $this->belongsTo(Negocio::class);
    }
    public function banco()
    {
        return $this->belongsTo(Banco::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function pagos()
    {
        return $this->hasMany(PagoPrestamo::class)->orderBy('fecha', 'desc')->orderBy('id', 'desc');
    }

    public function getSaldoPendienteAttribute(): float
    {
        return round($this->monto_original - $this->pagos()->sum('monto'), 2);
    }
}
