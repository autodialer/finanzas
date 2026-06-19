@extends('layouts.app')

@section('titulo', 'Nóminas')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nóminas</h4>
    <a href="{{ route('nominas.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Período
    </a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Período</th>
                    <th>Negocio</th>
                    <th>Empresa</th>
                    <th>Tipo</th>
                    <th>Fechas</th>
                    <th>Empleados</th>
                    <th class="text-end">Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $periodo)
                <tr>
                    <td>{{ $periodo->nombre }}</td>
                    <td>{{ $periodo->negocio->nombre }}</td>
                    <td>{{ $periodo->empresa->nombre ?? '-' }}</td>
                    <td>
                        @if($periodo->tipo_periodo == 'semanal')
                            <span class="badge bg-info text-dark">Semanal</span>
                        @elseif($periodo->tipo_periodo == 'quincenal')
                            <span class="badge bg-primary">Quincenal</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-muted small">
                        {{ $periodo->fecha_inicio->format('d/m/Y') }} — {{ $periodo->fecha_fin->format('d/m/Y') }}
                    </td>
                    <td>{{ $periodo->nominas->count() }}</td>
                    <td class="text-end fw-bold text-danger">${{ number_format($periodo->nominas->sum('monto'), 2) }}</td>
                    <td>
                        @if($periodo->estado === 'cerrado')
                            <span class="badge bg-secondary">Cerrado</span>
                        @else
                            <span class="badge bg-warning text-dark">Borrador</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('nominas.show', $periodo) }}" class="btn btn-sm btn-info">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($periodo->estado === 'borrador')
                        <form action="{{ route('nominas.destroy', $periodo) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar período?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted">Sin períodos de nómina</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
