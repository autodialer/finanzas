<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\PagoPrestamo;
use App\Models\Prestamo;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->aplicarFiltroNegocio(Prestamo::with('negocio', 'banco', 'pagos'));

        if ($request->filled('negocio_id')) {
            $query->where('negocio_id', $request->negocio_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $prestamos = $query->orderBy('fecha_inicio', 'desc')->get();
        $negocios  = $this->negociosVisibles()->sortBy('nombre');

        return view('prestamos.index', compact('prestamos', 'negocios'));
    }

    public function create()
    {
        $negocios = $this->negociosParaCaptura();
        $bancos   = Banco::orderBy('nombre')->get();
        return view('prestamos.create', compact('negocios', 'bancos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id'     => 'required|exists:negocios,id',
            'banco_id'       => 'required|exists:bancos,id',
            'tipo'           => 'required|in:auto,equipo,otro',
            'concepto'       => 'required|string|max:255',
            'monto_original' => 'required|numeric|min:0.01',
            'tasa_interes'   => 'nullable|numeric|min:0|max:100',
            'plazo_meses'    => 'nullable|integer|min:1',
            'fecha_inicio'   => 'required|date',
            'notas'          => 'nullable|string',
        ]);

        Prestamo::create($request->all() + ['user_id' => auth()->id()]);

        return redirect()->route('prestamos.index')->with('exito', 'Préstamo registrado correctamente.');
    }

    public function show(Prestamo $prestamo)
    {
        $prestamo->load('negocio', 'banco', 'pagos.cuenta');
        $cuentas = $this->aplicarFiltroNegocio(Cuenta::with('negocio'))
            ->orderBy('negocio_id')->orderBy('nombre')->get();

        return view('prestamos.show', compact('prestamo', 'cuentas'));
    }

    public function destroy(Prestamo $prestamo)
    {
        foreach ($prestamo->pagos as $pago) {
            $pago->gasto?->delete();
        }
        $prestamo->delete();

        return redirect()->route('prestamos.index')->with('exito', 'Préstamo eliminado correctamente.');
    }

    public function storePago(Request $request, Prestamo $prestamo)
    {
        $request->validate([
            'fecha'     => 'required|date',
            'monto'     => 'required|numeric|min:0.01',
            'tipo'      => 'required|in:capital,interes',
            'tiene_iva' => 'nullable|boolean',
            'cuenta_id' => 'required|exists:cuentas,id',
            'notas'     => 'nullable|string',
        ]);

        $categoria = Categoria::firstOrCreate(
            ['nombre' => 'Préstamos', 'tipo' => 'gasto']
        );

        $etiquetaTipo = $request->tipo === 'capital' ? 'capital' : 'interés';
        $tieneIva     = $request->boolean('tiene_iva');
        $montoIva     = $tieneIva ? round($request->monto * 16 / 116, 2) : 0;

        $gasto = Gasto::create([
            'negocio_id'   => $prestamo->negocio_id,
            'categoria_id' => $categoria->id,
            'cuenta_id'    => $request->cuenta_id,
            'user_id'      => auth()->id(),
            'monto'        => $request->monto,
            'fecha'        => $request->fecha,
            'concepto'     => "Pago préstamo ({$etiquetaTipo}): " . $prestamo->concepto,
            'forma_pago'   => 'transferencia',
            'notas'        => $request->notas,
            'tiene_iva'    => $tieneIva,
            'monto_iva'    => $montoIva,
        ]);

        PagoPrestamo::create([
            'prestamo_id' => $prestamo->id,
            'gasto_id'    => $gasto->id,
            'fecha'       => $request->fecha,
            'monto'       => $request->monto,
            'tipo'        => $request->tipo,
            'tiene_iva'   => $tieneIva,
            'monto_iva'   => $montoIva,
            'cuenta_id'   => $request->cuenta_id,
            'notas'       => $request->notas,
            'user_id'     => auth()->id(),
        ]);

        return redirect()->route('prestamos.show', $prestamo)->with('exito', 'Pago registrado correctamente.');
    }

    public function editPago(Prestamo $prestamo, PagoPrestamo $pago)
    {
        $cuentas = $this->aplicarFiltroNegocio(Cuenta::with('negocio'))
            ->orderBy('negocio_id')->orderBy('nombre')->get();

        return view('prestamos.pagos_edit', compact('prestamo', 'pago', 'cuentas'));
    }

    public function updatePago(Request $request, Prestamo $prestamo, PagoPrestamo $pago)
    {
        $request->validate([
            'fecha'     => 'required|date',
            'monto'     => 'required|numeric|min:0.01',
            'tipo'      => 'required|in:capital,interes',
            'tiene_iva' => 'nullable|boolean',
            'cuenta_id' => 'required|exists:cuentas,id',
            'notas'     => 'nullable|string',
        ]);

        $etiquetaTipo = $request->tipo === 'capital' ? 'capital' : 'interés';
        $tieneIva     = $request->boolean('tiene_iva');
        $montoIva     = $tieneIva ? round($request->monto * 16 / 116, 2) : 0;

        $pago->update([
            'fecha'     => $request->fecha,
            'monto'     => $request->monto,
            'tipo'      => $request->tipo,
            'tiene_iva' => $tieneIva,
            'monto_iva' => $montoIva,
            'cuenta_id' => $request->cuenta_id,
            'notas'     => $request->notas,
        ]);

        $pago->gasto?->update([
            'cuenta_id' => $request->cuenta_id,
            'monto'     => $request->monto,
            'fecha'     => $request->fecha,
            'concepto'  => "Pago préstamo ({$etiquetaTipo}): " . $prestamo->concepto,
            'notas'     => $request->notas,
            'tiene_iva' => $tieneIva,
            'monto_iva' => $montoIva,
        ]);

        return redirect()->route('prestamos.show', $prestamo)->with('exito', 'Pago actualizado correctamente.');
    }

    public function destroyPago(Prestamo $prestamo, PagoPrestamo $pago)
    {
        $pago->gasto?->delete();
        $pago->delete();

        return redirect()->route('prestamos.show', $prestamo)->with('exito', 'Pago eliminado correctamente.');
    }
}
