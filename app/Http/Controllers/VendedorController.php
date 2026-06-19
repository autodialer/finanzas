<?php

namespace App\Http\Controllers;

use App\Models\Vendedor;
use Illuminate\Http\Request;

class VendedorController extends Controller
{
    public function index()
    {
        $vendedores = Vendedor::all();
        return view('vendedores.index', compact('vendedores'));
    }

    public function create()
    {
        return view('vendedores.create');
    }

    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required']);
        Vendedor::create($request->all());
        return redirect()->route('vendedores.index')->with('exito', 'Vendedor creado correctamente.');
    }

    public function edit(Vendedor $vendedor)
    {
        return view('vendedores.edit', compact('vendedor'));
    }

    public function update(Request $request, Vendedor $vendedor)
    {
        $request->validate(['nombre' => 'required']);
        $vendedor->update($request->all());
        return redirect()->route('vendedores.index')->with('exito', 'Vendedor actualizado correctamente.');
    }

    public function destroy(Vendedor $vendedor)
    {
        $vendedor->delete();
        return redirect()->route('vendedores.index')->with('exito', 'Vendedor eliminado correctamente.');
    }
}
