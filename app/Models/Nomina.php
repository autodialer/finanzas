<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nomina extends Model
{
    protected $fillable = ['periodo_id', 'empleado_id', 'monto', 'notas'];

    protected $casts = ['monto' => 'decimal:2'];

    public function periodo()
    {
        return $this->belongsTo(PeriodoNomina::class, 'periodo_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
