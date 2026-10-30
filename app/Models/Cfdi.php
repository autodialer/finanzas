<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cfdi extends Model
{
    public const TIPOS = ['I' => 'Ingreso', 'E' => 'Egreso', 'P' => 'Pago', 'N' => 'Nómina', 'T' => 'Traslado'];

    protected $table = 'cfdis';
    protected $guarded = ['id'];

    protected $casts = [
        'fecha'          => 'datetime',
        'tipo_cambio'    => 'decimal:6',
        'subtotal'       => 'decimal:2',
        'descuento'      => 'decimal:2',
        'total'          => 'decimal:2',
        'iva_trasladado' => 'decimal:2',
        'iva_retenido'   => 'decimal:2',
        'isr_retenido'   => 'decimal:2',
        'ieps'           => 'decimal:2',
    ];

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }

    // Total en pesos (las facturas en dólares traen tipo de cambio)
    public function totalMxn(): float
    {
        $tc = in_array($this->moneda, ['MXN', 'XXX', null], true) ? 1.0 : (float) $this->tipo_cambio;
        return round((float) $this->total * ($tc > 0 ? $tc : 1.0), 2);
    }

    public function contraparteRfc(): string
    {
        return $this->direccion === 'recibido' ? $this->rfc_emisor : $this->rfc_receptor;
    }

    public function contraparteNombre(): ?string
    {
        return $this->direccion === 'recibido' ? $this->nombre_emisor : $this->nombre_receptor;
    }
}
