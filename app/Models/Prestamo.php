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
        $pagadoCapital = $this->pagos()->where('tipo', 'capital')->sum('monto');
        return round($this->monto_original - $pagadoCapital, 2);
    }

    public function getPagadoCapitalAttribute(): float
    {
        return round($this->pagos()->where('tipo', 'capital')->sum('monto'), 2);
    }

    public function getPagadoInteresAttribute(): float
    {
        return round($this->pagos()->where('tipo', 'interes')->sum('monto'), 2);
    }
}
