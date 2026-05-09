<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Negocio;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::with('negocio')->get();
        return view('areas.index', compact('areas'));
    }

    public function create()
    {
        $negocios = Negocio::all();
        return view('areas.create', compact('negocios'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre' => 'required',
        ]);
        Area::create($request->all());
        return redirect()->route('areas.index')->with('exito', 'Área creada correctamente.');
    }

    public function edit(Area $area)
    {
        $negocios = Negocio::all();
        return view('areas.edit', compact('area', 'negocios'));
    }

    public function update(Request $request, Area $area)
    {
        $request->validate([
            'negocio_id' => 'required|exists:negocios,id',
            'nombre' => 'required',
        ]);
        $area->update($request->all());
        return redirect()->route('areas.index')->with('exito', 'Área actualizada correctamente.');
    }

    public function destroy(Area $area)
    {
        $area->delete();
        return redirect()->route('areas.index')->with('exito', 'Área eliminada correctamente.');
    }
}
