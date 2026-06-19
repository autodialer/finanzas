<?php

namespace App\Http\Controllers;

use App\Models\Traspaso;
use App\Models\Cuenta;
use Illuminate\Http\Request;

class TraspasoController extends Controller
{
    public function index()
    {
        $traspasos = Traspaso::with('cuentaOrigen.negocio', 'cuentaDestino.negocio')
            ->latest()
            ->get();

        return view('traspasos.index', compact('traspasos'));
    }

    public function create()
    {
        $cuentas = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('traspasos.create', compact('cuentas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required|date',
            'cuenta_origen_id'  => 'required|exists:cuentas,id',
            'cuenta_destino_id' => 'required|exists:cuentas,id|different:cuenta_origen_id',
            'monto'             => 'required|numeric|min:0.01',
        ], [
            'cuenta_destino_id.different' => 'La cuenta destino debe ser diferente a la cuenta origen.',
        ]);

        Traspaso::create($request->all());

        return redirect()->route('traspasos.index')->with('exito', 'Traspaso registrado correctamente.');
    }

    public function destroy(Traspaso $traspaso)
    {
        $traspaso->delete();
        return redirect()->route('traspasos.index')->with('exito', 'Traspaso eliminado correctamente.');
    }
}
