@extends('layouts.app')

@section('titulo', 'Editar Empresa')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Empresa</h4>
    <a href="{{ route('empresas.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif
        <form action="{{ route('empresas.update', $empresa) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Negocio <span class="text-danger">*</span></label>
                    <select name="negocio_id" class="form-select" required>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ $empresa->negocio_id == $negocio->id ? 'selected' : '' }}>
                            {{ $negocio->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nombre de la empresa <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $empresa->nombre) }}" required>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Actualizar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
