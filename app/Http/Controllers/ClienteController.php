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
}
