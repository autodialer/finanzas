@extends('layouts.app')

@section('titulo', 'Importar Estado de Cuenta')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Importar estado de cuenta Afirme / Banregio (PDF)</h4>
    <a href="{{ route('gastos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Regresar
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>No se puede importar este estado de cuenta:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('importacion-afirme.analizar') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cuenta o tarjeta</label>
                        <select name="cuenta_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}" {{ old('cuenta_id') == $cuenta->id ? 'selected' : '' }}>
                                    {{ $cuenta->nombre }}
                                    @if($cuenta->negocio) — {{ $cuenta->negocio->nombre }}@endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Todos los movimientos se registran en esta cuenta y en su negocio.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Estado de cuenta (.pdf)</label>
                        <input type="file" name="archivo" class="form-control" accept=".pdf" required>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-search me-1"></i>Revisar movimientos
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle me-1"></i>Cómo funciona
            </div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Acepta el estado de cuenta Afirme de la <strong>Cuenta Visión</strong> o de la <strong>tarjeta de crédito</strong>, y el de la <strong>tarjeta de crédito Banregio</strong>; el tipo se detecta solo.</li>
                    <li>Antes de continuar se verifica que los movimientos cuadren con el resumen del estado de cuenta. Si no cuadra, no se importa.</li>
                    <li>Verás una vista previa con un tipo sugerido para cada movimiento. Nada se guarda hasta que confirmes.</li>
                    <li>Los movimientos ya importados (misma fecha, referencia y monto) se marcan para no duplicarlos.</li>
                    <li>Si subes el estado de cuenta de la <strong>tarjeta de crédito</strong> Afirme, se lee la sección de <strong>compras a meses sin intereses</strong>: se comparan con tus compras a meses registradas y puedes dar de alta las que falten.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
