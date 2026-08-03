<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Cuenta;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $claves = ['fecha_desde', 'fecha_hasta', 'negocio_id', 'proveedor_id', 'categoria_id', 'cuenta_id'];

        if ($request->has('fecha_desde') || $request->has('limpiar')) {
            // El usuario envió el formulario de filtros o pidió limpiar
            $filtros = $request->boolean('limpiar') ? [] : $request->only($claves);
            session(['gastos_filtros' => array_filter($filtros, fn($v) => $v !== null && $v !== '')]);
        }

        $filtros = session('gastos_filtros', []);

        $query = $this->aplicarFiltroReportes(
            Gasto::with('negocio', 'categoria', 'proveedor', 'cuenta', 'user')
        );

        if (!empty($filtros['fecha_desde']))   $query->where('fecha', '>=', $filtros['fecha_desde']);
        if (!empty($filtros['fecha_hasta']))   $query->where('fecha', '<=', $filtros['fecha_hasta']);
        if (!empty($filtros['negocio_id']))    $query->where('negocio_id', $filtros['negocio_id']);
        if (!empty($filtros['proveedor_id']))  $query->where('proveedor_id', $filtros['proveedor_id']);
        if (!empty($filtros['categoria_id']))  $query->where('categoria_id', $filtros['categoria_id']);
        if (!empty($filtros['cuenta_id']))     $query->where('cuenta_id', $filtros['cuenta_id']);

        $gastos      = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();
        $negocios    = Negocio::orderBy('nombre')->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get();
        $cuentas     = Cuenta::orderBy('nombre')->get();

        return view('gastos.index', compact('gastos', 'negocios', 'proveedores', 'categorias', 'cuentas', 'filtros'));
    }

    public function create()
    {
        $negocios    = $this->negociosParaCaptura();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas     = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.create', compact('negocios', 'categorias', 'proveedores', 'cuentas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id'        => 'required|exists:negocios,id',
            'categoria_id'      => 'required|exists:categorias,id',
            'cuenta_id'         => 'required|exists:cuentas,id',
            'monto'             => 'required|numeric|min:0',
            'fecha'             => 'required|date',
            'concepto'          => 'required',
            'forma_pago'        => 'required|in:efectivo,transferencia,tarjeta',
            'porcentaje_propina'=> 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->except(['tiene_iva', 'monto_iva', 'tiene_propina', 'monto_propina', 'porcentaje_propina']);
        $data = array_merge($data, $this->calcularPropinaeIva($request), ['user_id' => auth()->id()]);

        Gasto::create($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto registrado correctamente.');
    }

    public function edit(Gasto $gasto)
    {
        $negocios    = $this->negociosParaCaptura();
        $categorias  = Categoria::whereIn('tipo', ['gasto', 'ambos'])->get();
        $proveedores = Proveedor::orderBy('nombre')->get();
        $cuentas     = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.edit', compact('gasto', 'negocios', 'categorias', 'proveedores', 'cuentas'));
    }

    public function update(Request $request, Gasto $gasto)
    {
        $request->validate([
            'negocio_id'        => 'required|exists:negocios,id',
            'categoria_id'      => 'required|exists:categorias,id',
            'cuenta_id'         => 'required|exists:cuentas,id',
            'monto'             => 'required|numeric|min:0',
            'fecha'             => 'required|date',
            'concepto'          => 'required',
            'forma_pago'        => 'required|in:efectivo,transferencia,tarjeta',
            'porcentaje_propina'=> 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->except(['tiene_iva', 'monto_iva', 'tiene_propina', 'monto_propina', 'porcentaje_propina']);
        $data = array_merge($data, $this->calcularPropinaeIva($request));

        $gasto->update($data);
        return redirect()->route('gastos.index')->with('exito', 'Gasto actualizado correctamente.');
    }

    private function normalizarEncabezado(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }

    private function calcularPropinaeIva(Request $request): array
    {
        $monto = (float) $request->monto;
        $result = [];

        // Calcular propina
        if ($request->boolean('tiene_propina')) {
            $pct             = (float) ($request->porcentaje_propina ?? 20);
            $monto_propina   = round($monto * $pct / (100 + $pct), 2);
            $base_para_iva   = $monto - $monto_propina;
            $result['tiene_propina']     = true;
            $result['porcentaje_propina']= $pct;
            $result['monto_propina']     = $monto_propina;
        } else {
            $base_para_iva = $monto;
            $result['tiene_propina']     = false;
            $result['porcentaje_propina']= 0;
            $result['monto_propina']     = 0;
        }

        // Calcular IVA sobre la base (sin propina)
        if ($request->boolean('tiene_iva')) {
            $result['tiene_iva']  = true;
            $result['monto_iva']  = round($base_para_iva * 16 / 116, 2);
        } else {
            $result['tiene_iva']  = false;
            $result['monto_iva']  = 0;
        }

        return $result;
    }

    public function destroy(Gasto $gasto)
    {
        $gasto->delete();
        return redirect()->route('gastos.index')->with('exito', 'Gasto eliminado correctamente.');
    }

    public function importForm()
    {
        $negocios   = $this->negociosParaCaptura();
        $categorias = Categoria::whereIn('tipo', ['gasto', 'ambos'])->orderBy('nombre')->get();
        $cuentas    = Cuenta::with('negocio')->orderBy('nombre')->get();
        return view('gastos.import', compact('negocios', 'categorias', 'cuentas'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'negocio_id'   => 'required|exists:negocios,id',
            'categoria_id' => 'required|exists:categorias,id',
            'cuenta_id'    => 'required|exists:cuentas,id',
            'archivo'      => 'required|file|mimes:xlsx,xls',
        ]);

        $spreadsheet = IOFactory::load($request->file('archivo')->getPathname());
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, false);

        // Buscar la fila de encabezados (tiene "Fecha" en la primera columna)
        $headerRow = null;
        foreach ($filas as $i => $fila) {
            if (isset($fila[0]) && strtolower(trim((string)$fila[0])) === 'fecha') {
                $headerRow = $i;
                break;
            }
        }

        if ($headerRow === null) {
            return back()->withErrors(['archivo' => 'No se encontró la fila de encabezados en el archivo.']);
        }

        // Localizar columnas de Descripción e Importe por nombre de encabezado,
        // ya que Amex cambia el orden de columnas entre exportaciones.
        $colConcepto = null;
        $colMonto    = null;
        foreach ($filas[$headerRow] as $idx => $valor) {
            $encabezado = $this->normalizarEncabezado((string) $valor);
            if ($colConcepto === null && str_contains($encabezado, 'descripcion')) {
                $colConcepto = $idx;
            }
            if ($colMonto === null && str_contains($encabezado, 'importe')) {
                $colMonto = $idx;
            }
        }

        if ($colConcepto === null || $colMonto === null) {
            return back()->withErrors(['archivo' => 'No se encontraron las columnas "Descripción" e "Importe" en el archivo.']);
        }

        $importados = 0;
        $omitidos   = 0;

        for ($i = $headerRow + 1; $i < count($filas); $i++) {
            $fila = $filas[$i];

            $fecha    = trim((string)($fila[0] ?? ''));
            $concepto = trim((string)($fila[$colConcepto] ?? ''));
            $monto    = $fila[$colMonto] ?? null;

            if (empty($fecha) || empty($concepto) || $monto === null || $monto === '') {
                $omitidos++;
                continue;
            }

            // Parsear fecha (viene como "22 Jun 2026" o como número de serie Excel)
            try {
                if (is_numeric($fecha)) {
                    $fechaObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$fecha);
                } else {
                    $fechaObj = new \DateTime($fecha);
                }
                $fechaStr = $fechaObj->format('Y-m-d');
            } catch (\Exception $e) {
                $omitidos++;
                continue;
            }

            $proveedor = Proveedor::firstOrCreate([
                'negocio_id' => $request->negocio_id,
                'nombre'     => $concepto,
            ]);

            Gasto::create([
                'negocio_id'        => $request->negocio_id,
                'categoria_id'      => $request->categoria_id,
                'cuenta_id'         => $request->cuenta_id,
                'proveedor_id'      => $proveedor->id,
                'user_id'           => auth()->id(),
                'monto'             => (float) $monto,
                'fecha'             => $fechaStr,
                'concepto'          => $concepto,
                'forma_pago'        => 'tarjeta',
                'tiene_iva'         => false,
                'monto_iva'         => 0,
                'tiene_propina'     => false,
                'monto_propina'     => 0,
                'porcentaje_propina'=> 0,
            ]);
            $importados++;
        }

        return redirect()->route('gastos.index')
            ->with('exito', "Importación completada: {$importados} gastos importados, {$omitidos} omitidos.");
    }
}
