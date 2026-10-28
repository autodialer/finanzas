@extends('layouts.app')

@section('titulo', 'Vista Previa — Afirme')

@php
    $etiquetasTipo = ['gasto' => 'Gasto', 'ingreso' => 'Ingreso', 'traspaso' => 'Traspaso', 'prestamo' => 'Préstamo', 'ignorar' => 'Ignorar'];
    $conteo = collect($filas)->countBy('tipo');
@endphp

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Vista previa — {{ $importacion['archivo'] }}</h4>
    <form action="{{ route('importacion-afirme.cancelar') }}" method="POST">
        @csrf
        <button class="btn btn-secondary btn-sm"><i class="bi bi-x-lg"></i> Cancelar</button>
    </form>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa estas filas:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($cuentaDistinta)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        La cuenta elegida (<strong>{{ $cuenta->nombre }}</strong>, {{ $cuenta->numero }}) no coincide con la del estado de cuenta
        (cuenta {{ $estado['numero_cuenta'] }}, CLABE {{ $estado['clabe'] }}). Verifica antes de confirmar.
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-body small">
                <div class="mb-2">
                    <strong>Cuenta:</strong> {{ $cuenta->nombre }} — {{ $cuenta->negocio->nombre ?? '' }}<br>
                    <strong>Período:</strong> {{ \Carbon\Carbon::parse($estado['periodo_inicio'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($estado['periodo_fin'])->format('d/m/Y') }}
                </div>
                <table class="table table-sm mb-0">
                    <tr><td>Saldo inicial</td><td class="text-end">${{ number_format($estado['resumen']['saldo_inicial'], 2) }}</td></tr>
                    <tr><td>+ Depósitos</td><td class="text-end text-success">${{ number_format($estado['resumen']['depositos'], 2) }}</td></tr>
                    <tr><td>− Retiros</td><td class="text-end text-danger">${{ number_format($estado['resumen']['retiros'], 2) }}</td></tr>
                    <tr class="fw-semibold"><td>Saldo al corte</td><td class="text-end">${{ number_format($estado['resumen']['saldo_corte'], 2) }}
                        <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Cuadra</span></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-body small">
                <strong>Tipos sugeridos</strong>
                <ul class="list-unstyled mb-0 mt-2">
                    @foreach($etiquetasTipo as $tipo => $etiqueta)
                        <li>{{ $etiqueta }}: <strong>{{ $conteo[$tipo] ?? 0 }}</strong></li>
                    @endforeach
                    <li class="text-muted">Duplicados detectados: {{ collect($filas)->where('duplicado', true)->count() }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('importacion-afirme.confirmar') }}" method="POST">
    @csrf
    <div class="card">
        @include('importacion_afirme._movimientos', ['campo' => 'filas'])
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted">Las filas en "Ignorar" no se guardan.</span>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg me-1"></i>Confirmar importación
            </button>
        </div>
    </div>
</form>
@endsection
