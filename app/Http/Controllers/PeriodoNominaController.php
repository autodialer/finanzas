<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Nomina;
use App\Models\PeriodoNomina;
use App\Services\CalculoNominaService;
use Illuminate\Http\Request;

class PeriodoNominaController extends Controller
{
    public function index()
    {
        $periodos = $this->aplicarFiltroNegocio(
            PeriodoNomina::with('negocio', 'cuenta', 'empresa', 'nominas')
        )->latest()->get();
        return view('nominas.index', compact('periodos'));
    }

    public function create()
    {
        $negocios            = $this->negociosVisibles();
        $cuentas             = Cuenta::with('negocio')->get();
        $empresas            = Empresa::orderBy('nombre')->get();
        $negociosConEmpresas = Empresa::select('negocio_id')->distinct()->pluck('negocio_id')->toArray();

        $empresasPorNegocio = $empresas->groupBy('negocio_id')
            ->map(fn($g) => $g->map(fn($e) => ['id' => $e->id, 'nombre' => $e->nombre])->values())
            ->toArray();

        $cuentasJson  = $cuentas->map(fn($c) => [
            'id'             => $c->id,
            'nombre'         => $c->nombre,
            'negocio_id'     => $c->negocio_id,
            'negocio_nombre' => $c->negocio->nombre ?? '',
        ])->values()->toJson();

        $empresasJson = json_encode($empresasPorNegocio);

        return view('nominas.create', compact(
            'negocios', 'cuentas', 'empresas', 'negociosConEmpresas',
            'cuentasJson', 'empresasJson'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id'   => 'required|exists:negocios,id',
            'cuenta_id'    => 'required|exists:cuentas,id',
            'nombre'       => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'empresa_id'   => 'nullable|exists:empresas,id',
            'tipo_periodo' => 'nullable|in:semanal,quincenal',
        ]);

        $periodo = PeriodoNomina::create($request->only(
            'negocio_id', 'cuenta_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'empresa_id', 'tipo_periodo'
        ));

        $calculadora = new CalculoNominaService();

        $query = Empleado::where('negocio_id', $periodo->negocio_id)->where('activo', true);
        if ($periodo->empresa_id) {
            $query->where('empresa_id', $periodo->empresa_id);
        }
        if ($periodo->tipo_periodo) {
            $query->where('periodo_pago', $periodo->tipo_periodo);
        }

        foreach ($query->get() as $empleado) {
            $calc = $calculadora->calcular((float) $empleado->salario);
            Nomina::create([
                'periodo_id'    => $periodo->id,
                'empleado_id'   => $empleado->id,
                'monto'         => $calc['salario_bruto'],
                'isr'           => $calc['isr'],
                'imss_empleado' => $calc['imss_empleado'],
                'salario_neto'  => $calc['salario_neto'],
            ]);
        }

        return redirect()->route('nominas.show', $periodo)->with('exito', 'Período creado y nómina generada.');
    }

    public function show(PeriodoNomina $nomina)
    {
        $nomina->load('negocio', 'cuenta', 'empresa', 'nominas.empleado', 'nominas.cuenta');
        $cuentas = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('nominas.show', compact('nomina', 'cuentas'));
    }

    public function updateNomina(Request $request, Nomina $linea)
    {
        $request->validate([
            'monto'         => 'required|numeric|min:0',
            'isr'           => 'required|numeric|min:0',
            'imss_empleado' => 'required|numeric|min:0',
            'notas'         => 'nullable|string',
            'cuenta_id'     => 'nullable|exists:cuentas,id',
        ]);

        $bruto = (float) $request->monto;
        $isr   = (float) $request->isr;
        $imss  = (float) $request->imss_empleado;

        $linea->update([
            'monto'         => $bruto,
            'isr'           => $isr,
            'imss_empleado' => $imss,
            'salario_neto'  => round($bruto - $isr - $imss, 2),
            'notas'         => $request->notas,
            'cuenta_id'     => $request->cuenta_id ?: null,
        ]);

        return back()->with('exito', 'Nómina actualizada.');
    }

    public function recalcularLinea(Nomina $linea)
    {
        $calc = (new CalculoNominaService())->calcular((float) $linea->monto);
        $linea->update([
            'isr'           => $calc['isr'],
            'imss_empleado' => $calc['imss_empleado'],
            'salario_neto'  => $calc['salario_neto'],
        ]);
        return back()->with('exito', 'Impuestos recalculados.');
    }

    public function cerrar(PeriodoNomina $nomina)
    {
        if ($nomina->estado === 'cerrado') {
            return back()->with('error', 'Este período ya está cerrado.');
        }

        $nomina->load('nominas.empleado', 'nominas.cuenta', 'cuenta');

        $categoria = Categoria::firstOrCreate(
            ['nombre' => 'Nomina', 'tipo' => 'gasto'],
            ['nombre' => 'Nomina', 'tipo' => 'gasto']
        );

        foreach ($nomina->nominas as $linea) {
            $cuentaEfectiva = $linea->cuenta ?? $nomina->cuenta;

            Gasto::create([
                'negocio_id'   => $cuentaEfectiva->negocio_id,
                'cuenta_id'    => $cuentaEfectiva->id,
                'categoria_id' => $categoria->id,
                'monto'        => $linea->monto,
                'fecha'        => $nomina->fecha_fin,
                'concepto'     => 'Nomina: ' . $linea->empleado->nombre . ' - ' . $nomina->nombre,
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
