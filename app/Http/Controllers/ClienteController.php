<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Negocio;
use App\Models\Vendedor;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::with('negocio', 'vendedor')->get();

        return view('clientes.index', compact('clientes'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        $vendedores = Vendedor::all();

        return view('clientes.create', compact('negocios', 'vendedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'vendedor_id' => 'required|exists:vendedores,id',
            'nombre' => 'required',
        ]);
        Cliente::create($request->all());

        return redirect()->route('clientes.index')->with('exito', 'Cliente creado correctamente.');
    }

    public function edit(Cliente $cliente)
    {
        $negocios = Negocio::all();
        $vendedores = Vendedor::all();

        return view('clientes.edit', compact('cliente', 'negocios', 'vendedores'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'vendedor_id' => 'required|exists:vendedores,id',
            'nombre' => 'required',
        ]);
        $cliente->update($request->all());

        return redirect()->route('clientes.index')->with('exito', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('clientes.index')->with('exito', 'Cliente eliminado correctamente.');
    }

    public function importForm()
    {
        $negocios = Negocio::all();
        $vendedores = Vendedor::all();

        return view('clientes.import', compact('negocios', 'vendedores'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'vendedor_id' => 'required|exists:vendedores,id',
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

            Cliente::create([
                'negocio_id' => $request->negocio_id,
                'vendedor_id' => $request->vendedor_id,
                'nombre' => $nombre,
                'telefono' => trim($fila[1] ?? '') ?: null,
                'email' => trim($fila[2] ?? '') ?: null,
            ]);
            $importados++;
        }

        fclose($handle);

        return redirect()->route('clientes.index')
            ->with('exito', "Importación completada: {$importados} clientes importados, {$omitidos} omitidos.");
    }
}
