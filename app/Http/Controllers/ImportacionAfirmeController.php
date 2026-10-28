<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Prestamo;
use App\Models\Traspaso;
use App\Services\AfirmeClasificador;
use App\Services\AfirmeEstadoCuentaParser;
use App\Services\AfirmeTarjetaClasificador;
use App\Services\AfirmeTarjetaParser;
use App\Services\PdfTexto;
use App\Services\SugeridorCategoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Importa estados de cuenta en PDF de Afirme (Cuenta Visión o tarjeta de crédito): subir → vista previa → confirmar.
class ImportacionAfirmeController extends Controller
{
    private const SESION = 'importacion_afirme';

    public function __construct(private SugeridorCategoria $sugeridor)
    {
    }

    public function create()
    {
        $cuentas = $this->cuentasAfirme();
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

        // El tipo de estado de cuenta se reconoce por su contenido
        if (AfirmeTarjetaParser::reconoce($texto)) {
            $estado = (new AfirmeTarjetaParser())->parsearTexto($texto);
        } elseif (AfirmeEstadoCuentaParser::reconoce($texto)) {
            $estado = (new AfirmeEstadoCuentaParser())->parsearTexto($texto);
        } else {
            return back()->withInput()->withErrors(['archivo' => 'El PDF no es un estado de cuenta Afirme reconocido (Cuenta Visión o tarjeta de crédito).']);
        }

        if ($estado['errores']) {
            return back()->withInput()->withErrors(['archivo' => $estado['errores']]);
        }

        $msiInformativos = [];
        if ($estado['tipo'] === 'tarjeta') {
            [$filas, $msiInformativos] = (new AfirmeTarjetaClasificador($cuenta))->clasificar($estado);
        } else {
            $filas = (new AfirmeClasificador($cuenta))->clasificar($estado['movimientos']);
        }

        session([self::SESION => [
            'cuenta_id'        => $cuenta->id,
            'archivo'          => $request->file('archivo')->getClientOriginalName(),
            'estado'           => array_diff_key($estado, ['movimientos' => true, 'msi' => true, 'errores' => true]),
            'filas'            => $this->sugeridor->sugerir($filas),
            'msi_informativos' => $msiInformativos,
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
            $cuentaDistinta = $numero !== '' && $estado['ultimos4'] && substr($numero, -4) !== $estado['ultimos4'];
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

        return view('importacion_afirme.preview', compact(
            'importacion', 'cuenta', 'estado', 'filas', 'cuentaDistinta',
            'categoriasGasto', 'categoriasIngreso', 'categoriasNuevas', 'cuentas', 'prestamos'
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

        if ($errores) {
            return redirect()->route('importacion-afirme.preview')->withInput()->withErrors($errores);
        }

        $totales = array_fill_keys(['gasto', 'ingreso', 'traspaso', 'prestamo'], 0);

        DB::transaction(function () use ($registros, $cuenta, $importacion, &$totales) {
            foreach ($registros as [$fila, $entrada]) {
                $this->guardarFila($fila, $entrada, $cuenta, $importacion);
                $totales[$entrada['tipo']]++;
            }
        });

        session()->forget(self::SESION);

        $resumen = collect(['gasto' => 'gastos', 'ingreso' => 'ingresos', 'traspaso' => 'traspasos', 'prestamo' => 'pagos de préstamo'])
            ->map(fn ($nombre, $tipo) => "{$totales[$tipo]} {$nombre}")
            ->implode(', ');

        return redirect()->route('gastos.index')->with('exito', "Estado de cuenta Afirme importado: {$resumen}.");
    }

    public function cancelar()
    {
        session()->forget(self::SESION);
        return redirect()->route('importacion-afirme.create');
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
            return $this->sugeridor->esValida($entrada['categoria_id'] ?? null, $tipo) ? null : 'selecciona una categoría.';
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
        if (in_array($entrada['tipo'], ['gasto', 'ingreso'], true)) {
            $categoriaId = $this->sugeridor->resolver($entrada['categoria_id']);
            $this->sugeridor->aprender($fila['comercio'] ?? null, $entrada['tipo'], $categoriaId);
        }

        switch ($entrada['tipo']) {
            case 'gasto':
                Gasto::create([
                    'negocio_id'   => $cuenta->negocio_id,
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
