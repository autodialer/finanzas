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
use App\Services\AfirmeTarjetaClasificador;
use App\Services\AfirmeTarjetaParser;
use App\Services\ConciliadorComprasMeses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Importa el estado de cuenta en PDF de Afirme: subir → vista previa → confirmar.
// Cuenta Visión: todos los movimientos. Tarjeta de crédito: compras a meses y cargos/abonos regulares.
class ImportacionAfirmeController extends Controller
{
    private const SESION = 'importacion_afirme';
    private const SESION_TARJETA = 'importacion_afirme_tarjeta';

    public function create()
    {
        $cuentas = $this->cuentasAfirme();
        return view('importacion_afirme.create', compact('cuentas'));
    }

    public function analizar(Request $request, AfirmeEstadoCuentaParser $parser)
    {
        $request->validate([
            'cuenta_id' => 'required|exists:cuentas,id',
            'archivo'   => 'required|file|mimes:pdf|max:10240',
        ]);

        $cuenta = Cuenta::findOrFail($request->cuenta_id);
        $this->autorizarAccesoNegocio($cuenta->negocio_id);

        $archivo = $request->file('archivo')->getClientOriginalName();

        try {
            $texto = $parser->extraerTexto($request->file('archivo')->getPathname());
            if (AfirmeTarjetaParser::esEstadoTarjeta($texto)) {
                return $this->analizarTarjeta($cuenta, $archivo, $texto);
            }
            $estado = $parser->parsearTexto($texto);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['archivo' => 'No se pudo leer el PDF: ' . $e->getMessage()]);
        }

        if ($estado['errores']) {
            return back()->withInput()->withErrors(['archivo' => $estado['errores']]);
        }

        session([self::SESION => [
            'cuenta_id' => $cuenta->id,
            'archivo'   => $archivo,
            'estado'    => array_diff_key($estado, ['movimientos' => true, 'errores' => true]),
            'filas'     => (new AfirmeClasificador($cuenta))->clasificar($estado['movimientos']),
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
        $cuentaDistinta = $numero !== ''
            && !in_array($numero, array_filter([$estado['clabe'], $estado['numero_cuenta'], ltrim((string) $estado['numero_cuenta'], '0')]), true);

        $filas = $importacion['filas'];
        $categoriasGasto   = Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get();
        $categoriasIngreso = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->orderBy('nombre')->get();
        $cuentas   = $this->aplicarFiltroNegocio(Cuenta::with('negocio'))->where('id', '!=', $cuenta->id)->orderBy('nombre')->get();
        $prestamos = $this->aplicarFiltroNegocio(Prestamo::with('banco'))->orderBy('concepto')->get();

        return view('importacion_afirme.preview', compact(
            'importacion', 'cuenta', 'estado', 'filas', 'cuentaDistinta',
            'categoriasGasto', 'categoriasIngreso', 'cuentas', 'prestamos'
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

        [$registros, $errores] = $this->validarMovimientos($importacion['filas'], $request->input('filas', []), $cuenta, 'filas');

        if ($errores) {
            return redirect()->route('importacion-afirme.preview')->withInput()->withErrors($errores);
        }

        $totales = array_fill_keys(['gasto', 'ingreso', 'traspaso', 'prestamo'], 0);

        DB::transaction(function () use ($registros, $cuenta, $importacion, &$totales) {
            foreach ($registros as [$fila, $entrada]) {
                $this->guardarFila($fila, $entrada, $cuenta, $importacion['archivo']);
                $totales[$entrada['tipo']]++;
            }
        });

        session()->forget(self::SESION);

        return redirect()->route('gastos.index')->with('exito', 'Estado de cuenta Afirme importado: ' . $this->resumenTotales($totales) . '.');
    }

    /** @return array{0: array, 1: array} filas a guardar [fila, entrada] y errores por fila */
    private function validarMovimientos(array $filas, array $entradas, Cuenta $cuenta, string $campo): array
    {
        $registros = [];
        $errores   = [];

        foreach ($filas as $i => $fila) {
            $entrada = $entradas[$i] ?? [];
            if (($entrada['tipo'] ?? 'ignorar') === 'ignorar') {
                continue;
            }

            $error = $this->validarFila($fila, $entrada, $cuenta);
            if ($error) {
                $errores["{$campo}.{$i}"] = 'Fila ' . ($i + 1) . " ({$fila['concepto']}): {$error}";
                continue;
            }

            $registros[] = [$fila, $entrada];
        }

        return [$registros, $errores];
    }

    private function resumenTotales(array $totales): string
    {
        return collect(['gasto' => 'gastos', 'ingreso' => 'ingresos', 'traspaso' => 'traspasos', 'prestamo' => 'pagos de préstamo'])
            ->map(fn ($nombre, $tipo) => ($totales[$tipo] ?? 0) . " {$nombre}")
            ->implode(', ');
    }

    public function cancelar()
    {
        session()->forget([self::SESION, self::SESION_TARJETA]);
        return redirect()->route('importacion-afirme.create');
    }

    // ── Tarjeta de crédito: compras a meses sin intereses ──────────────────

    private function analizarTarjeta(Cuenta $cuenta, string $archivo, string $texto)
    {
        $estado = (new AfirmeTarjetaParser())->parsearTexto($texto);

        if ($estado['errores']) {
            return back()->withInput()->withErrors(['archivo' => $estado['errores']]);
        }
        if (!$estado['renglones'] && !$estado['movimientos']) {
            return back()->withInput()->withErrors(['archivo' => 'Este estado de cuenta de tarjeta no tiene compras a meses ni cargos o abonos.']);
        }

        $conciliador = new ConciliadorComprasMeses($cuenta);
        session()->forget(self::SESION);
        session([self::SESION_TARJETA => [
            'cuenta_id' => $cuenta->id,
            'archivo'   => $archivo,
            'estado'      => array_diff_key($estado, ['renglones' => true, 'movimientos' => true, 'errores' => true]),
            'filas'       => array_map(fn ($r) => $r + $conciliador->conciliar($r), $estado['renglones']),
            'movimientos' => (new AfirmeTarjetaClasificador($cuenta))->clasificar($estado['movimientos']),
        ]]);

        return redirect()->route('importacion-afirme.tarjeta');
    }

    public function previewTarjeta()
    {
        $importacion = session(self::SESION_TARJETA);
        if (!$importacion) {
            return redirect()->route('importacion-afirme.create');
        }

        $cuenta = Cuenta::with('negocio')->findOrFail($importacion['cuenta_id']);
        $this->autorizarAccesoNegocio($cuenta->negocio_id);

        $estado = $importacion['estado'];
        $filas  = $importacion['filas'];
        $numero = preg_replace('/\D/', '', (string) $cuenta->numero);
        $cuentaDistinta = $numero !== '' && $estado['ultimos_digitos'] && substr($numero, -4) !== $estado['ultimos_digitos'];
        $sumaMensualidades = round(array_sum(array_column($filas, 'mensualidad')), 2);
        $compras = CompraMeses::whereIn('id', array_filter(array_column($filas, 'compra_id')))->get()->keyBy('id');
        $movimientos = $importacion['movimientos'] ?? [];
        $categoriasGasto   = Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get();
        $categoriasIngreso = Categoria::whereIn('tipo', ['ingreso', 'ambos'])->orderBy('nombre')->get();
        $cuentas   = $this->aplicarFiltroNegocio(Cuenta::with('negocio'))->where('id', '!=', $cuenta->id)->orderBy('nombre')->get();
        $prestamos = $this->aplicarFiltroNegocio(Prestamo::with('banco'))->orderBy('concepto')->get();

        return view('importacion_afirme.tarjeta', compact(
            'importacion', 'cuenta', 'estado', 'filas', 'movimientos', 'cuentaDistinta', 'sumaMensualidades', 'compras',
            'categoriasGasto', 'categoriasIngreso', 'cuentas', 'prestamos'
        ));
    }

    public function confirmarTarjeta(Request $request)
    {
        $importacion = session(self::SESION_TARJETA);
        if (!$importacion) {
            return redirect()->route('importacion-afirme.create')
                ->withErrors(['archivo' => 'No hay una importación pendiente. Vuelve a subir el estado de cuenta.']);
        }

        $cuenta = Cuenta::findOrFail($importacion['cuenta_id']);
        $this->autorizarAccesoNegocio($cuenta->negocio_id);

        $conciliador = new ConciliadorComprasMeses($cuenta);
        $entradas = $request->input('filas', []);
        $altas    = [];
        $errores  = [];

        foreach ($importacion['filas'] as $i => $fila) {
            $entrada = $entradas[$i] ?? [];
            if (($entrada['accion'] ?? 'ignorar') !== 'alta' || !in_array($fila['accion'], [ConciliadorComprasMeses::CREAR, ConciliadorComprasMeses::SIN_PLAN], true)) {
                continue;
            }

            $etiqueta = 'Fila ' . ($i + 1) . " ({$fila['descripcion']})";
            [$datos, $error] = $this->validarAltaMeses($fila, $entrada, $cuenta);
            if (!$error && $conciliador->conciliar($fila)['compra_id']) {
                $error = 'ya existe un plan para esta compra.';
            }
            if ($error) {
                $errores["filas.{$i}"] = "{$etiqueta}: {$error}";
                continue;
            }
            $altas[] = [$datos, (int) $entrada['categoria_id']];
        }

        [$registros, $erroresMovimientos] = $this->validarMovimientos($importacion['movimientos'] ?? [], $request->input('movimientos', []), $cuenta, 'movimientos');
        $errores += $erroresMovimientos;

        if ($errores) {
            return redirect()->route('importacion-afirme.tarjeta')->withInput()->withErrors($errores);
        }

        $totales = array_fill_keys(['gasto', 'ingreso', 'traspaso', 'prestamo'], 0);

        DB::transaction(function () use ($altas, $registros, $cuenta, $importacion, &$totales) {
            foreach ($altas as [$datos, $categoriaId]) {
                CompraMeses::registrar($cuenta, $datos, $categoriaId, false, auth()->id(), "Importado de {$importacion['archivo']}");
            }
            foreach ($registros as [$fila, $entrada]) {
                $this->guardarFila($fila, $entrada, $cuenta, $importacion['archivo'], 'tarjeta');
                $totales[$entrada['tipo']]++;
            }
        });

        session()->forget(self::SESION_TARJETA);

        return redirect()->route('gastos.index')->with('exito', 'Estado de cuenta de tarjeta Afirme importado: '
            . count($altas) . ' compras a meses, ' . $this->resumenTotales($totales) . '.');
    }

    /** @return array{0: ?array, 1: ?string} campos del plan o el error */
    private function validarAltaMeses(array $fila, array $entrada, Cuenta $cuenta): array
    {
        $descripcion = trim($entrada['descripcion'] ?? '');
        if ($descripcion === '' || mb_strlen($descripcion) > 255) {
            return [null, 'la descripción es obligatoria (máximo 255 caracteres).'];
        }
        if (!Categoria::where('id', $entrada['categoria_id'] ?? null)->whereIn('tipo', ['gasto', 'ambos'])->exists()) {
            return [null, 'selecciona una categoría.'];
        }

        $meses = filter_var($entrada['meses'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if (!$meses || ($meses < (int) $fila['numero_pago'])) {
            return [null, 'el número de meses no es válido.'];
        }
        $mensualidad = round((float) ($entrada['mensualidad'] ?? 0), 2);
        if ($error = CompraMeses::errorMensualidad((float) $fila['monto_original'], $meses, $mensualidad)) {
            return [null, mb_lcfirst($error)];
        }

        try {
            $compra = \Carbon\Carbon::createFromFormat('!Y-m-d', $entrada['fecha_compra'] ?? '');
            $primer = \Carbon\Carbon::createFromFormat('!Y-m-d', $entrada['fecha_primer_cargo'] ?? '');
        } catch (\Throwable) {
            return [null, 'revisa la fecha de compra y la del primer cargo.'];
        }
        if (!$compra || !$primer || $primer->lt($compra)) {
            return [null, 'el primer cargo no puede ser antes de la fecha de compra.'];
        }

        return [[
            'cuenta_id'          => $cuenta->id,
            'descripcion'        => $descripcion,
            'monto_original'     => (float) $fila['monto_original'],
            'meses'              => $meses,
            'mensualidad'        => $mensualidad,
            'fecha_compra'       => $compra->toDateString(),
            'fecha_primer_cargo' => $primer->toDateString(),
            'notas'              => null,
        ], null];
    }

    private function cuentasAfirme()
    {
        return $this->aplicarFiltroNegocio(Cuenta::with('negocio', 'banco'))
            ->where('tipo', 'banco')
            ->whereHas('banco', fn ($q) => $q->where('nombre', 'like', '%afirme%'))
            ->orderBy('nombre')
            ->get();
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
            $existe = Categoria::where('id', $entrada['categoria_id'] ?? null)
                ->whereIn('tipo', [$tipo, 'ambos'])
                ->exists();
            return $existe ? null : 'selecciona una categoría.';
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

    private function guardarFila(array $fila, array $entrada, Cuenta $cuenta, string $archivo, string $formaPago = 'transferencia'): void
    {
        $concepto = trim($entrada['concepto']);
        $notas    = "Importado de {$archivo}: {$fila['detalle']}";

        switch ($entrada['tipo']) {
            case 'gasto':
                Gasto::create([
                    'negocio_id'   => $cuenta->negocio_id,
                    'categoria_id' => $entrada['categoria_id'],
                    'cuenta_id'    => $cuenta->id,
                    'user_id'      => auth()->id(),
                    'monto'        => $fila['monto'],
                    'referencia'   => $fila['referencia'],
                    'fecha'        => $fila['fecha'],
                    'concepto'     => $concepto,
                    'forma_pago'   => $formaPago,
                    'notas'        => $notas,
                    'tiene_iva'    => $fila['monto_iva'] > 0,
                    'monto_iva'    => $fila['monto_iva'],
                ]);
                break;

            case 'ingreso':
                Ingreso::create([
                    'negocio_id'   => $cuenta->negocio_id,
                    'categoria_id' => $entrada['categoria_id'],
                    'cuenta_id'    => $cuenta->id,
                    'user_id'      => auth()->id(),
                    'monto'        => $fila['monto'],
                    'referencia'   => $fila['referencia'],
                    'fecha'        => $fila['fecha'],
                    'concepto'     => $concepto,
                    'forma_pago'   => $formaPago,
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
