@extends('layouts.app')

@section('titulo', 'Nueva Área')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nueva Área</h4>
    <a href="{{ route('areas.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('areas.store') }}" method="POST">
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
                <label class="form-label">Nombre del Área</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>
@endsection