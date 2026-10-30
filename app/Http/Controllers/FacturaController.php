<?php

namespace App\Http\Controllers;

use App\Models\Cfdi;
use App\Models\Contribuyente;
use App\Models\Gasto;
use App\Services\Sat\CfdiImportador;
use App\Services\Sat\ConciliadorFacturas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

// Facturas descargadas del SAT y gastos que todavía no tienen factura. Solo administradores.
class FacturaController extends Controller
{
    public function __construct(private ConciliadorFacturas $conciliador)
    {
    }

    public function index(Request $request)
    {
        $contribuyentes = Contribuyente::orderBy('nombre')->get();
        $contribuyente = $this->contribuyenteElegido($request, $contribuyentes);
        [$inicio, $fin, $mes] = $this->mes($request);
        $direccion = in_array($request->direccion, ['emitido', 'recibido'], true) ? $request->direccion : 'recibido';
        $vinculo = $request->vinculo;

        $facturas = collect();
        $totales = ['facturas' => 0, 'total' => 0, 'iva' => 0];
        if ($contribuyente) {
            $query = Cfdi::with('gasto')
                ->where('contribuyente_id', $contribuyente->id)
                ->where('direccion', $direccion)
                ->whereBetween('fecha', [$inicio, $fin])
                ->when($vinculo === 'libres', fn ($q) => $q->whereNull('gasto_id'))
                ->when($vinculo === 'vinculadas', fn ($q) => $q->whereNotNull('gasto_id'))
                ->orderBy('fecha');

            $facturas = $query->get();
            $vigentes = $facturas->where('estado', 'vigente')->whereIn('tipo', ['I', 'E']);
            $signo = fn (Cfdi $c) => $c->tipo === 'E' ? -1 : 1;
            $totales = [
                'facturas' => $facturas->count(),
                'total'    => $vigentes->sum(fn ($c) => $signo($c) * $c->totalMxn()),
                'iva'      => $vigentes->sum(fn ($c) => $signo($c) * (float) $c->iva_trasladado * ($c->totalMxn() / max((float) $c->total, 0.01))),
            ];
        }

        return view('facturas.index', compact('contribuyentes', 'contribuyente', 'facturas', 'totales', 'mes', 'direccion', 'vinculo'));
    }

    public function xml(Cfdi $cfdi)
    {
        abort_unless($cfdi->archivo && Storage::disk('local')->exists($cfdi->archivo), 404, 'El XML no está guardado.');
        return Storage::disk('local')->download($cfdi->archivo, "{$cfdi->uuid}.xml", ['Content-Type' => 'application/xml']);
    }

    // Carga manual de XML o ZIP (por ejemplo, los que se bajan del portal del SAT o llegan por correo)
    public function subir(Request $request, CfdiImportador $importador)
    {
        $request->validate(['archivos' => 'required|array|max:50', 'archivos.*' => 'file|max:51200']);

        $total = ['nuevas' => 0, 'actualizadas' => 0, 'errores' => []];
        foreach ($request->file('archivos') as $archivo) {
            $nombre = $archivo->getClientOriginalName();
            if (!preg_match('/\.(xml|zip)$/i', $nombre)) {
                $total['errores'][] = "{$nombre}: solo se aceptan archivos .xml o .zip.";
                continue;
            }
            try {
                $resultado = $importador->importarArchivo($archivo->getRealPath(), $nombre);
            } catch (InvalidArgumentException $e) {
                $total['errores'][] = "{$nombre}: {$e->getMessage()}";
                continue;
            }
            $total['nuevas'] += $resultado['nuevas'];
            $total['actualizadas'] += $resultado['actualizadas'];
            $total['errores'] = array_merge($total['errores'], $resultado['errores']);
        }

        $vinculadas = Contribuyente::all()->sum(fn ($c) => $this->conciliador->conciliar($c));

        $redireccion = back()->with('exito', "Facturas: {$total['nuevas']} nuevas, {$total['actualizadas']} ya existían. {$vinculadas} vinculadas a gastos.");
        return $total['errores'] ? $redireccion->withErrors(['archivos' => array_slice($total['errores'], 0, 20)]) : $redireccion;
    }

    /** Gastos del mes sin factura, con la fecha límite para pedirla y las facturas que podrían corresponder. */
    public function faltantes(Request $request)
    {
        $contribuyentes = Contribuyente::with('negocios')->orderBy('nombre')->get();
        $contribuyente = $this->contribuyenteElegido($request, $contribuyentes);
        [$inicio, $fin, $mes] = $this->mes($request);

        $gastos = collect();
        $pendientesPorMes = collect();
        $sinFacturaMarcados = collect();
        if ($contribuyente) {
            $gastos = $this->conciliador->gastosSinFactura($contribuyente, $inicio, $fin)
                ->with('categoria', 'cuenta', 'negocio')
                ->orderBy('fecha')
                ->get()
                ->map(function (Gasto $gasto) use ($contribuyente) {
                    $gasto->limite = ConciliadorFacturas::fechaLimite($gasto);
                    $gasto->candidatas = $this->conciliador->candidatas($gasto, $contribuyente)->take(3);
                    return $gasto;
                });

            // Resumen de los últimos 6 meses para no perder de vista lo atrasado
            $pendientesPorMes = $this->conciliador->gastosSinFactura($contribuyente, now()->subMonths(5)->startOfMonth())
                ->get(['fecha', 'monto'])
                ->groupBy(fn ($g) => Carbon::parse($g->fecha)->format('Y-m'))
                ->map(fn ($g) => ['gastos' => $g->count(), 'monto' => $g->sum('monto')])
                ->sortKeysDesc();

            $sinFacturaMarcados = Gasto::whereIn('negocio_id', $contribuyente->negocios->pluck('id'))
                ->where('requiere_factura', false)
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->orderBy('fecha')
                ->get();
        }

        return view('facturas.faltantes', compact('contribuyentes', 'contribuyente', 'gastos', 'pendientesPorMes', 'sinFacturaMarcados', 'mes'));
    }

    public function vincular(Request $request)
    {
        $request->validate(['gasto_id' => 'required|exists:gastos,id', 'cfdi_id' => 'required|exists:cfdis,id']);

        $cfdi = Cfdi::findOrFail($request->cfdi_id);
        $gasto = Gasto::findOrFail($request->gasto_id);

        if ($cfdi->gasto_id || $cfdi->direccion !== 'recibido') {
            return back()->withErrors(['vincular' => 'Esa factura ya está vinculada o no es una factura recibida.']);
        }
        if (!$cfdi->contribuyente->negocios()->whereKey($gasto->negocio_id)->exists()) {
            return back()->withErrors(['vincular' => "La factura es de {$cfdi->contribuyente->rfc} y el gasto es de un negocio de otro RFC."]);
        }

        $cfdi->update(['gasto_id' => $gasto->id, 'vinculo' => 'manual']);
        return back()->with('exito', "Gasto \"{$gasto->concepto}\" vinculado con la factura de {$cfdi->nombre_emisor}.");
    }

    public function desvincular(Cfdi $cfdi)
    {
        $cfdi->update(['gasto_id' => null, 'vinculo' => null]);
        return back()->with('exito', 'Factura desvinculada del gasto.');
    }

    public function sinFactura(Request $request, Gasto $gasto)
    {
        $request->validate(['motivo' => 'required|string|max:255']);
        $gasto->forceFill(['requiere_factura' => false, 'motivo_sin_factura' => $request->motivo])->save();
        return back()->with('exito', "\"{$gasto->concepto}\" marcado como gasto sin factura ({$request->motivo}).");
    }

    public function requiereFactura(Gasto $gasto)
    {
        $gasto->forceFill(['requiere_factura' => true, 'motivo_sin_factura' => null])->save();
        return back()->with('exito', "\"{$gasto->concepto}\" vuelve a requerir factura.");
    }

    public function conciliar(Contribuyente $contribuyente)
    {
        $vinculadas = $this->conciliador->conciliar($contribuyente);
        return back()->with('exito', "{$vinculadas} factura(s) vinculadas automáticamente.");
    }

    private function contribuyenteElegido(Request $request, $contribuyentes): ?Contribuyente
    {
        return $contribuyentes->firstWhere('id', (int) $request->contribuyente_id) ?? $contribuyentes->first();
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function mes(Request $request): array
    {
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->mes) ? $request->mes : now()->format('Y-m');
        $inicio = Carbon::createFromFormat('!Y-m', $mes)->startOfMonth();
        return [$inicio, $inicio->copy()->endOfMonth(), $mes];
    }
}
