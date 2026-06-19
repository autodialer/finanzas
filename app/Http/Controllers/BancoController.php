<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use Illuminate\Http\Request;

class BancoController extends Controller
{
    public function index()
    {
        $bancos = Banco::all();
        return view('bancos.index', compact('bancos'));
    }

    public function create()
    {
        return view('bancos.create');
    }

    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required']);
        Banco::create($request->all());
        return redirect()->route('bancos.index')->with('exito', 'Banco creado correctamente.');
    }

    public function edit(Banco $banco)
    {
        return view('bancos.edit', compact('banco'));
    }

    public function update(Request $request, Banco $banco)
    {
        $request->validate(['nombre' => 'required']);
        $banco->update($request->all());
        return redirect()->route('bancos.index')->with('exito', 'Banco actualizado correctamente.');
    }

    public function destroy(Banco $banco)
    {
        $banco->delete();
        return redirect()->route('bancos.index')->with('exito', 'Banco eliminado correctamente.');
    }
}
