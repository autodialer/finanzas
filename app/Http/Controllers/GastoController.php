<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Cuenta;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $claves = ['fecha_desde', 'fecha_hasta', 'negocio_id', 'proveedor_id', 'categoria_id', 'cuenta_id'];

        if ($request->has('fecha_desde') || $request->has('limpiar')) {
            // El usuario envió el formulario de filtros o pidió limpiar
            $filtros = $request->boolean('limpiar') ? [] : $request->only($claves);
            session(['gastos_filtros' => array_filter($filtros, fn($v) => $v !== null && $v !== '')]);
        }

        $filtros = session('gastos_filtros', []);

        $query = $this->aplicarFiltroReportes(
            Gasto::with('negocio', 'categoria', 'proveedor', 'cuenta', 'user', 'compraMeses')
        );

        if (!empty($filtros['fecha_desde']))   $query->where('fecha', '>=', $filtros['fecha_desde']);
        if (!empty($filtros['fecha_hasta']))   $query->where('fecha', '<=', $filtros['fecha_hasta']);
        if (!empty($filtros['negocio_id']))    $query->where('negocio_id', $filtros['negocio_id']);
        if (!empty($filtros['proveedor_id']))  $query->where('proveedor_id', $filtros['proveedor_id']);
        if (!empty($filtros['categoria_id']))  $query->where('categoria_id', $filtros['categoria_id']);
        if (!empty($filtros['cuenta_id']))     $query->where('cuenta_id', $filtros['cuenta_id']);

        $gastos      = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();
        $negocios    = Negocio::orderBy('nombre')->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get();
        $cuentas     = Cuenta::orderBy('nombre')->get();

        return view('gastos.index', compact('gastos', 'negocios', 'proveedores', 'categorias', 'cuentas', 'filtros'));
    }

    public function create()
    {
        $negocios    = $this->negociosParaCaptura();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas     = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.create', compact('negocios', 'categorias', 'proveedores', 'cuentas'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->isCapturista()) {
            $request->merge(['negocio_id' => $user->negocio_id]);
        }

        $request->validate([
            'negocio_id'        => 'required|exists:negocios,id',
            'categoria_id'      => 'required|exists:categorias,id',
            'cuenta_id'         => 'required|exists:cuentas,id',
            'monto'             => 'required|numeric|min:0',
            'fecha'             => 'required|date',
            'concepto'          => 'required',
            'forma_pago'        => 'required|in:efectivo,transferencia,tarjeta',
            'porcentaje_propina'=> 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->except(['tiene_iva', 'monto_iva', 'tiene_propina', 'monto_propina', 'porcentaje_propina']);
        $data = array_merge($data, $this->calcularPropinaeIva($request), ['user_id' => auth()->id()]);

        Gasto::create($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto registrado correctamente.');
    }

    public function edit(Gasto $gasto)
    {
        $this->autorizarAccesoNegocio($gasto->negocio_id);
        if ($redireccion = $this->redirigirACompraMeses($gasto)) {
            return $redireccion;
        }

        $negocios    = $this->negociosParaCaptura();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas     = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.edit', compact('gasto', 'negocios', 'categorias', 'proveedores', 'cuentas'));
    }

    public function update(Request $request, Gasto $gasto)
    {
        $this->autorizarAccesoNegocio($gasto->negocio_id);
        if ($redireccion = $this->redirigirACompraMeses($gasto)) {
            return $redireccion;
        }

        $user = auth()->user();
        if ($user->isCapturista()) {
            $request->merge(['negocio_id' => $user->negocio_id]);
        }

        $request->validate([
            'negocio_id'        => 'required|exists:negocios,id',
            'categoria_id'      => 'required|exists:categorias,id',
            'cuenta_id'         => 'required|exists:cuentas,id',
            'monto'             => 'required|numeric|min:0',
            'fecha'             => 'required|date',
            'concepto'          => 'required',
            'forma_pago'        => 'required|in:efectivo,transferencia,tarjeta',
            'porcentaje_propina'=> 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->except(['tiene_iva', 'monto_iva', 'tiene_propina', 'monto_propina', 'porcentaje_propina']);
        $data = array_merge($data, $this->calcularPropinaeIva($request));

        $gasto->update($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto actualizado correctamente.');
    }

    // Un gasto de una compra a meses se edita desde su plan para que no se desincronicen
    private function redirigirACompraMeses(Gasto $gasto)
    {
        if (!$gasto->compraMeses) {
            return null;
        }
        return redirect()->route('compras-meses.edit', $gasto->compraMeses)
            ->with('exito', 'Este gasto es una compra a meses: edítalo o elimínalo desde aquí.');
    }

    private function calcularPropinaeIva(Request $request): array
    {
        $monto = (float) $request->monto;
        $result = [];

        // Calcular propina
        if ($request->boolean('tiene_propina')) {
            $pct             = (float) ($request->porcentaje_propina ?? 20);
            $monto_propina   = round($monto * $pct / (100 + $pct), 2);
            $base_para_iva   = $monto - $monto_propina;
            $result['tiene_propina']     = true;
            $result['porcentaje_propina']= $pct;
            $result['monto_propina']     = $monto_propina;
        } else {
            $base_para_iva = $monto;
            $result['tiene_propina']     = false;
            $result['porcentaje_propina']= 0;
            $result['monto_propina']     = 0;
        }

        // Calcular IVA sobre la base (sin propina)
        if ($request->boolean('tiene_iva')) {
            $result['tiene_iva']  = true;
            $result['monto_iva']  = round($base_para_iva * 16 / 116, 2);
        } else {
            $result['tiene_iva']  = false;
            $result['monto_iva']  = 0;
        }

        return $result;
    }

    public function destroy(Gasto $gasto)
    {
        $this->autorizarAccesoNegocio($gasto->negocio_id);
        if ($redireccion = $this->redirigirACompraMeses($gasto)) {
            return $redireccion;
        }
        $gasto->delete();
        return redirect()->route('gastos.index')->with('exito', 'Gasto eliminado correctamente.');
    }
}
