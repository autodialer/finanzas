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

    // Registra un pago junto con el gasto que refleja la salida de dinero de la cuenta.
    public function registrarPago(array $datos): PagoPrestamo
    {
        $categoria = Categoria::firstOrCreate(
            ['nombre' => 'Préstamos', 'tipo' => 'gasto']
        );

        $etiquetaTipo = $datos['tipo'] === 'capital' ? 'capital' : 'interés';
        $tieneIva     = (bool) ($datos['tiene_iva'] ?? false);
        $montoIva     = $tieneIva ? round((float) ($datos['monto_iva'] ?? 0), 2) : 0;

        $gasto = Gasto::create([
            'negocio_id'   => $this->negocio_id,
            'categoria_id' => $categoria->id,
            'cuenta_id'    => $datos['cuenta_id'],
            'user_id'      => $datos['user_id'],
            'monto'        => $datos['monto'],
            'referencia'   => $datos['referencia'] ?? null,
            'fecha'        => $datos['fecha'],
            'concepto'     => "Pago préstamo ({$etiquetaTipo}): " . $this->concepto,
            'forma_pago'   => 'transferencia',
            'notas'        => $datos['notas'] ?? null,
            'tiene_iva'    => $tieneIva,
            'monto_iva'    => $montoIva,
        ]);

        return PagoPrestamo::create([
            'prestamo_id' => $this->id,
            'gasto_id'    => $gasto->id,
            'fecha'       => $datos['fecha'],
            'monto'       => $datos['monto'],
            'tipo'        => $datos['tipo'],
            'tiene_iva'   => $tieneIva,
            'monto_iva'   => $montoIva,
            'cuenta_id'   => $datos['cuenta_id'],
            'notas'       => $datos['notas'] ?? null,
            'user_id'     => $datos['user_id'],
        ]);
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
