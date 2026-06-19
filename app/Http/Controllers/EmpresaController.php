<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Negocio;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function index()
    {
        $empresas = Empresa::withCount("empleados")->with("negocio")->orderBy('negocio_id')->orderBy('nombre')->get();
        return view('empresas.index', compact('empresas'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        return view('empresas.create', compact('negocios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre'     => 'required|string|max:255',
        ]);

        Empresa::create($request->only('negocio_id', 'nombre'));
        return redirect()->route('empresas.index')->with('exito', 'Empresa registrada correctamente.');
    }

    public function edit(Empresa $empresa)
    {
        $negocios = Negocio::all();
        return view('empresas.edit', compact('empresa', 'negocios'));
    }

    public function update(Request $request, Empresa $empresa)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre'     => 'required|string|max:255',
        ]);

        $empresa->update($request->only('negocio_id', 'nombre'));
        return redirect()->route('empresas.index')->with('exito', 'Empresa actualizada correctamente.');
    }

    public function destroy(Empresa $empresa)
    {
        $empresa->delete();
        return redirect()->route('empresas.index')->with('exito', 'Empresa eliminada.');
    }
}
