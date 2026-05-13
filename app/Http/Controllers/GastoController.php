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

        $data = $request->except(['tiene_iva', 'monto_iva']);
        $data = array_merge($data, $this->calcularIva($request));

        Gasto::create($data);
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

        $data = $request->except(['tiene_iva', 'monto_iva']);
        $data = array_merge($data, $this->calcularIva($request));

        $gasto->update($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto actualizado correctamente.');
    }

    private function calcularIva(Request $request): array
    {
        $forma = $request->forma_pago;
        $monto = (float) $request->monto;

        // Transferencia y tarjeta siempre llevan IVA
        if (in_array($forma, ['transferencia', 'tarjeta'])) {
            $monto_iva = round($monto * 16 / 116, 2);
            return ['tiene_iva' => true, 'monto_iva' => $monto_iva];
        }

        // Efectivo: el usuario decide
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
