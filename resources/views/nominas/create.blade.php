@extends('layouts.app')

@section('titulo', 'Nuevo Período de Nómina')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Período de Nómina</h4>
    <a href="{{ route('nominas.index') }}" class="btn btn-secondary btn-sm">
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

        <p class="text-muted small">Al guardar, se generará automáticamente una línea por cada empleado activo del negocio seleccionado.</p>

        <form action="{{ route('nominas.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Negocio <span class="text-danger">*</span></label>
                    <select name="negocio_id" class="form-select" required>
                        <option value="">Selecciona un negocio</option>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ old('negocio_id') == $negocio->id ? 'selected' : '' }}>
                            {{ $negocio->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Cuenta de pago <span class="text-danger">*</span></label>
                    <select name="cuenta_id" class="form-select" required>
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_id') == $cuenta->id ? 'selected' : '' }}>
                            {{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Nombre del período <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}"
                        placeholder="Ej. 1a Quincena Mayo 2026 / Semana 1 Mayo 2026" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Fecha inicio <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Fecha fin <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_fin" class="form-control" value="{{ old('fecha_fin') }}" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Crear Período y Generar Nómina
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
