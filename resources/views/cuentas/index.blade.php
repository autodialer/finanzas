@extends('layouts.app')

@section('titulo', 'Cuentas')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Cuentas</h4>
    <a href="{{ route('cuentas.create') }}" class="btn btn-primary btn-sm">
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
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Banco</th>
                    <th>Número</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cuentas as $cuenta)
                <tr>
                    <td>{{ $cuenta->id }}</td>
                    <td>{{ $cuenta->negocio->nombre }}</td>
                    <td>{{ $cuenta->nombre }}</td>
                    <td>
                        @if($cuenta->tipo == 'efectivo')
                        <span class="badge bg-success">Efectivo</span>
                        @else
                        <span class="badge bg-primary">Banco</span>
                        @endif
                    </td>
                    <td>{{ $cuenta->banco->nombre ?? '-' }}</td>
                    <td>{{ $cuenta->numero ?? '-' }}</td>
                    <td>
                        <a href="{{ route('cuentas.edit', $cuenta) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('cuentas.destroy', $cuenta) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">Sin registros</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection