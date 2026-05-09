@extends('layouts.app')

@section('titulo', 'Áreas')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Áreas</h4>
    <a href="{{ route('areas.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nueva
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Negocio</th>
                    <th>Área</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($areas as $area)
                <tr>
                    <td>{{ $area->id }}</td>
                    <td>{{ $area->negocio->nombre }}</td>
                    <td>{{ $area->nombre }}</td>
                    <td>
                        <a href="{{ route('areas.edit', $area) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('areas.destroy', $area) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">Sin registros</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection