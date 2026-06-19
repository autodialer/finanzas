@extends('layouts.app')

@section('titulo', 'Empresas')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Empresas</h4>
    <a href="{{ route('empresas.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nueva Empresa
    </a>
</div>

@if(session('exito'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('exito') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Negocio</th>
                    <th>Empresa</th>
                    <th>Empleados</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($empresas as $empresa)
                <tr>
                    <td>{{ $empresa->negocio->nombre }}</td>
                    <td>{{ $empresa->nombre }}</td>
                    <td>{{ $empresa->empleados_count ?? 0 }}</td>
                    <td>
                        <a href="{{ route('empresas.edit', $empresa) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('empresas.destroy', $empresa) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar empresa?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">Sin empresas registradas</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
