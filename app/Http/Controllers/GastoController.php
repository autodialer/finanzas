<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Area;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Cuenta;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index()
    {
        $gastos = Gasto::with('negocio', 'area', 'categoria', 'proveedor', 'cuenta')
            ->latest()
            ->get();
        return view('gastos.index', compact('gastos'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        $areas = Area::with('negocio')->get();
        $categorias = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::all();
        $cuentas = Cuenta::with('negocio')->get();
        return view('gastos.create', compact('negocios', 'areas', 'categorias', 'proveedores', 'cuentas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'categoria_id' => 'required|exists:categorias,id',
            'cuenta_id' => 'required|exists:cuentas,id',
            'monto' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'concepto' => 'required',
            'forma_pago' => 'required|in:efectivo,transferencia,tarjeta',
        ]);

        Gasto::create($request->all());
        return redirect()->route('gastos.index')->with('exito', 'Gasto registrado correctamente.');
    }

    public function edit(Gasto $gasto)
    {
        $negocios = Negocio::all();
        $areas = Area::with('negocio')->get();
        $categorias = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::all();
        $cuentas = Cuenta::with('negocio')->get();
        return view('gastos.edit', compact('gasto', 'negocios', 'areas', 'categorias', 'proveedores', 'cuentas'));
    }

    public function update(Request $request, Gasto $gasto)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'categoria_id' => 'required|exists:categorias,id',
            'cuenta_id' => 'required|exists:cuentas,id',
            'monto' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'concepto' => 'required',
            'forma_pago' => 'required|in:efectivo,transferencia,tarjeta',
        ]);

        $gasto->update($request->all());
        return redirect()->route('gastos.index')->with('exito', 'Gasto actualizado correctamente.');
    }

    public function destroy(Gasto $gasto)
    {
        $gasto->delete();
        return redirect()->route('gastos.index')->with('exito', 'Gasto eliminado correctamente.');
    }
}
