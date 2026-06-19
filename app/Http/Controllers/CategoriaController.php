<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingreso;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::all();
        return view('categorias.index', compact('categorias'));
    }

    public function create()
    {
        return view('categorias.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required',
            'tipo' => 'required|in:ingreso,gasto,ambos',
        ]);
        Categoria::create($request->all());
        return redirect()->route('categorias.index')->with('exito', 'Categoría creada correctamente.');
    }

    public function edit(Categoria $categoria)
    {
        return view('categorias.edit', compact('categoria'));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $request->validate([
            'nombre' => 'required',
            'tipo' => 'required|in:ingreso,gasto,ambos',
        ]);
        $categoria->update($request->all());
        return redirect()->route('categorias.index')->with('exito', 'Categoría actualizada correctamente.');
    }

    public function destroy(Categoria $categoria)
    {
        $total = Gasto::where('categoria_id', $categoria->id)->count()
                + Ingreso::where('categoria_id', $categoria->id)->count();

        if ($total > 0) {
            return redirect()->route('categorias.index')
                ->with('error', "No se puede eliminar la categoría «{$categoria->nombre}» porque tiene {$total} movimiento(s) vinculado(s).");
        }

        $categoria->delete();
        return redirect()->route('categorias.index')->with('exito', 'Categoría eliminada correctamente.');
    }
}
