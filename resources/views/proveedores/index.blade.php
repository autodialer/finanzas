@extends('layouts.app')

@section('titulo', 'Proveedores')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Proveedores</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('proveedores.import.form') }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-arrow-up"></i> Importar CSV
        </a>
        <a href="{{ route('proveedores.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Negocio</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($proveedores as $proveedor)
                <tr>
                    <td>{{ $proveedor->id }}</td>
                    <td>{{ $proveedor->negocio->nombre }}</td>
                    <td>{{ $proveedor->nombre }}</td>
                    <td>{{ $proveedor->telefono ?? '-' }}</td>
                    <td>{{ $proveedor->email ?? '-' }}</td>
                    <td>
                        <a href="{{ route('proveedores.edit', $proveedor) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('proveedores.destroy', $proveedor) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">Sin registros</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection