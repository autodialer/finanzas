@extends('layouts.app')

@section('titulo', 'Editar Banco')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Banco</h4>
    <a href="{{ route('bancos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('bancos.update', $banco) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Nombre del Banco</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $banco->nombre) }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</div>
@endsection