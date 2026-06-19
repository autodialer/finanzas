@extends('layouts.app')

@section('titulo', 'Categorías')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Categorías</h4>
    <a href="{{ route('categorias.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nueva
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categorias as $categoria)
                <tr>
                    <td>{{ $categoria->id }}</td>
                    <td>{{ $categoria->nombre }}</td>
                    <td>
                        @if($categoria->tipo == 'ingreso')
                        <span class="badge bg-success">Ingreso</span>
                        @elseif($categoria->tipo == 'gasto')
                        <span class="badge bg-danger">Gasto</span>
                        @else
                        <span class="badge bg-secondary">Ambos</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('categorias.destroy', $categoria) }}" method="POST" class="d-inline">
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