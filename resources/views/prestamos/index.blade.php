@extends('layouts.app')

@section('titulo', 'Préstamos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Préstamos</h4>
    <a href="{{ route('prestamos.create') }}" class="btn btn-danger btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Préstamo
    </a>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('prestamos.index') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1 fw-semibold">Negocio</label>
                <select name="negocio_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($negocios as $n)
                        <option value="{{ $n->id }}" {{ request('negocio_id') == $n->id ? 'selected' : '' }}>{{ $n->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1 fw-semibold">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="auto" {{ request('tipo') == 'auto' ? 'selected' : '' }}>Automóvil</option>
                    <option value="equipo" {{ request('tipo') == 'equipo' ? 'selected' : '' }}>Equipo</option>
                    <option value="otro" {{ request('tipo') == 'otro' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-danger btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Negocio</th>
                    <th>Banco</th>
                    <th>Tipo</th>
                    <th>Concepto</th>
                    <th>Inicio</th>
                    <th class="text-end">Monto original</th>
                    <th class="text-end">Saldo pendiente</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prestamos as $p)
                <tr>
                    <td>{{ $p->negocio->nombre }}</td>
                    <td>{{ $p->banco->nombre }}</td>
                    <td>
                        @if($p->tipo == 'auto')
                        <span class="badge bg-primary">Automóvil</span>
                        @elseif($p->tipo == 'equipo')
                        <span class="badge bg-info text-dark">Equipo</span>
                        @else
                        <span class="badge bg-secondary">Otro</span>
                        @endif
                    </td>
                    <td>{{ $p->concepto }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->fecha_inicio)->format('d/m/Y') }}</td>
                    <td class="text-end">${{ number_format($p->monto_original, 2) }}</td>
                    <td class="text-end fw-bold {{ $p->saldo_pendiente > 0 ? 'text-danger' : 'text-success' }}">
                        ${{ number_format($p->saldo_pendiente, 2) }}
                    </td>
                    <td>
                        <a href="{{ route('prestamos.show', $p) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <form action="{{ route('prestamos.destroy', $p) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar este préstamo? También se eliminarán todos sus pagos registrados.')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Sin préstamos registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
