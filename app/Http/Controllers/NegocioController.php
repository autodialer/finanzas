<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use Illuminate\Http\Request;

class NegocioController extends Controller
{
    public function index()
    {
        $negocios = Negocio::all();
        return view('negocios.index', compact('negocios'));
    }

    public function create()
    {
        return view('negocios.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required',
            'descripcion' => 'nullable',
        ]);
        Negocio::create($request->all());
        return redirect()->route('negocios.index')->with('exito', 'Negocio creado correctamente.');
    }

    public function edit(Negocio $negocio)
    {
        return view('negocios.edit', compact('negocio'));
    }

    public function update(Request $request, Negocio $negocio)
    {
        $request->validate(['nombre' => 'required']);
        $negocio->update($request->all());
        return redirect()->route('negocios.index')->with('exito', 'Negocio actualizado correctamente.');
    }

    public function destroy(Negocio $negocio)
    {
        $negocio->delete();
        return redirect()->route('negocios.index')->with('exito', 'Negocio eliminado correctamente.');
    }
}
