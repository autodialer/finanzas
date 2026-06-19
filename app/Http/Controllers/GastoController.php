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
        $gastos = Gasto::with('negocio', 'categoria', 'proveedor', 'cuenta', 'user')
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('gastos.index', compact('gastos'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        $categorias = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.create', compact('negocios', 'categorias', 'proveedores', 'cuentas'));
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

        $data = $request->except(['tiene_iva', 'monto_iva']);
        $data = array_merge($data, $this->calcularIva($request), ['user_id' => auth()->id()]);

        Gasto::create($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto registrado correctamente.');
    }

    public function edit(Gasto $gasto)
    {
        $negocios = Negocio::all();
        $categorias = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.edit', compact('gasto', 'negocios', 'categorias', 'proveedores', 'cuentas'));
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

        $data = $request->except(['tiene_iva', 'monto_iva']);
        $data = array_merge($data, $this->calcularIva($request));

        $gasto->update($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto actualizado correctamente.');
    }

    private function calcularIva(Request $request): array
    {
        $monto = (float) $request->monto;

        if ($request->boolean('tiene_iva')) {
            $monto_iva = round($monto * 16 / 116, 2);
            return ['tiene_iva' => true, 'monto_iva' => $monto_iva];
        }

        return ['tiene_iva' => false, 'monto_iva' => 0];
    }

    public function destroy(Gasto $gasto)
    {
        $gasto->delete();
        return redirect()->route('gastos.index')->with('exito', 'Gasto eliminado correctamente.');
    }
}
