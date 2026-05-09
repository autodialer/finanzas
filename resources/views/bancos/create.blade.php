@extends('layouts.app')

@section('titulo', 'Nuevo Banco')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Banco</h4>
    <a href="{{ route('bancos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('bancos.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nombre del Banco</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>
@endsection