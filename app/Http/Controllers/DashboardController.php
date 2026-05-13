<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Gasto;
use App\Models\Negocio;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $mesActual = now()->month;
        $anioActual = now()->year;

        $totalIngresosMes = Ingreso::whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->sum('monto');

        $totalGastosMes = Gasto::whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->sum('monto');

        $balanceMes = $totalIngresosMes - $totalGastosMes;

        $ivaCobradomMes = Ingreso::whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->sum('monto_iva');

        $ivaPagadoMes = Gasto::whereMonth('fecha', $mesActual)
            ->whereYear('fecha', $anioActual)
            ->sum('monto_iva');

        $saldoIva = $ivaCobradomMes - $ivaPagadoMes;

        $ingresosPorNegocio = Negocio::withSum(['ingresos' => function ($q) use ($mesActual, $anioActual) {
            $q->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual);
        }], 'monto')->get();

        $gastosPorNegocio = Negocio::withSum(['gastos' => function ($q) use ($mesActual, $anioActual) {
            $q->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual);
        }], 'monto')->get();

        $ultimosMovimientos = collect()
            ->merge(Ingreso::with('negocio', 'categoria')->latest()->take(5)->get()->map(fn($i) => [
                'fecha' => $i->fecha,
                'tipo' => 'ingreso',
                'negocio' => $i->negocio->nombre,
                'concepto' => $i->concepto,
                'monto' => $i->monto,
            ]))
            ->merge(Gasto::with('negocio', 'categoria')->latest()->take(5)->get()->map(fn($g) => [
                'fecha' => $g->fecha,
                'tipo' => 'gasto',
                'negocio' => $g->negocio->nombre,
                'concepto' => $g->concepto,
                'monto' => $g->monto,
            ]))
            ->sortByDesc('fecha')
            ->take(10);

        return view('dashboard', compact(
            'totalIngresosMes',
            'totalGastosMes',
            'balanceMes',
            'ivaCobradomMes',
            'ivaPagadoMes',
            'saldoIva',
            'ingresosPorNegocio',
            'gastosPorNegocio',
            'ultimosMovimientos'
        ));
    }
}
