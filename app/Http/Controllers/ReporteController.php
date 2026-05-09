<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Area;
use App\Models\Categoria;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $negocios = Negocio::all();
        $areas = Area::with('negocio')->get();
        $categorias = Categoria::all();

        $filtros = $request->only(['negocio_id', 'area_id', 'categoria_id', 'fecha_inicio', 'fecha_fin', 'tipo']);

        $queryIngresos = Ingreso::with('negocio', 'area', 'categoria', 'cliente', 'cuenta');
        $queryGastos = Gasto::with('negocio', 'area', 'categoria', 'proveedor', 'cuenta');

        if (!empty($filtros['negocio_id'])) {
            $queryIngresos->where('negocio_id', $filtros['negocio_id']);
            $queryGastos->where('negocio_id', $filtros['negocio_id']);
        }

        if (!empty($filtros['area_id'])) {
            $queryIngresos->where('area_id', $filtros['area_id']);
            $queryGastos->where('area_id', $filtros['area_id']);
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
            'areas',
            'categorias',
            'filtros',
            'ingresos',
            'gastos',
            'totalIngresos',
            'totalGastos',
            'balance'
        ));
    }
}
