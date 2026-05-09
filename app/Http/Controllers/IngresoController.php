<?php

namespace App\Http\Controllers;

use App\Models\Ingreso;
use App\Models\Negocio;
use App\Models\Area;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cuenta;
use Illuminate\Http\Request;

class IngresoController extends Controller
{
    public function index()
    {
        $ingresos = Ingreso::with('negocio', 'area', 'categoria', 'cliente', 'cuenta')
            ->latest()
            ->get();
        return view('ingresos.index', compact('ingresos'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        $areas = Area::with('negocio')->get();
        $categorias = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->get();
        $clientes = Cliente::all();
        $cuentas = Cuenta::with('negocio')->get();
        return view('ingresos.create', compact('negocios', 'areas', 'categorias', 'clientes', 'cuentas'));
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

        Ingreso::create($request->all());
        return redirect()->route('ingresos.index')->with('exito', 'Ingreso registrado correctamente.');
    }

    public function edit(Ingreso $ingreso)
    {
        $negocios = Negocio::all();
        $areas = Area::with('negocio')->get();
        $categorias = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->get();
        $clientes = Cliente::all();
        $cuentas = Cuenta::with('negocio')->get();
        return view('ingresos.edit', compact('ingreso', 'negocios', 'areas', 'categorias', 'clientes', 'cuentas'));
    }

    public function update(Request $request, Ingreso $ingreso)
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

        $ingreso->update($request->all());
        return redirect()->route('ingresos.index')->with('exito', 'Ingreso actualizado correctamente.');
    }

    public function destroy(Ingreso $ingreso)
    {
        $ingreso->delete();
        return redirect()->route('ingresos.index')->with('exito', 'Ingreso eliminado correctamente.');
    }
}
