<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ProveedorController extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::with('negocio')->get();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create()
    {
        $negocios = Negocio::all();

        return view('proveedores.create', compact('negocios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre' => 'required',
        ]);
        Proveedor::create($request->all());

        return redirect()->route('proveedores.index')->with('exito', 'Proveedor creado correctamente.');
    }

    public function edit(Proveedor $proveedor)
    {
        $negocios = Negocio::all();

        return view('proveedores.edit', compact('proveedor', 'negocios'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre' => 'required',
        ]);
        $proveedor->update($request->all());

        return redirect()->route('proveedores.index')->with('exito', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor)
    {
        try {
            $proveedor->delete();
            return redirect()->route('proveedores.index')
                ->with('exito', 'Proveedor eliminado correctamente.');
        } catch (QueryException $e) {
            return redirect()->route('proveedores.index')
                ->with('error', 'No se pudo eliminar el proveedor porque tiene registros vinculados.');
        }
    }

    public function importForm()
    {
        $negocios = Negocio::all();

        return view('proveedores.import', compact('negocios'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'archivo' => 'required|file|mimes:csv,txt',
        ]);

        $archivo = $request->file('archivo');
        $handle = fopen($archivo->getPathname(), 'r');

        $importados = 0;
        $omitidos = 0;
        $primeraFila = true;

        while (($fila = fgetcsv($handle, 1000, ',')) !== false) {
            if ($primeraFila) {
                $primeraFila = false;

                continue;
            }

            $nombre = trim($fila[0] ?? '');
            if (empty($nombre)) {
                $omitidos++;

                continue;
            }

            Proveedor::create([
                'negocio_id' => $request->negocio_id,
                'nombre' => $nombre,
                'telefono' => trim($fila[1] ?? '') ?: null,
                'email' => trim($fila[2] ?? '') ?: null,
            ]);
            $importados++;
        }

        fclose($handle);

        return redirect()->route('proveedores.index')
            ->with('exito', "Importación completada: {$importados} proveedores importados, {$omitidos} omitidos.");
    }
}
