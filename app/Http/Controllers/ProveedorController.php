<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Negocio;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::with('negocio')->get();
        return view('proveedores.index', compact('proveedores'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        return view('proveedores.create', compact('negocios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre' => 'required',
        ]);
        Proveedor::create($request->all());
        return redirect()->route('proveedores.index')->with('exito', 'Proveedor creado correctamente.');
    }

    public function edit(Proveedor $proveedor)
    {
        $negocios = Negocio::all();
        return view('proveedores.edit', compact('proveedor', 'negocios'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre' => 'required',
        ]);
        $proveedor->update($request->all());
        return redirect()->route('proveedores.index')->with('exito', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->delete();
        return redirect()->route('proveedores.index')->with('exito', 'Proveedor eliminado correctamente.');
    }
}
