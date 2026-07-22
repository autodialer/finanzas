<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Negocio;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cuenta;
use Illuminate\Http\Request;

class IngresoController extends Controller
{
    public function index(Request $request)
    {
        $claves = ['fecha_desde', 'fecha_hasta', 'negocio_id', 'cliente_id', 'categoria_id', 'forma_pago'];

        if ($request->has('fecha_desde') || $request->has('limpiar')) {
            $filtros = $request->boolean('limpiar') ? [] : $request->only($claves);
            session(['ingresos_filtros' => array_filter($filtros, fn($v) => $v !== null && $v !== '')]);
        }

        $filtros = session('ingresos_filtros', []);

        $query = $this->aplicarFiltroReportes(
            Ingreso::with('negocio', 'categoria', 'cliente', 'cuenta', 'user')
        );

        if (!empty($filtros['fecha_desde']))  $query->where('fecha', '>=', $filtros['fecha_desde']);
        if (!empty($filtros['fecha_hasta']))  $query->where('fecha', '<=', $filtros['fecha_hasta']);
        if (!empty($filtros['negocio_id']))   $query->where('negocio_id', $filtros['negocio_id']);
        if (!empty($filtros['cliente_id']))   $query->where('cliente_id', $filtros['cliente_id']);
        if (!empty($filtros['categoria_id'])) $query->where('categoria_id', $filtros['categoria_id']);
        if (!empty($filtros['forma_pago']))   $query->where('forma_pago', $filtros['forma_pago']);

        $ingresos   = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();
        $negocios   = Negocio::orderBy('nombre')->get();
        $clientes   = Cliente::orderBy('nombre')->get();
        $categorias = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->orderBy('nombre')->get();

        return view('ingresos.index', compact('ingresos', 'negocios', 'clientes', 'categorias', 'filtros'));
    }

    public function create()
    {
        $negocios   = $this->negociosParaCaptura();
        $categorias = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->get();
        $clientes   = Cliente::orderBy('nombre')->get();
        $cuentas    = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('ingresos.create', compact('negocios', 'categorias', 'clientes', 'cuentas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id'  => 'required|exists:negocios,id',
            'categoria_id'=> 'required|exists:categorias,id',
            'cuenta_id'   => 'required|exists:cuentas,id',
            'monto'       => 'required|numeric|min:0',
            'fecha'       => 'required|date',
            'concepto'    => 'required',
            'forma_pago'  => 'required|in:efectivo,transferencia,tarjeta',
        ]);

        $data = $request->except(['tiene_iva', 'monto_iva']);
        $data = array_merge($data, $this->calcularIva($request), ['user_id' => auth()->id()]);

        Ingreso::create($data);
        return redirect()->route('ingresos.index')->with('exito', 'Ingreso registrado correctamente.');
    }

    public function edit(Ingreso $ingreso)
    {
        $negocios   = $this->negociosParaCaptura();
        $categorias = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->get();
        $clientes   = Cliente::orderBy('nombre')->get();
        $cuentas    = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('ingresos.edit', compact('ingreso', 'negocios', 'categorias', 'clientes', 'cuentas'));
    }

    public function update(Request $request, Ingreso $ingreso)
    {
        $request->validate([
            'negocio_id'  => 'required|exists:negocios,id',
            'categoria_id'=> 'required|exists:categorias,id',
            'cuenta_id'   => 'required|exists:cuentas,id',
            'monto'       => 'required|numeric|min:0',
            'fecha'       => 'required|date',
            'concepto'    => 'required',
            'forma_pago'  => 'required|in:efectivo,transferencia,tarjeta',
        ]);

        $data = $request->except(['tiene_iva', 'monto_iva']);
        $data = array_merge($data, $this->calcularIva($request));

        $ingreso->update($data);
        return redirect()->route('ingresos.index')->with('exito', 'Ingreso actualizado correctamente.');
    }

    private function calcularIva(Request $request): array
    {
        $forma = $request->forma_pago;
        $monto = (float) $request->monto;

        if (in_array($forma, ['transferencia', 'tarjeta'])) {
            $monto_iva = round($monto * 16 / 116, 2);
            return ['tiene_iva' => true, 'monto_iva' => $monto_iva];
        }

        return ['tiene_iva' => false, 'monto_iva' => 0];
    }

    public function destroy(Ingreso $ingreso)
    {
        $ingreso->delete();
        return redirect()->route('ingresos.index')->with('exito', 'Ingreso eliminado correctamente.');
    }
}
