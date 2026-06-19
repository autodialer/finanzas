@extends('layouts.app')

@section('titulo', 'Empleados')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Empleados</h4>
    <a href="{{ route('empleados.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Empleado
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Negocio</th>
                    <th>Empresa</th>
                    <th>Nombre</th>
                    <th>Cargo</th>
                    <th>Período</th>
                    <th class="text-end">Salario</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($empleados as $empleado)
                <tr>
                    <td>{{ $empleado->negocio->nombre }}</td>
                    <td>{{ $empleado->empresa->nombre ?? '-' }}</td>
                    <td>{{ $empleado->nombre }}</td>
                    <td>{{ $empleado->cargo ?? '-' }}</td>
                    <td>
                        @if($empleado->periodo_pago == 'semanal')
                            <span class="badge bg-info text-dark">Semanal</span>
                        @elseif($empleado->periodo_pago == 'quincenal')
                            <span class="badge bg-primary">Quincenal</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold">${{ number_format($empleado->salario, 2) }}</td>
                    <td>
                        @if($empleado->activo)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('empleados.edit', $empleado) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('empleados.destroy', $empleado) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar empleado?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">Sin empleados registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
