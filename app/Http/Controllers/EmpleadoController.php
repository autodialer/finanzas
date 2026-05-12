<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Negocio;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function index()
    {
        $empleados = Empleado::with('negocio')->orderBy('negocio_id')->orderBy('nombre')->get();
        return view('empleados.index', compact('empleados'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        return view('empleados.create', compact('negocios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre'     => 'required|string|max:255',
            'cargo'      => 'nullable|string|max:255',
            'salario'    => 'required|numeric|min:0',
        ]);

        Empleado::create($request->all());
        return redirect()->route('empleados.index')->with('exito', 'Empleado registrado correctamente.');
    }

    public function edit(Empleado $empleado)
    {
        $negocios = Negocio::all();
        return view('empleados.edit', compact('empleado', 'negocios'));
    }

    public function update(Request $request, Empleado $empleado)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre'     => 'required|string|max:255',
            'cargo'      => 'nullable|string|max:255',
            'salario'    => 'required|numeric|min:0',
        ]);

        $empleado->update($request->all());
        return redirect()->route('empleados.index')->with('exito', 'Empleado actualizado correctamente.');
    }

    public function destroy(Empleado $empleado)
    {
        $empleado->delete();
        return redirect()->route('empleados.index')->with('exito', 'Empleado eliminado correctamente.');
    }
}
