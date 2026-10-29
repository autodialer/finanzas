<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\CompraMeses;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Prestamo;
use App\Models\Traspaso;
use App\Services\AfirmeClasificador;
use App\Services\AfirmeEstadoCuentaParser;
use App\Services\AfirmeTarjetaParser;
use App\Services\AmexParser;
use App\Services\BanregioTarjetaParser;
use App\Services\ConciliadorComprasMeses;
use App\Services\PdfTexto;
use App\Services\SugeridorCategoria;
use App\Services\TarjetaCreditoClasificador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Importa estados de cuenta en PDF de Afirme (Cuenta Visión o tarjeta de crédito), de la tarjeta de crédito
// Banregio y de American Express: subir → vista previa → confirmar. El tipo se detecta por su contenido.
class ImportacionAfirmeController extends Controller
{
    private const SESION = 'importacion_afirme';

    public function __construct(private SugeridorCategoria $sugeridor)
    {
    }

    public function create()
    {
        $cuentas = $this->cuentasImportables();
        return view('importacion_afirme.create', compact('cuentas'));
    }

    public function analizar(Request $request, PdfTexto $pdf)
    {
        $request->validate([
            'cuenta_id' => 'required|exists:cuentas,id',
            'archivo'   => 'required|file|mimes:pdf|max:10240',
        ]);

        $cuenta = Cuenta::findOrFail($request->cuenta_id);
        $this->autorizarAccesoNegocio($cuenta->negocio_id);

        try {
            $texto = $pdf->extraer($request->file('archivo')->getPathname());
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['archivo' => 'No se pudo leer el PDF: ' . $e->getMessage()]);
        }

        // El tipo de estado de cuenta se reconoce por su contenido (Banregio antes que Afirme: también dice "tarjeta de crédito")
        if (AmexParser::reconoce($texto)) {
            if (!$this->esAmex($cuenta)) {
                return back()->withInput()->withErrors(['archivo' => "El PDF es un estado de cuenta American Express y la cuenta elegida ({$cuenta->nombre}) no es de American Express."]);
            }
            $estado = (new AmexParser())->parsearTexto($texto);
        } elseif (BanregioTarjetaParser::reconoce($texto)) {
            $estado = (new BanregioTarjetaParser())->parsearTexto($texto);
        } elseif (AfirmeTarjetaParser::reconoce($texto)) {
            $estado = (new AfirmeTarjetaParser())->parsearTexto($texto);
        } elseif (AfirmeEstadoCuentaParser::reconoce($texto)) {
            $estado = (new AfirmeEstadoCuentaParser())->parsearTexto($texto);
        } else {
            return back()->withInput()->withErrors(['archivo' => 'El PDF no es un estado de cuenta reconocido (Afirme Cuenta Visión, tarjeta de crédito Afirme, tarjeta de crédito Banregio o American Express).']);
        }

        if ($estado['errores']) {
            return back()->withInput()->withErrors(['archivo' => $estado['errores']]);
        }

        $meses = [];
        if ($estado['tipo'] === 'tarjeta') {
            [$filas, $msi] = (new TarjetaCreditoClasificador($cuenta))->clasificar($estado);
            $meses = $this->conciliarMeses($cuenta, $msi, $estado);
        } else {
            $filas = (new AfirmeClasificador($cuenta))->clasificar($estado['movimientos']);
        }

        session([self::SESION => [
            'cuenta_id'        => $cuenta->id,
            'archivo'          => $request->file('archivo')->getClientOriginalName(),
            'estado'           => array_diff_key($estado, ['movimientos' => true, 'msi' => true, 'errores' => true]),
            'filas'            => $this->conNegocio($this->sugeridor->sugerir($filas), $cuenta),
            'meses'            => $meses,
        ]]);

        return redirect()->route('importacion-afirme.preview');
    }

    public function preview()
    {
        $importacion = session(self::SESION);
        if (!$importacion) {
            return redirect()->route('importacion-afirme.create');
        }

        $cuenta = Cuenta::with('banco', 'negocio')->findOrFail($importacion['cuenta_id']);
        $this->autorizarAccesoNegocio($cuenta->negocio_id);

        // Aviso si la cuenta elegida no parece ser la del estado de cuenta
        $estado = $importacion['estado'];
        $numero = preg_replace('/\D/', '', (string) $cuenta->numero);
        if ($estado['tipo'] === 'tarjeta') {
            $tarjetas = array_filter(array_merge([$estado['ultimos4']], $estado['tarjetas'] ?? []));
            $cuentaDistinta = $numero !== '' && $tarjetas && !in_array(substr($numero, -4), $tarjetas, true);
        } else {
            $cuentaDistinta = $numero !== ''
                && !in_array($numero, array_filter([$estado['clabe'], $estado['numero_cuenta'], ltrim((string) $estado['numero_cuenta'], '0')]), true);
        }

        $filas = $importacion['filas'];
        $categoriasGasto   = Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get();
        $categoriasIngreso = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->orderBy('nombre')->get();
        $cuentas   = $this->aplicarFiltroNegocio(Cuenta::with('negocio'))->where('id', '!=', $cuenta->id)->orderBy('nombre')->get();
        $prestamos = $this->aplicarFiltroNegocio(Prestamo::with('banco'))->orderBy('concepto')->get();
        $categoriasNuevas = $this->sugeridor->categoriasNuevas();
        $negocios = $this->negociosVisibles()->sortBy('nombre');
        $meses   = $importacion['meses'] ?? [];
        $compras = CompraMeses::whereIn('id', array_filter(array_column($meses, 'compra_id')))->get()->keyBy('id');

        return view('importacion_afirme.preview', compact(
            'importacion', 'cuenta', 'estado', 'filas', 'cuentaDistinta',
            'categoriasGasto', 'categoriasIngreso', 'categoriasNuevas', 'negocios', 'cuentas', 'prestamos', 'meses', 'compras'
        ));
    }

    public function confirmar(Request $request)
    {
        $importacion = session(self::SESION);
        if (!$importacion) {
            return redirect()->route('importacion-afirme.create')
                ->withErrors(['archivo' => 'No hay una importación pendiente. Vuelve a subir el estado de cuenta.']);
        }

        $cuenta = Cuenta::findOrFail($importacion['cuenta_id']);
        $this->autorizarAccesoNegocio($cuenta->negocio_id);

        $entradas = $request->input('filas', []);
        $registros = [];
        $errores   = [];

        foreach ($importacion['filas'] as $i => $fila) {
            $entrada = $entradas[$i] ?? [];
            $tipo    = $entrada['tipo'] ?? 'ignorar';
            $etiqueta = 'Fila ' . ($i + 1) . " ({$fila['concepto']})";

            if ($tipo === 'ignorar') {
                continue;
            }

            $error = $this->validarFila($fila, $entrada, $cuenta);
            if ($error) {
                $errores["filas.{$i}"] = "{$etiqueta}: {$error}";
                continue;
            }

            $registros[] = [$fila, $entrada];
        }

        [$altasMeses, $mensualidades, $erroresMeses] = $this->validarMeses($importacion['meses'] ?? [], $request->input('meses', []), $cuenta);
        $errores += $erroresMeses;

        if ($errores) {
            return redirect()->route('importacion-afirme.preview')->withInput()->withErrors($errores);
        }

        $totales = array_fill_keys(['gasto', 'ingreso', 'traspaso', 'prestamo'], 0);

        DB::transaction(function () use ($registros, $altasMeses, $mensualidades, $cuenta, $importacion, &$totales) {
            foreach ($registros as [$fila, $entrada]) {
                $this->guardarFila($fila, $entrada, $cuenta, $importacion);
                $totales[$entrada['tipo']]++;
            }
            foreach ($altasMeses as [$datos, $categoria, $negocioId]) {
                $categoriaId = $this->sugeridor->resolver($categoria);
                $this->sugeridor->aprender($datos['descripcion'], 'gasto', $categoriaId, $negocioId);
                CompraMeses::registrar($cuenta, $datos, $categoriaId, false, auth()->id(), "Importado de {$importacion['archivo']}", $negocioId);
            }
            foreach ($mensualidades as [$renglon, $entrada]) {
                $this->guardarMensualidad($renglon, $entrada, $cuenta, $importacion);
            }
        });

        session()->forget(self::SESION);

        $resumen = collect(['gasto' => 'gastos', 'ingreso' => 'ingresos', 'traspaso' => 'traspasos', 'prestamo' => 'pagos de préstamo'])
            ->map(fn ($nombre, $tipo) => "{$totales[$tipo]} {$nombre}")
            ->implode(', ');
        if ($importacion['meses'] ?? []) {
            $resumen .= ', ' . count($altasMeses) . ' compras a meses';
        }
        if ($mensualidades) {
            $resumen .= ', ' . count($mensualidades) . ' mensualidades como gasto';
        }
        return redirect()->route('gastos.index')->with('exito', 'Estado de cuenta ' . ($cuenta->banco->nombre ?? '') . " importado: {$resumen}.");
    }

    public function cancelar()
    {
        session()->forget(self::SESION);
        return redirect()->route('importacion-afirme.create');
    }

    /**
     * Cada renglón de meses sin intereses se compara con las compras a meses de la
     * tarjeta. La fecha del renglón es la del cargo del periodo; el total de meses
     * no viene en el estado de cuenta y se deduce de monto original / pago.
     */
    private function conciliarMeses(Cuenta $cuenta, array $msi, array $estado): array
    {
        $conciliador = new ConciliadorComprasMeses($cuenta);
        $usados = [];

        return array_map(function ($renglon) use ($conciliador, $estado, $cuenta, &$usados) {
            $pago = (float) $renglon['pago_requerido'];
            $renglon += [
                'mensualidad'    => $pago,
                'total_pagos'    => $pago > 0 ? (int) round($renglon['monto_original'] / $pago) : null,
                'periodo_inicio' => $estado['periodo_inicio'],
                'periodo_fin'    => $estado['periodo_fin'],
            ];
            $renglon['fecha'] ??= $estado['periodo_fin'];

            // Categoría sugerida para el gasto de la compra (regla aprendida o palabra clave)
            [$sugerida] = $this->conNegocio($this->sugeridor->sugerir([['naturaleza' => 'retiro', 'comercio' => $renglon['comercio'] ?? $renglon['descripcion'], 'categoria_id' => null, 'motivo' => '']]), $cuenta);

            $renglon += $conciliador->conciliar($renglon) + ['categoria_id' => $sugerida['categoria_id'], 'negocio_id' => $sugerida['negocio_id']];
            $renglon['gasto_previo'] = $this->gastoPrevioDeLaCompra($cuenta, $renglon, $usados);
            return $renglon + ['accion_sugerida' => $this->accionSugeridaMeses($renglon)];
        }, $msi);
    }

    private const DIAS_TOLERANCIA_GASTO_PREVIO = 3;

    /**
     * Dar de alta una compra a meses crea un gasto por el monto original. Si esa
     * compra ya se capturó como gasto (a mano, sin plan) por el mismo monto cerca
     * de la fecha de compra, el alta la duplicaría. Cada gasto marca una sola compra.
     */
    private function gastoPrevioDeLaCompra(Cuenta $cuenta, array $renglon, array &$usados): ?array
    {
        $fecha = $renglon['sugerencia']['fecha_compra'] ?? null;
        if (!$fecha || !in_array($renglon['accion'], [ConciliadorComprasMeses::CREAR, ConciliadorComprasMeses::SIN_PLAN], true)) {
            return null;
        }

        $gasto = Gasto::where('cuenta_id', $cuenta->id)
            ->whereDoesntHave('compraMeses')
            ->where('monto', round((float) $renglon['monto_original'], 2))
            ->whereBetween('fecha', [
                Carbon::parse($fecha)->subDays(self::DIAS_TOLERANCIA_GASTO_PREVIO)->toDateString(),
                Carbon::parse($fecha)->addDays(self::DIAS_TOLERANCIA_GASTO_PREVIO)->toDateString(),
            ])
            ->whereNotIn('id', $usados)
            ->get()
            ->sortBy(fn ($g) => abs(Carbon::parse($g->fecha)->diffInDays(Carbon::parse($fecha), false)))
            ->first();
        if (!$gasto) {
            return null;
        }

        $usados[] = $gasto->id;
        return ['id' => $gasto->id, 'fecha' => Carbon::parse($gasto->fecha)->toDateString(), 'concepto' => $gasto->concepto, 'monto' => (float) $gasto->monto];
    }

    /**
     * Pago 1 → dar de alta el plan. En Amex, una compra con pago > 1 sin plan es
     * anterior a lo registrado (o sus mensualidades ya se guardaban como gasto
     * cada mes): se registra la mensualidad del periodo como gasto, porque dar de
     * alta el plan pondría el gasto completo en un mes anterior o lo duplicaría.
     * Si la mensualidad ya está registrada, no se guarda nada.
     */
    private function accionSugeridaMeses(array $renglon): string
    {
        $puedeAlta = in_array($renglon['accion'], [ConciliadorComprasMeses::CREAR, ConciliadorComprasMeses::SIN_PLAN], true);
        if (!$puedeAlta || ($renglon['mensualidad_registrada'] ?? false) || ($renglon['gasto_previo'] ?? null)) {
            return 'ignorar';
        }
        $esAmex = array_key_exists('mensualidades_previas', $renglon);
        if ($esAmex && ($renglon['mensualidades_previas'] > 0 || $renglon['accion'] === ConciliadorComprasMeses::SIN_PLAN)) {
            return 'mensualidad';
        }
        return $renglon['accion'] === ConciliadorComprasMeses::CREAR ? 'alta' : 'ignorar';
    }

    /** @return array{0: array, 1: array, 2: array} altas [datos del plan, categoría], mensualidades [renglón, entrada] y errores por renglón */
    private function validarMeses(array $meses, array $entradas, Cuenta $cuenta): array
    {
        $conciliador = new ConciliadorComprasMeses($cuenta);
        $altas   = [];
        $mensualidades = [];
        $errores = [];

        foreach ($meses as $i => $renglon) {
            $entrada = $entradas[$i] ?? [];
            $accion  = $entrada['accion'] ?? 'ignorar';
            $puedeAlta = in_array($renglon['accion'], [ConciliadorComprasMeses::CREAR, ConciliadorComprasMeses::SIN_PLAN], true);
            if (!$puedeAlta || !in_array($accion, ['alta', 'mensualidad'], true)) {
                continue;
            }

            if ($accion === 'mensualidad') {
                $error = $this->validarMensualidad($renglon, $entrada) ?? $this->errorNegocio($entrada);
                if ($error) {
                    $errores["meses.{$i}"] = 'Compra a meses ' . ($i + 1) . " ({$renglon['descripcion']}): {$error}";
                } else {
                    $mensualidades[] = [$renglon, $entrada];
                }
                continue;
            }

            [$datos, $error] = $this->validarAltaMeses($renglon, $entrada, $cuenta);
            $error ??= $this->errorNegocio($entrada);
            if (!$error && $conciliador->conciliar($renglon)['compra_id']) {
                $error = 'ya existe un plan para esta compra.';
            }
            if ($error) {
                $errores["meses.{$i}"] = 'Compra a meses ' . ($i + 1) . " ({$renglon['descripcion']}): {$error}";
                continue;
            }
            $altas[] = [$datos, $entrada['categoria_id'], $this->negocioElegido($entrada, $cuenta)];
        }

        return [$altas, $mensualidades, $errores];
    }

    // Solo las mensualidades de Amex vienen como cargo del periodo y se pueden registrar como gasto
    private function validarMensualidad(array $renglon, array $entrada): ?string
    {
        if (!array_key_exists('mensualidades_previas', $renglon)) {
            return 'esta compra no se puede registrar como mensualidad.';
        }
        $descripcion = trim($entrada['descripcion'] ?? '');
        if ($descripcion === '' || mb_strlen($descripcion) > 200) {
            return 'la descripción es obligatoria (máximo 200 caracteres).';
        }
        return $this->sugeridor->esValida($entrada['categoria_id'] ?? null, 'gasto') ? null : 'selecciona una categoría.';
    }

    private function guardarMensualidad(array $renglon, array $entrada, Cuenta $cuenta, array $importacion): void
    {
        $categoriaId = $this->sugeridor->resolver($entrada['categoria_id']);
        $negocioId   = $this->negocioElegido($entrada, $cuenta);
        $this->sugeridor->aprender($renglon['comercio'] ?? $renglon['descripcion'], 'gasto', $categoriaId, $negocioId);

        Gasto::create([
            'negocio_id'   => $negocioId,
            'categoria_id' => $categoriaId,
            'cuenta_id'    => $cuenta->id,
            'user_id'      => auth()->id(),
            'monto'        => $renglon['pago_requerido'],
            'referencia'   => $renglon['referencia'] ?? null,
            'fecha'        => $renglon['fecha'],
            'concepto'     => trim($entrada['descripcion']) . " (mensualidad {$renglon['numero_pago']} de {$renglon['total_pagos']})",
            'forma_pago'   => 'tarjeta',
            'notas'        => "Importado de {$importacion['archivo']}: {$renglon['detalle']}",
            'tiene_iva'    => false,
            'monto_iva'    => 0,
        ]);
    }

    /** @return array{0: ?array, 1: ?string} campos del plan o el error */
    private function validarAltaMeses(array $renglon, array $entrada, Cuenta $cuenta): array
    {
        $descripcion = trim($entrada['descripcion'] ?? '');
        if ($descripcion === '' || mb_strlen($descripcion) > 255) {
            return [null, 'la descripción es obligatoria (máximo 255 caracteres).'];
        }
        if (!$this->sugeridor->esValida($entrada['categoria_id'] ?? null, 'gasto')) {
            return [null, 'selecciona una categoría.'];
        }

        $meses = filter_var($entrada['meses'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if (!$meses || $meses < (int) $renglon['numero_pago']) {
            return [null, 'el número de meses no es válido.'];
        }
        $mensualidad = round((float) ($entrada['mensualidad'] ?? 0), 2);
        if ($error = CompraMeses::errorMensualidad((float) $renglon['monto_original'], $meses, $mensualidad)) {
            return [null, mb_lcfirst($error)];
        }

        try {
            $compra = Carbon::createFromFormat('!Y-m-d', $entrada['fecha_compra'] ?? '');
            $primer = Carbon::createFromFormat('!Y-m-d', $entrada['fecha_primer_cargo'] ?? '');
        } catch (\Throwable) {
            return [null, 'revisa la fecha de compra y la del primer cargo.'];
        }
        if (!$compra || !$primer || $primer->lt($compra)) {
            return [null, 'el primer cargo no puede ser antes de la fecha de compra.'];
        }

        return [[
            'cuenta_id'          => $cuenta->id,
            'descripcion'        => $descripcion,
            'monto_original'     => (float) $renglon['monto_original'],
            'meses'              => $meses,
            'mensualidad'        => $mensualidad,
            'fecha_compra'       => $compra->toDateString(),
            'fecha_primer_cargo' => $primer->toDateString(),
            'notas'              => null,
        ], null];
    }

    private function cuentasImportables()
    {
        return $this->aplicarFiltroNegocio(Cuenta::with('negocio', 'banco'))
            ->where('tipo', 'banco')
            ->whereHas('banco', fn ($q) => $q->where('nombre', 'like', '%afirme%')
                ->orWhere('nombre', 'like', '%banregio%')
                ->orWhere('nombre', 'like', '%american express%'))
            ->orderBy('nombre')
            ->get();
    }

    private function esAmex(Cuenta $cuenta): bool
    {
        return (bool) preg_match('/american express/i', (string) $cuenta->banco?->nombre);
    }

    // Negocio sugerido para cada fila: el aprendido para el comercio (si el usuario lo puede ver) o el de la cuenta
    private function conNegocio(array $filas, Cuenta $cuenta): array
    {
        $permitidos = $this->negociosPermitidos();

        return array_map(function ($fila) use ($cuenta, $permitidos) {
            $aprendido = $fila['negocio_id'] ?? null;
            $valido = $aprendido && ($permitidos === null || $permitidos->contains($aprendido));
            if ($valido && (int) $aprendido !== (int) $cuenta->negocio_id) {
                $fila['motivo'] = ($fila['motivo'] ?? '') . ' · negocio aprendido';
            }
            $fila['negocio_id'] = $valido ? (int) $aprendido : $cuenta->negocio_id;
            return $fila;
        }, $filas);
    }

    // Sin negocio en el formulario se usa el de la cuenta
    private function negocioElegido(array $entrada, Cuenta $cuenta): int
    {
        return (int) ($entrada['negocio_id'] ?? 0) ?: $cuenta->negocio_id;
    }

    private function errorNegocio(array $entrada): ?string
    {
        $id = (int) ($entrada['negocio_id'] ?? 0);
        if ($id === 0) {
            return null;
        }
        $permitidos = $this->negociosPermitidos();
        $existe = \App\Models\Negocio::whereKey($id)->exists();
        return $existe && ($permitidos === null || $permitidos->contains($id)) ? null : 'selecciona un negocio válido.';
    }

    private function validarFila(array $fila, array $entrada, Cuenta $cuenta): ?string
    {
        $tipo = $entrada['tipo'];
        $permitidos = $fila['naturaleza'] === 'retiro'
            ? ['gasto', 'traspaso', 'prestamo']
            : ['ingreso', 'traspaso'];

        if (!in_array($tipo, $permitidos, true)) {
            return 'tipo no válido para un ' . ($fila['naturaleza'] === 'retiro' ? 'retiro' : 'depósito') . '.';
        }

        if (trim($entrada['concepto'] ?? '') === '' || mb_strlen($entrada['concepto']) > 255) {
            return 'el concepto es obligatorio (máximo 255 caracteres).';
        }

        if ($tipo === 'gasto' || $tipo === 'ingreso') {
            if (!$this->sugeridor->esValida($entrada['categoria_id'] ?? null, $tipo)) {
                return 'selecciona una categoría.';
            }
            return $tipo === 'gasto' ? $this->errorNegocio($entrada) : null;
        }

        if ($tipo === 'traspaso') {
            $destinoId = (int) ($entrada['cuenta_destino_id'] ?? 0);
            $valida = $destinoId !== $cuenta->id
                && $this->aplicarFiltroNegocio(Cuenta::where('id', $destinoId))->exists();
            return $valida ? null : 'selecciona la otra cuenta del traspaso.';
        }

        $prestamoValido = $this->aplicarFiltroNegocio(Prestamo::where('id', $entrada['prestamo_id'] ?? null))->exists();
        if (!$prestamoValido) {
            return 'selecciona el préstamo.';
        }
        return in_array($entrada['prestamo_tipo'] ?? null, ['capital', 'interes'], true)
            ? null
            : 'indica si el pago es a capital o a interés.';
    }

    private function guardarFila(array $fila, array $entrada, Cuenta $cuenta, array $importacion): void
    {
        $concepto = trim($entrada['concepto']);
        $notas    = "Importado de {$importacion['archivo']}: {$fila['detalle']}";

        // La categoría elegida queda como regla para el comercio en las siguientes importaciones
        // y, si es gasto, también el negocio al que se asignó
        if (in_array($entrada['tipo'], ['gasto', 'ingreso'], true)) {
            $categoriaId = $this->sugeridor->resolver($entrada['categoria_id']);
            $negocioId   = $entrada['tipo'] === 'gasto' ? $this->negocioElegido($entrada, $cuenta) : null;
            $this->sugeridor->aprender($fila['comercio'] ?? null, $entrada['tipo'], $categoriaId, $negocioId);
        }

        switch ($entrada['tipo']) {
            case 'gasto':
                Gasto::create([
                    'negocio_id'   => $negocioId,
                    'categoria_id' => $categoriaId,
                    'cuenta_id'    => $cuenta->id,
                    'user_id'      => auth()->id(),
                    'monto'        => $fila['monto'],
                    'referencia'   => $fila['referencia'],
                    'fecha'        => $fila['fecha'],
                    'concepto'     => $concepto,
                    'forma_pago'   => $importacion['estado']['tipo'] === 'tarjeta' ? 'tarjeta' : 'transferencia',
                    'notas'        => $notas,
                    'tiene_iva'    => $fila['monto_iva'] > 0,
                    'monto_iva'    => $fila['monto_iva'],
                ]);
                break;

            case 'ingreso':
                Ingreso::create([
                    'negocio_id'   => $cuenta->negocio_id,
                    'categoria_id' => $categoriaId,
                    'cuenta_id'    => $cuenta->id,
                    'user_id'      => auth()->id(),
                    'monto'        => $fila['monto'],
                    'referencia'   => $fila['referencia'],
                    'fecha'        => $fila['fecha'],
                    'concepto'     => $concepto,
                    'forma_pago'   => 'transferencia',
                    'notas'        => $notas,
                    'tiene_iva'    => false,
                    'monto_iva'    => 0,
                ]);
                break;

            case 'traspaso':
                $esRetiro = $fila['naturaleza'] === 'retiro';
                Traspaso::create([
                    'fecha'             => $fila['fecha'],
                    'cuenta_origen_id'  => $esRetiro ? $cuenta->id : $entrada['cuenta_destino_id'],
                    'cuenta_destino_id' => $esRetiro ? $entrada['cuenta_destino_id'] : $cuenta->id,
                    'monto'             => $fila['monto'],
                    'referencia'        => $fila['referencia'],
                    'concepto'          => $concepto,
                    'notas'             => $notas,
                ]);
                break;

            case 'prestamo':
                Prestamo::findOrFail($entrada['prestamo_id'])->registrarPago([
                    'fecha'      => $fila['fecha'],
                    'monto'      => $fila['monto'],
                    'tipo'       => $entrada['prestamo_tipo'],
                    'cuenta_id'  => $cuenta->id,
                    'referencia' => $fila['referencia'],
                    'notas'      => $notas,
                    'user_id'    => auth()->id(),
                ]);
                break;
        }
    }
}
