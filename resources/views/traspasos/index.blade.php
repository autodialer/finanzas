@extends('layouts.app')

@section('titulo', 'Traspasos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Traspasos entre Cuentas</h4>
    <a href="{{ route('traspasos.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-arrow-left-right me-1"></i> Nuevo Traspaso
    </a>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('traspasos.index') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1 fw-semibold">Cuenta (origen o destino)</label>
                <select name="cuenta_id" class="form-select form-select-sm">
                    <option value="">Todas las cuentas</option>
                    @foreach($cuentas->groupBy(fn($c) => $c->negocio->nombre ?? 'Sin negocio') as $nombreNegocio => $ctas)
                        <optgroup label="{{ $nombreNegocio }}">
                            @foreach($ctas as $cta)
                                <option value="{{ $cta->id }}" {{ ($filtros['cuenta_id'] ?? '') == $cta->id ? 'selected' : '' }}>
                                    {{ $cta->nombre }}@if($cta->numero) · Nº {{ $cta->numero }}@endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
        @if(!empty($filtros))
        <div class="mt-2">
            <a href="{{ route('traspasos.index', ['limpiar' => 1]) }}" class="text-muted small"><i class="bi bi-x-circle"></i> Limpiar filtros</a>
        </div>
        @endif
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Origen</th>
                    <th></th>
                    <th>Destino</th>
                    <th>Concepto</th>
                    <th class="text-end">Monto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($traspasos as $t)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($t->fecha)->format('d/m/Y') }}</td>
                    <td>
                        <div class="fw-semibold">{{ $t->cuentaOrigen->nombre }}</div>
                        <div class="text-muted small">{{ $t->cuentaOrigen->negocio->nombre }}</div>
                    </td>
                    <td class="text-center text-muted">
                        <i class="bi bi-arrow-right-circle-fill fs-5 text-primary"></i>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $t->cuentaDestino->nombre }}</div>
                        <div class="text-muted small">{{ $t->cuentaDestino->negocio->nombre }}</div>
                    </td>
                    <td>{{ $t->concepto ?? '-' }}</td>
                    <td class="text-end fw-bold text-primary">${{ number_format($t->monto, 2) }}</td>
                    <td>
                        <form action="{{ route('traspasos.destroy', $t) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar este traspaso?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        {{ !empty($filtros) ? 'Sin traspasos que coincidan con los filtros' : 'Sin traspasos registrados' }}
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($traspasos->count() > 0)
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end fw-bold">Total traspasos:</td>
                    <td class="text-end fw-bold text-primary">${{ number_format($traspasos->sum('monto'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
