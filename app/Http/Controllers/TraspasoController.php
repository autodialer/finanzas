<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Traspaso;
use Illuminate\Http\Request;

class TraspasoController extends Controller
{
    public function index()
    {
        $ids = $this->negociosPermitidos();

        $query = Traspaso::with('cuentaOrigen.negocio', 'cuentaDestino.negocio')->latest();

        if ($ids !== null) {
            // Excluir traspasos que involucren cuentas de negocios privados
            $cuentasVisibles = Cuenta::whereIn('negocio_id', $ids)->pluck('id');
            $query->whereIn('cuenta_origen_id', $cuentasVisibles)
                  ->whereIn('cuenta_destino_id', $cuentasVisibles);
        }

        $traspasos = $query->get();

        return view('traspasos.index', compact('traspasos'));
    }

    public function create()
    {
        $cuentas = $this->aplicarFiltroNegocio(Cuenta::with('negocio'))->orderBy('nombre')->get();
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
