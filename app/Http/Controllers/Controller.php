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

    // Todos los negocios para formularios de captura.
    // Capturista solo ve su negocio asignado.
    protected function negociosParaCaptura()
    {
        $user = auth()->user();
        if ($user->isCapturista()) {
            return \App\Models\Negocio::where('id', $user->negocio_id)->orderBy('nombre')->get();
        }
        return \App\Models\Negocio::orderBy('nombre')->get();
    }

    // Filtra reportes/listados: capturista solo ve su negocio; gerente ve públicos + propios.
    protected function aplicarFiltroReportes($query, string $columna = 'negocio_id')
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isCapturista()) {
            if (!$user->negocio_id) {
                return $query->whereRaw('0 = 1'); // sin negocio asignado → no ve nada
            }
            return $query->where($columna, $user->negocio_id);
        }
        $negociosPublicos = \App\Models\Negocio::where('privado', false)->pluck('id');
        return $query->where(function ($q) use ($negociosPublicos, $columna) {
            $q->whereIn($columna, $negociosPublicos)
              ->orWhere('user_id', auth()->id());
        });
    }

    // Lanza 403 si el usuario capturista intenta acceder a un negocio que no es el suyo.
    protected function autorizarAccesoNegocio(int $negocioId): void
    {
        $user = auth()->user();
        if ($user->isCapturista() && $user->negocio_id !== $negocioId) {
            abort(403, 'No tienes permiso para acceder a este registro.');
        }
    }
}
