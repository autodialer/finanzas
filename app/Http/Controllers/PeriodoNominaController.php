<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Cuenta;
use App\Models\Nomina;
use App\Models\PeriodoNomina;
use Illuminate\Http\Request;

class PeriodoNominaController extends Controller
{
    public function index()
    {
        $periodos = PeriodoNomina::with('negocio', 'cuenta', 'nominas')
            ->latest()
            ->get();
        return view('nominas.index', compact('periodos'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        $cuentas  = Cuenta::with('negocio')->get();
        return view('nominas.create', compact('negocios', 'cuentas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id'   => 'required|exists:negocios,id',
            'cuenta_id'    => 'required|exists:cuentas,id',
            'nombre'       => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $periodo = PeriodoNomina::create($request->only('negocio_id', 'cuenta_id', 'nombre', 'fecha_inicio', 'fecha_fin'));

        // Generar una línea de nómina por cada empleado activo del negocio
        $empleados = Empleado::where('negocio_id', $periodo->negocio_id)->where('activo', true)->get();
        foreach ($empleados as $empleado) {
            Nomina::create([
                'periodo_id'  => $periodo->id,
                'empleado_id' => $empleado->id,
                'monto'       => $empleado->salario,
            ]);
        }

        return redirect()->route('nominas.show', $periodo)->with('exito', 'Período creado y nómina generada.');
    }

    public function show(PeriodoNomina $nomina)
    {
        $nomina->load('negocio', 'cuenta', 'nominas.empleado');
        return view('nominas.show', compact('nomina'));
    }

    public function updateNomina(Request $request, Nomina $linea)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0',
            'notas' => 'nullable|string',
        ]);

        $linea->update($request->only('monto', 'notas'));
        return back()->with('exito', 'Monto actualizado.');
    }

    public function cerrar(PeriodoNomina $nomina)
    {
        if ($nomina->estado === 'cerrado') {
            return back()->with('error', 'Este período ya está cerrado.');
        }

        $nomina->load('nominas.empleado');

        $categoria = Categoria::firstOrCreate(
            ['nombre' => 'Nómina', 'tipo' => 'gasto'],
            ['nombre' => 'Nómina', 'tipo' => 'gasto']
        );

        // Registrar un Gasto por empleado
        foreach ($nomina->nominas as $linea) {
            Gasto::create([
                'negocio_id'   => $nomina->negocio_id,
                'cuenta_id'    => $nomina->cuenta_id,
                'categoria_id' => $categoria->id,
                'monto'        => $linea->monto,
                'fecha'        => $nomina->fecha_fin,
                'concepto'     => 'Nómina: ' . $linea->empleado->nombre . ' — ' . $nomina->nombre,
                'forma_pago'   => 'transferencia',
            ]);
        }

        $nomina->update(['estado' => 'cerrado']);
        return redirect()->route('nominas.index')->with('exito', 'Nómina cerrada y gastos registrados correctamente.');
    }

    public function destroy(PeriodoNomina $nomina)
    {
        if ($nomina->estado === 'cerrado') {
            return back()->with('error', 'No se puede eliminar un período cerrado.');
        }
        $nomina->delete();
        return redirect()->route('nominas.index')->with('exito', 'Período eliminado.');
    }
}
