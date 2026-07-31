<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Traspaso;
use Illuminate\Http\Request;

class TraspasoController extends Controller
{
    public function index(Request $request)
    {
        $claves = ['fecha_desde', 'fecha_hasta', 'cuenta_id'];

        // Los filtros se guardan en sesión para que sobrevivan al eliminar un traspaso
        if ($request->has('fecha_desde') || $request->has('limpiar')) {
            $filtros = $request->boolean('limpiar') ? [] : $request->only($claves);
            session(['traspasos_filtros' => array_filter($filtros, fn($v) => $v !== null && $v !== '')]);
        }

        $filtros = session('traspasos_filtros', []);

        $ids = $this->negociosPermitidos();

        $query = Traspaso::with('cuentaOrigen.negocio', 'cuentaDestino.negocio')
            ->orderBy('fecha', 'desc')
            ->orderBy('created_at', 'desc');

        $cuentasQuery = Cuenta::with('negocio');

        if ($ids !== null) {
            // Excluir traspasos que involucren cuentas de negocios privados
            $cuentasVisibles = Cuenta::whereIn('negocio_id', $ids)->pluck('id');
            $query->whereIn('cuenta_origen_id', $cuentasVisibles)
                  ->whereIn('cuenta_destino_id', $cuentasVisibles);
            $cuentasQuery->whereIn('negocio_id', $ids);
        }

        $cuentas = $cuentasQuery->orderBy('nombre')->get();

        if (!empty($filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $filtros['fecha_hasta']);
        }

        if (!empty($filtros['cuenta_id'])) {
            if ($cuentas->contains('id', (int) $filtros['cuenta_id'])) {
                $cuentaId = $filtros['cuenta_id'];
                // La cuenta puede ser origen o destino del traspaso
                $query->where(function ($q) use ($cuentaId) {
                    $q->where('cuenta_origen_id', $cuentaId)
                      ->orWhere('cuenta_destino_id', $cuentaId);
                });
            } else {
                // Cuenta no visible para este usuario: se ignora el filtro
                unset($filtros['cuenta_id']);
            }
        }

        $traspasos = $query->get();

        return view('traspasos.index', compact('traspasos', 'cuentas', 'filtros'));
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
