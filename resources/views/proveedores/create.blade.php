@extends('layouts.app')

@section('titulo', 'Nuevo Proveedor')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Proveedor</h4>
    <a href="{{ route('proveedores.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('proveedores.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Negocio</label>
                <select name="negocio_id" class="form-select">
                    <option value="">Selecciona un negocio</option>
                    @foreach($negocios as $negocio)
                    <option value="{{ $negocio->id }}" {{ old('negocio_id') == $negocio->id ? 'selected' : '' }}>
                        {{ $negocio->nombre }}
                    </option>
                    @endforeach
                </select>
                @error('negocio_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control" value="{{ old('telefono') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}">
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>
@endsection