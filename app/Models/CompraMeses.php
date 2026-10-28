<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Compra a meses sin intereses. El gasto ligado registra el monto completo en la
 * fecha de compra; las mensualidades se pagan con traspasos a la tarjeta, así que
 * el calendario y los saldos se calculan, no se guardan.
 */
class CompraMeses extends Model
{
    protected $table = 'compras_meses';
    protected $fillable = [
        'gasto_id', 'cuenta_id', 'descripcion', 'monto_original', 'meses',
        'mensualidad', 'fecha_compra', 'fecha_primer_cargo', 'notas',
    ];

    protected $casts = [
        'monto_original'     => 'decimal:2',
        'mensualidad'        => 'decimal:2',
        'meses'              => 'integer',
        'fecha_compra'       => 'date',
        'fecha_primer_cargo' => 'date',
    ];

    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }
    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    /**
     * Mensualidades: una por mes desde el primer cargo. La última absorbe la
     * diferencia de centavos para que la suma sea exactamente el monto original.
     */
    public static function calendario(float $monto, int $meses, float $mensualidad, CarbonInterface $primerCargo): Collection
    {
        $ultima = round($monto - $mensualidad * ($meses - 1), 2);

        return collect(range(1, max($meses, 1)))->map(fn ($n) => [
            'numero' => $n,
            'fecha'  => Carbon::parse($primerCargo)->addMonthsNoOverflow($n - 1),
            'monto'  => $n < $meses ? round($mensualidad, 2) : $ultima,
        ]);
    }

    public function getCalendarioAttribute(): Collection
    {
        return self::calendario((float) $this->monto_original, $this->meses, (float) $this->mensualidad, $this->fecha_primer_cargo);
    }

    // Una mensualidad cuenta como pagada cuando su fecha de cargo ya pasó (o es hoy).
    public function pagosRealizados(?CarbonInterface $al = null): int
    {
        $al = ($al ?? now())->copy()->endOfDay();
        return $this->calendario->filter(fn ($c) => $c['fecha']->lte($al))->count();
    }

    public function pagosRestantes(?CarbonInterface $al = null): int
    {
        return $this->meses - $this->pagosRealizados($al);
    }

    public function saldoPendiente(?CarbonInterface $al = null): float
    {
        return round($this->calendario->slice($this->pagosRealizados($al))->sum('monto'), 2);
    }

    public function getPagosRealizadosAttribute(): int
    {
        return $this->pagosRealizados();
    }
    public function getPagosRestantesAttribute(): int
    {
        return $this->pagosRestantes();
    }
    public function getSaldoPendienteAttribute(): float
    {
        return $this->saldoPendiente();
    }
    public function getFechaUltimoPagoAttribute(): Carbon
    {
        return $this->calendario->last()['fecha'];
    }
    public function getTerminadaAttribute(): bool
    {
        return $this->pagosRestantes() === 0;
    }

    /** Mensualidades que caen en un rango de fechas (para prorratear reportes). */
    public function cargosEntre(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return $this->calendario->filter(fn ($c) => $c['fecha']->between($desde->copy()->startOfDay(), $hasta->copy()->endOfDay()))->values();
    }

    /**
     * Suma de mensualidades por mes ('Y-m' => total) de varias compras, desde el
     * mes de $desde y por $meses meses. Los meses sin cargos aparecen en cero.
     */
    public static function compromisosPorMes(Collection $compras, CarbonInterface $desde, int $meses): array
    {
        $inicio = Carbon::parse($desde)->startOfMonth();
        $totales = [];
        for ($i = 0; $i < $meses; $i++) {
            $totales[$inicio->copy()->addMonthsNoOverflow($i)->format('Y-m')] = 0.0;
        }

        foreach ($compras as $compra) {
            foreach ($compra->calendario as $cargo) {
                $clave = $cargo['fecha']->format('Y-m');
                if (array_key_exists($clave, $totales)) {
                    $totales[$clave] = round($totales[$clave] + $cargo['monto'], 2);
                }
            }
        }

        return $totales;
    }
}
