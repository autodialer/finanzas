<?php

namespace App\Http\Controllers;

abstract class Controller
{
    // Retorna los IDs de negocios visibles para el usuario actual.
    // Admin: null (sin restricción). Otros: solo negocios no privados.
    protected function negociosPermitidos(): ?\Illuminate\Support\Collection
    {
        return auth()->user()->negociosPermitidos();
    }

    // Scope helper: filtra un query por negocio_id si el usuario no es admin.
    protected function aplicarFiltroNegocio($query, string $columna = 'negocio_id')
    {
        $ids = $this->negociosPermitidos();
        if ($ids !== null) {
            $query->whereIn($columna, $ids);
        }
        return $query;
    }

    // Retorna la lista de negocios visibles para selectores en formularios.
    protected function negociosVisibles()
    {
        $ids = $this->negociosPermitidos();
        $q = \App\Models\Negocio::query();
        if ($ids !== null) {
            $q->whereIn('id', $ids);
        }
        return $q->get();
    }
}
