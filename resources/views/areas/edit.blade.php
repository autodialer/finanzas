@extends('layouts.app')

@section('titulo', 'Editar Área')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Área</h4>
    <a href="{{ route('areas.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('areas.update', $area) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Negocio</label>
                <select name="negocio_id" class="form-select">
                    <option value="">Selecciona un negocio</option>
                    @foreach($negocios as $negocio)
                    <option value="{{ $negocio->id }}" {{ old('negocio_id', $area->negocio_id) == $negocio->id ? 'selected' : '' }}>
                        {{ $negocio->nombre }}
                    </option>
                    @endforeach
                </select>
                @error('negocio_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Nombre del Área</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $area->nombre) }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</div>
@endsection