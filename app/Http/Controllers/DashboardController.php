<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Cuenta;
use App\Models\Traspaso;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $mesActual  = now()->month;
        $anioActual = now()->year;
        $ids        = $this->negociosPermitidos(); // null = admin, collection = restringido

        $totalIngresosMes = $this->aplicarFiltroReportes(Ingreso::query())
            ->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->sum('monto');

        $totalGastosMes = $this->aplicarFiltroReportes(Gasto::query())
            ->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->sum('monto');

        $balanceMes = $totalIngresosMes - $totalGastosMes;

        $ivaCobradomMes = $this->aplicarFiltroReportes(Ingreso::query())
            ->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->sum('monto_iva');

        $ivaPagadoMes = $this->aplicarFiltroReportes(Gasto::query())
            ->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->sum('monto_iva');

        $saldoIva = $ivaCobradomMes - $ivaPagadoMes;

        $negociosBase = $ids ? Negocio::whereIn('id', $ids) : Negocio::query();

        $ingresosPorNegocio = (clone $negociosBase)->withSum(['ingresos' => function ($q) use ($mesActual, $anioActual) {
            $q->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual);
        }], 'monto')->get();

        $gastosPorNegocio = (clone $negociosBase)->withSum(['gastos' => function ($q) use ($mesActual, $anioActual) {
            $q->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual);
        }], 'monto')->get();

        // Cuentas visibles: solo las de negocios permitidos
        $cuentasQuery = Cuenta::with('negocio')->withSum('ingresos', 'monto')->withSum('gastos', 'monto');
        if ($ids !== null) {
            $cuentasQuery->whereIn('negocio_id', $ids);
        }

        $cuentaIds = $cuentasQuery->pluck('id');

        $traspasoEntrada = Traspaso::selectRaw('cuenta_destino_id as cuenta_id, SUM(monto) as total')
            ->groupBy('cuenta_destino_id')->pluck('total', 'cuenta_id');

        $traspasoSalida = Traspaso::selectRaw('cuenta_origen_id as cuenta_id, SUM(monto) as total')
            ->groupBy('cuenta_origen_id')->pluck('total', 'cuenta_id');

        $cuentasConSaldo = $cuentasQuery->get()->map(function ($cuenta) use ($traspasoEntrada, $traspasoSalida) {
            $ingresos = $cuenta->ingresos_sum_monto ?? 0;
            $gastos   = $cuenta->gastos_sum_monto ?? 0;
            $entrada  = $traspasoEntrada[$cuenta->id] ?? 0;
            $salida   = $traspasoSalida[$cuenta->id] ?? 0;
            $cuenta->total_entradas = $ingresos + $entrada;
            $cuenta->total_salidas  = $gastos + $salida;
            $cuenta->saldo          = $cuenta->total_entradas - $cuenta->total_salidas;
            return $cuenta;
        })->sortByDesc('saldo');

        $saldoTotalCuentas = $cuentasConSaldo->sum('saldo');

        $ultimosMovimientos = collect()
            ->merge($this->aplicarFiltroReportes(Ingreso::with('negocio', 'categoria', 'user'))->latest()->take(5)->get()->map(fn($i) => [
                'fecha'          => $i->fecha,
                'tipo'           => 'ingreso',
                'negocio'        => $i->negocio->nombre,
                'concepto'       => $i->concepto,
                'monto'          => $i->monto,
                'registrado_por' => $i->user->name ?? '-',
            ]))
            ->merge($this->aplicarFiltroReportes(Gasto::with('negocio', 'categoria', 'user'))->latest()->take(5)->get()->map(fn($g) => [
                'fecha'          => $g->fecha,
                'tipo'           => 'gasto',
                'negocio'        => $g->negocio->nombre,
                'concepto'       => $g->concepto,
                'monto'          => $g->monto,
                'registrado_por' => $g->user->name ?? '-',
            ]))
            ->sortByDesc('fecha')
            ->take(10);

        return view('dashboard', compact(
            'totalIngresosMes', 'totalGastosMes', 'balanceMes',
            'ivaCobradomMes', 'ivaPagadoMes', 'saldoIva',
            'ingresosPorNegocio', 'gastosPorNegocio',
            'ultimosMovimientos', 'cuentasConSaldo', 'saldoTotalCuentas'
        ));
    }
}
