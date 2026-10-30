<?php

namespace App\Http\Controllers;

use App\Models\Contribuyente;
use App\Models\Negocio;
use App\Models\SatSolicitud;
use App\Services\Sat\ConciliadorFacturas;
use App\Services\Sat\DescargaMasiva;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpCfdi\Credentials\Credential;
use Throwable;

// RFCs propios (personas morales y físicas), su e.firma y la descarga de sus facturas del SAT. Solo administradores.
class ContribuyenteController extends Controller
{
    public function index(ConciliadorFacturas $conciliador)
    {
        $contribuyentes = Contribuyente::with('negocios')->withCount('cfdis')->orderBy('nombre')->get();

        $inicioMes = now()->startOfMonth();
        $sinFactura = $contribuyentes->mapWithKeys(fn ($c) => [
            $c->id => $conciliador->gastosSinFactura($c, $inicioMes->copy()->subMonthNoOverflow())->count(),
        ]);

        return view('contribuyentes.index', compact('contribuyentes', 'sinFactura'));
    }

    public function create()
    {
        return view('contribuyentes.create', ['contribuyente' => new Contribuyente(['descarga_automatica' => true]), 'negocios' => $this->negocios()]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $efirma = $this->leerEfirma($request, $datos['rfc']);

        // Todo o nada: si falla la e.firma no queda un contribuyente a medias
        $contribuyente = DB::transaction(function () use ($datos, $efirma, $request) {
            $contribuyente = Contribuyente::create($datos);
            $this->asignarNegocios($contribuyente, $request->input('negocios', []));
            if ($efirma) {
                $contribuyente->guardarEfirma(...$efirma);
            }
            return $contribuyente;
        });

        return redirect()->route('contribuyentes.show', $contribuyente)->with('exito', "Contribuyente {$contribuyente->rfc} registrado.");
    }

    public function show(Contribuyente $contribuyente)
    {
        $contribuyente->load('negocios');
        $solicitudes = $contribuyente->solicitudes()->latest('id')->limit(30)->get();
        // Facturas por mes de los últimos 6 meses: [mes => [direccion => [facturas, total]]]
        $resumen = $contribuyente->cfdis()
            ->where('fecha', '>=', now()->subMonths(5)->startOfMonth())
            ->where('estado', 'vigente')
            ->get(['direccion', 'fecha', 'total', 'moneda', 'tipo_cambio', 'tipo'])
            ->groupBy(fn ($c) => $c->fecha->format('Y-m'))
            ->sortKeysDesc()
            ->map(fn ($mes) => $mes->groupBy('direccion')->map(fn ($g) => ['facturas' => $g->count(), 'total' => $g->sum(fn ($c) => $c->totalMxn())]));

        return view('contribuyentes.show', compact('contribuyente', 'solicitudes', 'resumen'));
    }

    public function edit(Contribuyente $contribuyente)
    {
        return view('contribuyentes.edit', ['contribuyente' => $contribuyente->load('negocios'), 'negocios' => $this->negocios()]);
    }

    public function update(Request $request, Contribuyente $contribuyente)
    {
        $datos = $this->validar($request, $contribuyente);
        $efirma = $this->leerEfirma($request, $datos['rfc']);

        DB::transaction(function () use ($contribuyente, $datos, $efirma, $request) {
            $contribuyente->update($datos);
            $this->asignarNegocios($contribuyente, $request->input('negocios', []));
            if ($efirma) {
                $contribuyente->guardarEfirma(...$efirma);
            }
        });

        return redirect()->route('contribuyentes.show', $contribuyente)->with('exito', 'Contribuyente actualizado.');
    }

    public function destroy(Contribuyente $contribuyente)
    {
        $contribuyente->delete();
        return redirect()->route('contribuyentes.index')->with('exito', 'Contribuyente eliminado (con sus facturas descargadas).');
    }

    // Presenta al SAT solicitudes de emitidos y recibidos para el periodo elegido
    public function descargar(Request $request, Contribuyente $contribuyente, DescargaMasiva $descarga)
    {
        $request->validate([
            'desde' => 'required|date|after_or_equal:' . now()->subYears(5)->startOfYear()->toDateString(),
            'hasta' => 'required|date|after_or_equal:desde|before_or_equal:today',
        ]);

        if (!$contribuyente->efirmaVigente()) {
            return back()->withErrors(['efirma' => 'Carga una e.firma vigente antes de descargar.']);
        }

        $desde = Carbon::parse($request->desde)->startOfDay();
        $hasta = Carbon::parse($request->hasta)->isToday() ? now()->subMinute() : Carbon::parse($request->hasta)->endOfDay();
        $solicitudes = $descarga->solicitar($contribuyente, $desde, $hasta);

        $errores = collect($solicitudes)->where('estado', 'error')->map(fn ($s) => "{$s->direccion}s: {$s->mensaje}");
        if ($errores->isNotEmpty()) {
            return back()->withErrors(['efirma' => $errores->all()]);
        }

        return back()->with('exito', 'Solicitudes enviadas al SAT. Se revisan solas cada hora; también puedes dar clic en "Verificar ahora" en unos minutos.');
    }

    public function verificar(Contribuyente $contribuyente, DescargaMasiva $descarga, ConciliadorFacturas $conciliador)
    {
        $solicitudes = $contribuyente->solicitudes()->whereIn('estado', SatSolicitud::ABIERTAS)->get();
        $nuevas = 0;
        foreach ($solicitudes as $solicitud) {
            $nuevas += $descarga->verificar($solicitud)->cfdis_nuevos;
        }
        $vinculadas = $nuevas > 0 ? $conciliador->conciliar($contribuyente) : 0;

        $abiertas = $contribuyente->solicitudes()->whereIn('estado', SatSolicitud::ABIERTAS)->count();
        $mensaje = "{$nuevas} factura(s) nuevas, {$vinculadas} vinculadas a gastos."
            . ($abiertas ? " {$abiertas} solicitud(es) siguen en proceso en el SAT." : '');

        return back()->with('exito', $mensaje);
    }

    private function validar(Request $request, ?Contribuyente $contribuyente = null): array
    {
        $request->merge(['rfc' => mb_strtoupper(trim((string) $request->rfc))]);

        $datos = $request->validate([
            'rfc'        => ['required', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', Rule::unique('contribuyentes', 'rfc')->ignore($contribuyente)],
            'nombre'     => 'required|string|max:255',
            'tipo'       => 'required|in:moral,fisica',
            'regimen'    => ['nullable', Rule::in(array_keys(Contribuyente::REGIMENES))],
            'negocios'   => 'array',
            'negocios.*' => 'exists:negocios,id',
            'cer'        => 'nullable|file|max:20',
            'key'        => 'nullable|file|max:20',
            'password'   => 'nullable|string|max:255',
        ], ['rfc.regex' => 'El RFC no tiene un formato válido.']);

        // Persona moral: RFC de 12 caracteres; física: 13
        if (strlen($datos['rfc']) !== ($datos['tipo'] === 'moral' ? 12 : 13)) {
            throw ValidationException::withMessages(['rfc' => 'El RFC de una persona ' . ($datos['tipo'] === 'moral' ? 'moral tiene 12' : 'física tiene 13') . ' caracteres.']);
        }

        return array_intersect_key($datos, array_flip(['rfc', 'nombre', 'tipo', 'regimen'])) + ['descarga_automatica' => $request->boolean('descarga_automatica')];
    }

    /** @return ?array{0: string, 1: string, 2: string, 3: string, 4: \DateTimeInterface} argumentos para guardarEfirma() */
    private function leerEfirma(Request $request, string $rfc): ?array
    {
        if (!$request->hasFile('cer') && !$request->hasFile('key')) {
            return null;
        }
        if (!$request->hasFile('cer') || !$request->hasFile('key') || (string) $request->password === '') {
            throw ValidationException::withMessages(['cer' => 'Para cargar la e.firma sube el .cer, el .key y escribe su contraseña.']);
        }

        $cer = (string) file_get_contents($request->file('cer')->getRealPath());
        $key = (string) file_get_contents($request->file('key')->getRealPath());

        try {
            $credencial = Credential::create($cer, $key, (string) $request->password);
        } catch (Throwable) {
            throw ValidationException::withMessages(['cer' => 'No se pudo abrir la e.firma: revisa la contraseña y que el .key corresponda al .cer.']);
        }

        $certificado = $credencial->certificate();
        $errores = match (true) {
            !$certificado->satType()->isFiel() => 'Ese certificado es un sello digital (CSD), no una e.firma.',
            !$certificado->validOn()           => 'La e.firma está vencida (venció el ' . $certificado->validToDateTime()->format('d/m/Y') . ').',
            $certificado->rfc() !== $rfc       => "La e.firma es del RFC {$certificado->rfc()}, no de {$rfc}.",
            default                            => null,
        };
        if ($errores) {
            throw ValidationException::withMessages(['cer' => $errores]);
        }

        // El número de certificado que muestra el SAT (20 dígitos); si no fuera legible, el hexadecimal
        $serie = $certificado->serialNumber()->bytesArePrintable() ? $certificado->serialNumber()->bytes() : $certificado->serialNumber()->hexadecimal();

        return [$cer, $key, (string) $request->password, mb_substr($serie, 0, 60), $certificado->validToDateTime()];
    }

    private function asignarNegocios(Contribuyente $contribuyente, array $ids): void
    {
        Negocio::where('contribuyente_id', $contribuyente->id)->whereNotIn('id', $ids)->update(['contribuyente_id' => null]);
        Negocio::whereIn('id', $ids)->update(['contribuyente_id' => $contribuyente->id]);
    }

    private function negocios()
    {
        return Negocio::with('contribuyente')->orderBy('nombre')->get();
    }
}
