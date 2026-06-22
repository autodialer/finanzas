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
    public function index()
    {
        $gastos = $this->aplicarFiltroReportes(
            Gasto::with('negocio', 'categoria', 'proveedor', 'cuenta', 'user')
        )->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();
        return view('gastos.index', compact('gastos'));
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
        $negocios    = $this->negociosParaCaptura();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas     = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.edit', compact('gasto', 'negocios', 'categorias', 'proveedores', 'cuentas'));
    }

    public function update(Request $request, Gasto $gasto)
    {
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
        $gasto->delete();
        return redirect()->route('gastos.index')->with('exito', 'Gasto eliminado correctamente.');
    }
}
