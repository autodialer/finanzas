<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatSolicitud extends Model
{
    public const ABIERTAS = ['pendiente', 'en_proceso'];

    protected $table = 'sat_solicitudes';
    protected $guarded = ['id'];

    protected $casts = [
        'desde'         => 'datetime',
        'hasta'         => 'datetime',
        'paquetes'      => 'array',
        'verificada_at' => 'datetime',
    ];

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function abierta(): bool
    {
        return in_array($this->estado, self::ABIERTAS, true);
    }
}
