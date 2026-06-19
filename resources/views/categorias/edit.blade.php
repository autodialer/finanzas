@extends('layouts.app')

@section('titulo', 'Editar Categoría')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Categoría</h4>
    <a href="{{ route('categorias.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('categorias.update', $categoria) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $categoria->nombre) }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Selecciona el tipo</option>
                    <option value="ingreso" {{ old('tipo', $categoria->tipo) == 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                    <option value="gasto" {{ old('tipo', $categoria->tipo) == 'gasto' ? 'selected' : '' }}>Gasto</option>
                    <option value="ambos" {{ old('tipo', $categoria->tipo) == 'ambos' ? 'selected' : '' }}>Ambos</option>
                </select>
                @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</div>
@endsection