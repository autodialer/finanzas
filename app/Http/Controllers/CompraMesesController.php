<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\CompraMeses;
use App\Models\Cuenta;
use App\Models\Gasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Compras a meses sin intereses: un gasto por el monto original + el plan de mensualidades.
class CompraMesesController extends Controller
{
    // Meses que muestra el simulador del formulario
    private const MESES_SIMULADOR = 12;

    public function index(Request $request)
    {
        $query = $this->comprasMesesVisibles()->with('cuenta.negocio', 'gasto.categoria');

        if ($request->filled('cuenta_id')) {
            $query->where('cuenta_id', $request->cuenta_id);
        }

        $compras = $query->orderBy('fecha_compra', 'desc')->get();
        [$terminadas, $activas] = $compras->partition(fn ($c) => $c->terminada);
        $cuentas = $this->cuentasTarjeta();

        return view('compras_meses.index', compact('activas', 'terminadas', 'cuentas'));
    }

    public function create()
    {
        return view('compras_meses.create', $this->datosFormulario(new CompraMeses()));
    }

    public function store(Request $request)
    {
        [$cuenta, $datos] = $this->validar($request);

        $compra = DB::transaction(function () use ($cuenta, $datos, $request) {
            $gasto = Gasto::create($this->datosGasto($cuenta, $datos, $request) + ['user_id' => auth()->id()]);
            return CompraMeses::create($datos + ['gasto_id' => $gasto->id]);
        });

        return redirect()->route('compras-meses.show', $compra)->with('exito', 'Compra a meses registrada correctamente.');
    }

    public function show(CompraMeses $compra)
    {
        $this->autorizar($compra);
        $compra->load('cuenta.negocio', 'gasto.categoria', 'gasto.user');

        return view('compras_meses.show', compact('compra'));
    }

    public function edit(CompraMeses $compra)
    {
        $this->autorizar($compra);
        $compra->load('gasto');

        return view('compras_meses.edit', $this->datosFormulario($compra));
    }

    public function update(Request $request, CompraMeses $compra)
    {
        $this->autorizar($compra);
        [$cuenta, $datos] = $this->validar($request);

        DB::transaction(function () use ($compra, $cuenta, $datos, $request) {
            $compra->gasto->update($this->datosGasto($cuenta, $datos, $request));
            $compra->update($datos);
        });

        return redirect()->route('compras-meses.show', $compra)->with('exito', 'Compra a meses actualizada correctamente.');
    }

    public function destroy(CompraMeses $compra)
    {
        $this->autorizar($compra);

        // Al borrar el gasto se borra el plan (llave foránea en cascada)
        $compra->gasto->delete();

        return redirect()->route('compras-meses.index')->with('exito', 'Compra a meses y su gasto eliminados correctamente.');
    }

    // Tarjetas: cuentas bancarias de los negocios que el usuario puede ver
    private function cuentasTarjeta()
    {
        return $this->aplicarFiltroNegocio(Cuenta::with('negocio'))
            ->where('tipo', 'banco')
            ->orderBy('nombre')
            ->get();
    }

    private function autorizar(CompraMeses $compra): void
    {
        $compra->loadMissing('gasto');
        $this->autorizarAccesoNegocio($compra->gasto->negocio_id);
        abort_unless($this->comprasMesesVisibles()->whereKey($compra->id)->exists(), 403, 'No tienes permiso para acceder a este registro.');
    }

    private function datosFormulario(CompraMeses $compra): array
    {
        // Compromisos de las demás compras, para que el simulador sume la que se captura
        $otras = $this->comprasMesesVisibles()->when($compra->exists, fn ($q) => $q->whereKeyNot($compra->id))->get();

        return [
            'compra'      => $compra,
            'cuentas'     => $this->cuentasTarjeta(),
            'categorias'  => Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get(),
            'compromisos' => CompraMeses::compromisosPorMes($otras, now(), self::MESES_SIMULADOR),
        ];
    }

    /** @return array{0: Cuenta, 1: array} la tarjeta y los campos del plan */
    private function validar(Request $request): array
    {
        $request->validate([
            'cuenta_id'          => 'required|exists:cuentas,id',
            'categoria_id'       => 'required|exists:categorias,id',
            'descripcion'        => 'required|string|max:255',
            'monto_original'     => 'required|numeric|min:0.01',
            'meses'              => 'required|integer|min:1|max:60',
            'mensualidad'        => 'required|numeric|min:0.01',
            'fecha_compra'       => 'required|date',
            'fecha_primer_cargo' => 'required|date|after_or_equal:fecha_compra',
            'notas'              => 'nullable|string',
        ], [
            'fecha_primer_cargo.after_or_equal' => 'El primer cargo no puede ser antes de la fecha de compra.',
        ]);

        $cuenta = $this->cuentasTarjeta()->firstWhere('id', (int) $request->cuenta_id);
        if (!$cuenta) {
            abort(403, 'No tienes permiso para usar esta cuenta.');
        }

        // La última mensualidad absorbe el redondeo; si se aleja más de $1 la mensualidad no cuadra
        $monto = (float) $request->monto_original;
        $meses = (int) $request->meses;
        $mensualidad = round((float) $request->mensualidad, 2);
        $ultima = $monto - $mensualidad * ($meses - 1);
        if ($ultima <= 0 || abs($ultima - $mensualidad) > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mensualidad' => 'La mensualidad no cuadra con el monto y los meses (sugerida: $' . number_format($monto / $meses, 2) . ').',
            ]);
        }

        return [$cuenta, [
            'cuenta_id'          => $cuenta->id,
            'descripcion'        => trim($request->descripcion),
            'monto_original'     => $monto,
            'meses'              => $meses,
            'mensualidad'        => $mensualidad,
            'fecha_compra'       => $request->fecha_compra,
            'fecha_primer_cargo' => $request->fecha_primer_cargo,
            'notas'              => $request->notas,
        ]];
    }

    // El gasto refleja la compra completa en la fecha de compra, cargada a la tarjeta
    private function datosGasto(Cuenta $cuenta, array $datos, Request $request): array
    {
        $tieneIva = $request->boolean('tiene_iva');

        return [
            'negocio_id'   => $cuenta->negocio_id,
            'categoria_id' => $request->categoria_id,
            'cuenta_id'    => $cuenta->id,
            'monto'        => $datos['monto_original'],
            'fecha'        => $datos['fecha_compra'],
            'concepto'     => "{$datos['descripcion']} ({$datos['meses']} MSI)",
            'forma_pago'   => 'tarjeta',
            'notas'        => $datos['notas'],
            'tiene_iva'    => $tieneIva,
            'monto_iva'    => $tieneIva ? round($datos['monto_original'] * 16 / 116, 2) : 0,
        ];
    }
}
