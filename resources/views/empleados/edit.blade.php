@extends('layouts.app')

@section('titulo', 'Editar Empleado')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Empleado</h4>
    <a href="{{ route('empleados.index') }}" class="btn btn-secondary btn-sm">
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

        <form action="{{ route('empleados.update', $empleado) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Negocio <span class="text-danger">*</span></label>
                    <select name="negocio_id" class="form-select" required>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ $empleado->negocio_id == $negocio->id ? 'selected' : '' }}>
                            {{ $negocio->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $empleado->nombre) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" class="form-control" value="{{ old('cargo', $empleado->cargo) }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Salario por período <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="salario" class="form-control" value="{{ old('salario', $empleado->salario) }}" min="0" step="0.01" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="1" {{ $empleado->activo ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ !$empleado->activo ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Actualizar Empleado
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
