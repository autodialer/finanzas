<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Gasto;
use App\Models\Traspaso;
use App\Models\Negocio;
use App\Models\Categoria;
use App\Models\Cuenta;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $negocios = $this->negociosVisibles();
        $categorias = Categoria::all();

        $filtros = $request->only(['negocio_id', 'categoria_id', 'fecha_inicio', 'fecha_fin', 'tipo']);

        $queryIngresos = $this->aplicarFiltroReportes(Ingreso::with('negocio', 'categoria', 'cliente', 'cuenta'));
        $queryGastos   = $this->aplicarFiltroReportes(Gasto::with('negocio', 'categoria', 'proveedor', 'cuenta'));

        if (!empty($filtros['negocio_id'])) {
            $queryIngresos->where('negocio_id', $filtros['negocio_id']);
            $queryGastos->where('negocio_id', $filtros['negocio_id']);
        }

        if (!empty($filtros['categoria_id'])) {
            $queryIngresos->where('categoria_id', $filtros['categoria_id']);
            $queryGastos->where('categoria_id', $filtros['categoria_id']);
        }

        if (!empty($filtros['fecha_inicio'])) {
            $queryIngresos->where('fecha', '>=', $filtros['fecha_inicio']);
            $queryGastos->where('fecha', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $queryIngresos->where('fecha', '<=', $filtros['fecha_fin']);
            $queryGastos->where('fecha', '<=', $filtros['fecha_fin']);
        }

        $ingresos = ($filtros['tipo'] ?? '') == 'gasto' ? collect() : $queryIngresos->latest()->get();
        $gastos = ($filtros['tipo'] ?? '') == 'ingreso' ? collect() : $queryGastos->latest()->get();
        $totalIngresos = $ingresos->sum('monto');
        $totalGastos = $gastos->sum('monto');
        $balance = $totalIngresos - $totalGastos;

        return view('reportes.index', compact(
            'negocios',
            'categorias',
            'filtros',
            'ingresos',
            'gastos',
            'totalIngresos',
            'totalGastos',
            'balance'
        ));
    }

    public function mensual(Request $request)
    {
        $mes  = (int) $request->input('mes', now()->month);
        $anio = (int) $request->input('anio', now()->year);

        $inicioMes = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes    = $inicioMes->copy()->endOfMonth();

        $negocios = $this->negociosVisibles()->sortBy('nombre');
        $negocioIds = $negocios->pluck('id');

        $ingresosPorNegocio = $this->aplicarFiltroReportes(Ingreso::query())
            ->whereIn('negocio_id', $negocioIds)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->selectRaw('negocio_id, SUM(monto) as total')
            ->groupBy('negocio_id')
            ->pluck('total', 'negocio_id');

        $gastosPorNegocio = $this->aplicarFiltroReportes(Gasto::query())
            ->whereIn('negocio_id', $negocioIds)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->selectRaw('negocio_id, SUM(monto) as total')
            ->groupBy('negocio_id')
            ->pluck('total', 'negocio_id');

        $reporte = $negocios->map(function ($negocio) use ($ingresosPorNegocio, $gastosPorNegocio) {
            $ingresos = (float) ($ingresosPorNegocio[$negocio->id] ?? 0);
            $gastos   = (float) ($gastosPorNegocio[$negocio->id] ?? 0);
            return [
                'negocio'  => $negocio,
                'ingresos' => $ingresos,
                'gastos'   => $gastos,
                'balance'  => $ingresos - $gastos,
            ];
        });

        $totalIngresos = $reporte->sum('ingresos');
        $totalGastos   = $reporte->sum('gastos');
        $balanceGeneral = $totalIngresos - $totalGastos;

        return view('reportes.mensual', compact(
            'reporte',
            'totalIngresos',
            'totalGastos',
            'balanceGeneral',
            'mes',
            'anio',
            'inicioMes',
            'finMes'
        ));
    }

    public function cuentas(Request $request)
    {
        $negocios = $this->negociosVisibles()->sortBy('nombre');

        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaFin    = $request->input('fecha_fin',    now()->toDateString());
        $negocioId   = $request->input('negocio_id');
        $cuentaId    = $request->input('cuenta_id');

        // Catálogo de cuentas para el selector (todas las visibles, sin filtrar por cuenta)
        $cuentasDisponibles = $this->aplicarFiltroNegocio(Cuenta::with('negocio', 'banco'))
            ->orderBy('negocio_id')->orderBy('nombre')->get();

        // Si la cuenta elegida no es visible para el usuario, se ignora el filtro
        if ($cuentaId && !$cuentasDisponibles->contains('id', (int) $cuentaId)) {
            $cuentaId = null;
        }

        // Cuentas con su negocio y banco — solo de negocios visibles
        $cuentasQuery = $this->aplicarFiltroNegocio(Cuenta::with('negocio', 'banco'))->orderBy('negocio_id');
        if ($negocioId) {
            $cuentasQuery->where('negocio_id', $negocioId);
        }
        if ($cuentaId) {
            $cuentasQuery->where('id', $cuentaId);
        }
        $cuentas = $cuentasQuery->get();
        $cuentaIds = $cuentas->pluck('id');

        // ── Movimientos del PERÍODO (para desglose diario) ──────────────────

        $ingresosPorCuentaFecha = Ingreso::selectRaw('cuenta_id, fecha, SUM(monto) as total')
            ->whereIn('cuenta_id', $cuentaIds)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->groupBy('cuenta_id', 'fecha')->orderBy('fecha')->get()->groupBy('cuenta_id');

        $gastosPorCuentaFecha = Gasto::selectRaw('cuenta_id, fecha, SUM(monto) as total')
            ->whereIn('cuenta_id', $cuentaIds)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->groupBy('cuenta_id', 'fecha')->orderBy('fecha')->get()->groupBy('cuenta_id');

        // Traspasos del período: entrada y salida por cuenta y fecha
        $traspasoEntradaPeriodo = Traspaso::selectRaw('cuenta_destino_id as cuenta_id, fecha, SUM(monto) as total')
            ->whereIn('cuenta_destino_id', $cuentaIds)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->groupBy('cuenta_destino_id', 'fecha')->orderBy('fecha')->get()->groupBy('cuenta_id');

        $traspasoSalidaPeriodo = Traspaso::selectRaw('cuenta_origen_id as cuenta_id, fecha, SUM(monto) as total')
            ->whereIn('cuenta_origen_id', $cuentaIds)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->groupBy('cuenta_origen_id', 'fecha')->orderBy('fecha')->get()->groupBy('cuenta_id');

        // ── Construir reporte ─────────────────────────────────────────────────
        $reporte = [];

        foreach ($cuentas as $cuenta) {
            $negNombre = $cuenta->negocio->nombre ?? 'Sin negocio';
            $id = $cuenta->id;

            // Saldo histórico ANTES del período (ingresos - gastos + traspasos entrada - traspasos salida)
            $saldoAnterior =
                Ingreso::where('cuenta_id', $id)->where('fecha', '<', $fechaInicio)->sum('monto')
                - Gasto::where('cuenta_id', $id)->where('fecha', '<', $fechaInicio)->sum('monto')
                + Traspaso::where('cuenta_destino_id', $id)->where('fecha', '<', $fechaInicio)->sum('monto')
                - Traspaso::where('cuenta_origen_id', $id)->where('fecha', '<', $fechaInicio)->sum('monto');

            $ingCuenta      = $ingresosPorCuentaFecha->get($id, collect());
            $gastCuenta     = $gastosPorCuentaFecha->get($id, collect());
            $entradaCuenta  = $traspasoEntradaPeriodo->get($id, collect());
            $salidaCuenta   = $traspasoSalidaPeriodo->get($id, collect());

            // Todas las fechas con movimiento en el período
            $fechas = $ingCuenta->pluck('fecha')
                ->merge($gastCuenta->pluck('fecha'))
                ->merge($entradaCuenta->pluck('fecha'))
                ->merge($salidaCuenta->pluck('fecha'))
                ->unique()->sort()->values();

            $dias        = [];
            $saldoAcum   = $saldoAnterior;

            foreach ($fechas as $fecha) {
                $ing     = $ingCuenta->firstWhere('fecha', $fecha)?->total    ?? 0;
                $gast    = $gastCuenta->firstWhere('fecha', $fecha)?->total   ?? 0;
                $entrada = $entradaCuenta->firstWhere('fecha', $fecha)?->total ?? 0;
                $salida  = $salidaCuenta->firstWhere('fecha', $fecha)?->total  ?? 0;

                $saldoAcum += $ing - $gast + $entrada - $salida;

                $dias[] = [
                    'fecha'    => $fecha,
                    'ingresos' => $ing + $entrada,   // ingresos + traspasos que entran
                    'egresos'  => $gast + $salida,   // gastos + traspasos que salen
                    'saldo'    => $saldoAcum,        // saldo acumulado total
                ];
            }

            $totalIng  = $ingCuenta->sum('total')  + $entradaCuenta->sum('total');
            $totalGast = $gastCuenta->sum('total') + $salidaCuenta->sum('total');
            $saldoFinal = $saldoAnterior + $totalIng - $totalGast;

            $reporte[$negNombre][] = [
                'cuenta'         => $cuenta,
                'saldo_anterior' => $saldoAnterior,
                'dias'           => $dias,
                'total_ingresos' => $totalIng,
                'total_egresos'  => $totalGast,
                'saldo_final'    => $saldoFinal,
            ];
        }

        return view('reportes.cuentas', compact(
            'negocios',
            'cuentasDisponibles',
            'reporte',
            'fechaInicio',
            'fechaFin',
            'negocioId',
            'cuentaId'
        ));
    }
}
