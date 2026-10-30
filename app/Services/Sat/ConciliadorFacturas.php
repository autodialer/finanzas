<?php

namespace App\Services\Sat;

use App\Models\Cfdi;
use App\Models\Contribuyente;
use App\Models\Gasto;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Vincula los gastos de los negocios de un contribuyente con sus facturas recibidas.
 *
 * Una factura es candidata para un gasto cuando su total en pesos coincide (±$1 o ±0.5%,
 * con o sin la propina) y se emitió entre 10 días antes y 45 días después del gasto (muchos
 * comercios facturan días después de la compra). Solo se vincula automáticamente cuando el
 * gasto tiene una sola factura candidata y esa factura solo sirve para ese gasto; los demás
 * casos se proponen en la pantalla de gastos sin factura.
 */
class ConciliadorFacturas
{
    public const DIAS_ANTES   = 10;
    public const DIAS_DESPUES = 45;

    /** @return int facturas vinculadas */
    public function conciliar(Contribuyente $contribuyente, ?Carbon $desde = null, ?Carbon $hasta = null): int
    {
        $gastos = $this->gastosSinFactura($contribuyente, $desde, $hasta)->get();
        if ($gastos->isEmpty()) {
            return 0;
        }

        $facturas = $this->facturasLibres($contribuyente)
            ->whereBetween('fecha', [
                Carbon::parse($gastos->min('fecha'))->subDays(self::DIAS_ANTES)->startOfDay(),
                Carbon::parse($gastos->max('fecha'))->addDays(self::DIAS_DESPUES)->endOfDay(),
            ])
            ->get();

        $candidatas = [];
        $porFactura = [];
        foreach ($gastos as $gasto) {
            $ids = $facturas->filter(fn (Cfdi $f) => $this->coincide($gasto, $f))->pluck('id')->all();
            $candidatas[$gasto->id] = $ids;
            foreach ($ids as $id) {
                $porFactura[$id] = ($porFactura[$id] ?? 0) + 1;
            }
        }

        $vinculadas = 0;
        foreach ($candidatas as $gastoId => $ids) {
            if (count($ids) === 1 && $porFactura[$ids[0]] === 1) {
                Cfdi::whereKey($ids[0])->whereNull('gasto_id')->update(['gasto_id' => $gastoId, 'vinculo' => 'auto']);
                $vinculadas++;
            }
        }

        return $vinculadas;
    }

    /** Facturas libres que podrían ser de este gasto, la más cercana en fecha primero. */
    public function candidatas(Gasto $gasto, Contribuyente $contribuyente): Collection
    {
        $fecha = Carbon::parse($gasto->fecha);

        return $this->facturasLibres($contribuyente)
            ->whereBetween('fecha', [
                $fecha->copy()->subDays(self::DIAS_ANTES)->startOfDay(),
                $fecha->copy()->addDays(self::DIAS_DESPUES)->endOfDay(),
            ])
            ->get()
            ->filter(fn (Cfdi $f) => $this->coincide($gasto, $f))
            ->sortBy(fn (Cfdi $f) => abs($f->fecha->diffInDays($fecha)))
            ->values();
    }

    public function gastosSinFactura(Contribuyente $contribuyente, ?Carbon $desde = null, ?Carbon $hasta = null)
    {
        return Gasto::whereIn('negocio_id', $contribuyente->negocios()->pluck('id'))
            ->where('requiere_factura', true)
            ->whereDoesntHave('cfdis')
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta));
    }

    public function facturasLibres(Contribuyente $contribuyente)
    {
        return Cfdi::where('contribuyente_id', $contribuyente->id)
            ->where('direccion', 'recibido')
            ->where('tipo', 'I')
            ->where('estado', 'vigente')
            ->whereNull('gasto_id');
    }

    public function coincide(Gasto $gasto, Cfdi $factura): bool
    {
        $fecha = Carbon::parse($gasto->fecha);
        if ($factura->fecha->lt($fecha->copy()->subDays(self::DIAS_ANTES)->startOfDay())
            || $factura->fecha->gt($fecha->copy()->addDays(self::DIAS_DESPUES)->endOfDay())) {
            return false;
        }

        $total = $factura->totalMxn();
        $montos = array_unique([(float) $gasto->monto, (float) $gasto->monto - (float) ($gasto->monto_propina ?? 0)]);

        foreach ($montos as $monto) {
            if (abs($total - $monto) <= max(1.0, $monto * 0.005)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hasta cuándo conviene pedir la factura: muchos comercios solo facturan dentro del mes de la
     * compra, y la declaración mensual vence el día 17 del mes siguiente.
     *
     * @return array{fin_mes: Carbon, declaracion: Carbon, dias: int, urgencia: string}
     */
    public static function fechaLimite(Gasto $gasto, ?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $fecha = Carbon::parse($gasto->fecha);
        $finMes = $fecha->copy()->endOfMonth()->startOfDay();
        $declaracion = $fecha->copy()->startOfMonth()->addMonthNoOverflow()->day(17)->startOfDay();
        $dias = (int) $hoy->diffInDays($finMes, false);

        $urgencia = match (true) {
            $hoy->gt($declaracion) => 'tarde',
            $dias < 0              => 'vencida',
            $dias <= 5             => 'urgente',
            default                => 'a_tiempo',
        };

        return ['fin_mes' => $finMes, 'declaracion' => $declaracion, 'dias' => $dias, 'urgencia' => $urgencia];
    }
}
