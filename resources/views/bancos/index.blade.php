@extends('layouts.app')

@section('titulo', 'Bancos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Bancos</h4>
    <a href="{{ route('bancos.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bancos as $banco)
                <tr>
                    <td>{{ $banco->id }}</td>
                    <td>{{ $banco->nombre }}</td>
                    <td>
                        <a href="{{ route('bancos.edit', $banco) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('bancos.destroy', $banco) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="text-center text-muted">Sin registros</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection