@extends('layouts.app')

@section('titulo', 'Importar Gastos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Importar Gastos desde Excel (American Express)</h4>
    <a href="{{ route('gastos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Regresar
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('gastos.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Negocio</label>
                        <select name="negocio_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            @foreach($negocios as $negocio)
                                <option value="{{ $negocio->id }}" {{ old('negocio_id') == $negocio->id ? 'selected' : '' }}>
                                    {{ $negocio->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cuenta (tarjeta de crédito)</label>
                        <select name="cuenta_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}" {{ old('cuenta_id') == $cuenta->id ? 'selected' : '' }}>
                                    {{ $cuenta->nombre }}
                                    @if($cuenta->negocio) — {{ $cuenta->negocio->nombre }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Categoría para todos los gastos</label>
                        <select name="categoria_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}" {{ old('categoria_id') == $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Puedes editar la categoría de cada gasto después de importar.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Archivo Excel (.xlsx)</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls" required>
                        @error('archivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Importar gastos
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle me-1"></i>Formato esperado
            </div>
            <div class="card-body">
                <p class="mb-2">El sistema lee el estado de cuenta Excel de <strong>American Express</strong> tal como lo descarga del portal.</p>
                <p class="mb-2">Se toman estas columnas automáticamente:</p>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light">
                        <tr>
                            <th>Columna</th>
                            <th>Campo registrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Fecha</td><td>Fecha del gasto</td></tr>
                        <tr><td>Descripción</td><td>Concepto</td></tr>
                        <tr><td>Importe</td><td>Monto</td></tr>
                    </tbody>
                </table>
                <ul class="small text-muted mb-0">
                    <li>La forma de pago se registra automáticamente como <strong>Tarjeta</strong></li>
                    <li>Los gastos quedan sin IVA ni propina; puedes editarlos después</li>
                    <li>Filas sin fecha, descripción o monto se omiten</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
