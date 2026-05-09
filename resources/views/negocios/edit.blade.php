@extends('layouts.app')

@section('titulo', 'Editar Negocio')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Negocio</h4>
    <a href="{{ route('negocios.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('negocios.update', $negocio) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $negocio->nombre) }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion', $negocio->descripcion) }}">
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</div>
@endsection