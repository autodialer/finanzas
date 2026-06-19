<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Negocio;
use App\Models\Banco;
use Illuminate\Http\Request;

class CuentaController extends Controller
{
    public function index()
    {
        $cuentas = $this->aplicarFiltroNegocio(Cuenta::with('negocio', 'banco'))->get();
        return view('cuentas.index', compact('cuentas'));
    }

    public function create()
    {
        $negocios = $this->negociosVisibles();
        $bancos = Banco::all();
        return view('cuentas.create', compact('negocios', 'bancos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'tipo' => 'required|in:efectivo,banco',
            'nombre' => 'required',
        ]);
        Cuenta::create($request->all());
        return redirect()->route('cuentas.index')->with('exito', 'Cuenta creada correctamente.');
    }

    public function edit(Cuenta $cuenta)
    {
        $negocios = $this->negociosVisibles();
        $bancos = Banco::all();
        return view('cuentas.edit', compact('cuenta', 'negocios', 'bancos'));
    }

    public function update(Request $request, Cuenta $cuenta)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'tipo' => 'required|in:efectivo,banco',
            'nombre' => 'required',
        ]);
        $cuenta->update($request->all());
        return redirect()->route('cuentas.index')->with('exito', 'Cuenta actualizada correctamente.');
    }

    public function destroy(Cuenta $cuenta)
    {
        $cuenta->delete();
        return redirect()->route('cuentas.index')->with('exito', 'Cuenta eliminada correctamente.');
    }
}
