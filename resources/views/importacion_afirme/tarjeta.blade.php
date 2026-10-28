@extends('layouts.app')

@section('titulo', 'Vista Previa — Tarjeta Afirme')

@use('App\Services\ConciliadorComprasMeses', 'C')

@php
    $estados = [
        C::CREAR      => ['Nueva (pago 1)', 'bg-primary'],
        C::SIN_PLAN   => ['Sin plan registrado', 'bg-secondary'],
        C::VERIFICADA => ['Coincide con el plan', 'bg-success'],
        C::DIFERENCIA => ['No coincide', 'bg-warning text-dark'],
    ];
@endphp

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Tarjeta Afirme — {{ $importacion['archivo'] }}</h4>
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
        La cuenta elegida (<strong>{{ $cuenta->nombre }}</strong>) no termina en los mismos dígitos que la tarjeta del estado de cuenta
        (····{{ $estado['ultimos_digitos'] }}). Verifica antes de confirmar.
    </div>
@endif

@if($estado['cargos_meses'] !== null && abs($estado['cargos_meses'] - $sumaMensualidades) > 0.01)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        La suma de los pagos requeridos (${{ number_format($sumaMensualidades, 2) }}) no coincide con "Cargos y compras a meses (capital)"
        del resumen (${{ number_format($estado['cargos_meses'], 2) }}). Puede haber compras a meses con intereses que no se leen aquí.
    </div>
@endif

<div class="card mb-3">
    <div class="card-body small">
        <strong>Tarjeta:</strong> {{ $cuenta->nombre }} — {{ $cuenta->negocio->nombre ?? '' }} ·
        <strong>Período:</strong> {{ \Carbon\Carbon::parse($estado['periodo_inicio'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($estado['periodo_fin'])->format('d/m/Y') }} ·
        <strong>Mensualidades del periodo:</strong> ${{ number_format($sumaMensualidades, 2) }}
        <table class="table table-sm mb-0 mt-2" style="max-width: 520px">
            <tr><td>Cargos regulares (no a meses)</td><td class="text-end text-danger">${{ number_format($estado['resumen']['cargos_regulares'] ?? 0, 2) }}</td></tr>
            <tr><td>Pagos y abonos</td><td class="text-end text-success">${{ number_format($estado['resumen']['pagos_abonos'] ?? 0, 2) }}
                <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Cuadra</span></td></tr>
            <tr class="text-muted"><td>Duplicados detectados</td><td class="text-end">{{ collect($movimientos)->where('duplicado', true)->count() }}</td></tr>
        </table>
    </div>
</div>

<form action="{{ route('importacion-afirme.tarjeta.confirmar') }}" method="POST">
    @csrf
    @if($filas)
    <h5 class="mt-2">Compras a meses sin intereses</h5>
    <p class="small text-muted mb-2">
        Cada compra a meses es un gasto por el monto original + su plan. Las mensualidades no son gastos: se pagan con el
        traspaso a la tarjeta. El estado de cuenta no trae la fecha de compra: se propone la del primer cargo.
    </p>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th style="min-width: 220px">Estado de cuenta</th>
                        <th class="text-end">Importes</th>
                        <th style="min-width: 220px">Resultado</th>
                        <th style="min-width: 380px">Alta de la compra a meses</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($filas as $i => $fila)
                    @php
                        [$texto, $clase] = $estados[$fila['accion']];
                        $puedeAlta = in_array($fila['accion'], [C::CREAR, C::SIN_PLAN], true);
                        $sug = $fila['sugerencia'];
                        $accion = old("filas.$i.accion", $fila['accion'] === C::CREAR ? 'alta' : 'ignorar');
                        $compra = $fila['compra_id'] ? ($compras[$fila['compra_id']] ?? null) : null;
                    @endphp
                    <tr class="{{ $errors->has("filas.$i") ? 'table-danger' : '' }}">
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="fw-semibold">{{ $fila['descripcion'] }}</div>
                            <div class="small text-muted">Cargo {{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }} · Pago {{ $fila['numero_pago'] }} de {{ $fila['total_pagos'] ?? '?' }}</div>
                        </td>
                        <td class="text-end small text-nowrap">
                            Original ${{ number_format($fila['monto_original'], 2) }}<br>
                            Pago ${{ number_format($fila['mensualidad'], 2) }}<br>
                            Saldo ${{ number_format($fila['saldo_pendiente'], 2) }}
                        </td>
                        <td>
                            <span class="badge {{ $clase }}">{{ $texto }}</span>
                            @if($compra)
                                <div class="small mt-1"><a href="{{ route('compras-meses.show', $compra) }}" target="_blank">{{ $compra->descripcion }}</a></div>
                            @endif
                            @foreach($fila['avisos'] as $aviso)
                                <div class="small text-danger mt-1"><i class="bi bi-exclamation-circle"></i> {{ $aviso }}</div>
                            @endforeach
                        </td>
                        <td>
                            @if($puedeAlta)
                            <div class="row g-1">
                                <div class="col-12">
                                    <select name="filas[{{ $i }}][accion]" class="form-select form-select-sm">
                                        <option value="alta" @selected($accion === 'alta')>Dar de alta (gasto + plan)</option>
                                        <option value="ignorar" @selected($accion === 'ignorar')>Ignorar</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="filas[{{ $i }}][descripcion]" class="form-control form-control-sm" maxlength="255"
                                           value="{{ old("filas.$i.descripcion", $sug['descripcion']) }}" placeholder="Descripción">
                                </div>
                                <div class="col-12">
                                    <select name="filas[{{ $i }}][categoria_id]" class="form-select form-select-sm">
                                        <option value="">Categoría del gasto...</option>
                                        @foreach($categoriasGasto as $categoria)
                                            <option value="{{ $categoria->id }}" @selected(old("filas.$i.categoria_id") == $categoria->id)>{{ $categoria->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Meses</label>
                                    <input type="number" min="1" max="60" name="filas[{{ $i }}][meses]" class="form-control form-control-sm" value="{{ old("filas.$i.meses", $sug['meses']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Mensualidad</label>
                                    <input type="number" step="0.01" name="filas[{{ $i }}][mensualidad]" class="form-control form-control-sm" value="{{ old("filas.$i.mensualidad", $sug['mensualidad']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Fecha de compra{{ $fila['accion'] === C::SIN_PLAN ? ' (estimada)' : '' }}</label>
                                    <input type="date" name="filas[{{ $i }}][fecha_compra]" class="form-control form-control-sm" value="{{ old("filas.$i.fecha_compra", $sug['fecha_compra']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Primer cargo</label>
                                    <input type="date" name="filas[{{ $i }}][fecha_primer_cargo]" class="form-control form-control-sm" value="{{ old("filas.$i.fecha_primer_cargo", $sug['fecha_primer_cargo']) }}">
                                </div>
                            </div>
                            @else
                                <span class="small text-muted">Ya registrada; no se guarda nada.</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($movimientos)
    <h5>Cargos y abonos regulares (no a meses)</h5>
    <p class="small text-muted mb-2">
        Los cargos se registran como gastos de la tarjeta (con la categoría usada antes para el mismo comercio, si existe).
        Los pagos a la tarjeta son traspasos desde la cuenta con la que pagaste; si ya los registraste desde el estado de cuenta de débito, aparecen como duplicados.
    </p>
    @endif

    <div class="card">
        @if($movimientos)
            @include('importacion_afirme._movimientos', ['campo' => 'movimientos', 'filas' => $movimientos])
        @endif
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted">Las filas en "Ignorar" no se guardan. Nada se guarda hasta que confirmes.</span>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg me-1"></i>Confirmar importación
            </button>
        </div>
    </div>
</form>
@endsection
