@extends('layouts.app')

@section('titulo', 'Reporte de Cuentas')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Reporte de Cuentas</h4>
        <small class="text-muted">Saldo acumulado, ingresos y egresos por cuenta y negocio</small>
    </div>
    <a href="{{ route('reportes.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-bar-chart me-1"></i>Reporte general
    </a>
</div>

{{-- Filtros --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('reportes.cuentas') }}" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold small">Negocio</label>
                <select name="negocio_id" id="filtroNegocio" class="form-select form-select-sm">
                    <option value="">Todos los negocios</option>
                    @foreach($negocios as $neg)
                        <option value="{{ $neg->id }}" {{ $negocioId == $neg->id ? 'selected' : '' }}>
                            {{ $neg->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold small">Cuenta</label>
                <select name="cuenta_id" id="filtroCuenta" class="form-select form-select-sm">
                    <option value="">Todas las cuentas</option>
                    @foreach($cuentasDisponibles->groupBy(fn($c) => $c->negocio->nombre ?? 'Sin negocio') as $nombreNegocio => $ctas)
                        <optgroup label="{{ $nombreNegocio }}">
                            @foreach($ctas as $cta)
                                <option value="{{ $cta->id }}"
                                        data-negocio="{{ $cta->negocio_id }}"
                                        {{ $cuentaId == $cta->id ? 'selected' : '' }}>
                                    {{ $cta->nombre }}@if($cta->numero) · Nº {{ $cta->numero }}@endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold small">Fecha inicio</label>
                <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="{{ $fechaInicio }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold small">Fecha fin</label>
                <input type="date" name="fecha_fin" class="form-control form-control-sm" value="{{ $fechaFin }}">
            </div>
            <div class="col-lg-2 col-md-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="{{ route('reportes.cuentas') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

@if(collect($reporte)->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No hay cuentas para mostrar.
    </div>
@else

{{-- Totales globales del período --}}
@php
    $granTotalIng   = collect($reporte)->flatten(1)->sum('total_ingresos');
    $granTotalEgr   = collect($reporte)->flatten(1)->sum('total_egresos');
    $granSaldoFinal = collect($reporte)->flatten(1)->sum('saldo_final');
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Ingresos del período</div>
                <div class="fs-4 fw-bold text-success">${{ number_format($granTotalIng, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Egresos del período</div>
                <div class="fs-4 fw-bold text-danger">${{ number_format($granTotalEgr, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Saldo total al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</div>
                <div class="fs-4 fw-bold {{ $granSaldoFinal >= 0 ? 'text-primary' : 'text-danger' }}">
                    ${{ number_format($granSaldoFinal, 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reporte por negocio --}}
@foreach($reporte as $negocioNombre => $cuentas)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-dark text-white fw-semibold py-2">
        <i class="bi bi-building me-2"></i>{{ $negocioNombre }}
    </div>
    <div class="card-body p-0">

        @foreach($cuentas as $item)
        @php $cuenta = $item['cuenta']; @endphp
        <div class="border-bottom">
            {{-- Encabezado de cuenta --}}
            <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light">
                <div>
                    <i class="bi bi-credit-card me-2 text-primary"></i>
                    <strong>{{ $cuenta->nombre }}</strong>
                    <span class="badge bg-secondary ms-2">{{ ucfirst($cuenta->tipo) }}</span>
                    @if($cuenta->banco)
                        <span class="text-muted small ms-2">{{ $cuenta->banco->nombre }}</span>
                    @endif
                    @if($cuenta->numero)
                        <span class="text-muted small ms-1">· Nº {{ $cuenta->numero }}</span>
                    @endif
                </div>
                <div class="d-flex gap-3 text-end small">
                    <span class="text-success fw-semibold" title="Ingresos del período">
                        <i class="bi bi-arrow-down-circle me-1"></i>${{ number_format($item['total_ingresos'], 2) }}
                    </span>
                    <span class="text-danger fw-semibold" title="Egresos del período">
                        <i class="bi bi-arrow-up-circle me-1"></i>${{ number_format($item['total_egresos'], 2) }}
                    </span>
                    <span class="fw-bold {{ $item['saldo_final'] >= 0 ? 'text-primary' : 'text-danger' }}" title="Saldo acumulado al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}">
                        <i class="bi bi-wallet2 me-1"></i>${{ number_format($item['saldo_final'], 2) }}
                    </span>
                </div>
            </div>

            {{-- Tabla de movimientos diarios --}}
            @if(count($item['dias']) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha</th>
                            <th class="text-end text-success">Entradas</th>
                            <th class="text-end text-danger">Salidas</th>
                            <th class="text-end text-primary">Saldo acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Fila de saldo anterior --}}
                        <tr class="table-secondary text-muted" style="font-size:0.8rem;">
                            <td class="ps-4 fst-italic">Saldo anterior al {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }}</td>
                            <td class="text-end">—</td>
                            <td class="text-end">—</td>
                            <td class="text-end fw-semibold {{ $item['saldo_anterior'] >= 0 ? 'text-primary' : 'text-danger' }}">
                                ${{ number_format($item['saldo_anterior'], 2) }}
                            </td>
                        </tr>
                        @foreach($item['dias'] as $dia)
                        <tr>
                            <td class="ps-4">{{ \Carbon\Carbon::parse($dia['fecha'])->translatedFormat('d M Y') }}</td>
                            <td class="text-end text-success">
                                {{ $dia['ingresos'] > 0 ? '$'.number_format($dia['ingresos'], 2) : '—' }}
                            </td>
                            <td class="text-end text-danger">
                                {{ $dia['egresos'] > 0 ? '$'.number_format($dia['egresos'], 2) : '—' }}
                            </td>
                            <td class="text-end fw-semibold {{ $dia['saldo'] >= 0 ? 'text-primary' : 'text-danger' }}">
                                ${{ number_format($dia['saldo'], 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="ps-4">Total del período</td>
                            <td class="text-end text-success">${{ number_format($item['total_ingresos'], 2) }}</td>
                            <td class="text-end text-danger">${{ number_format($item['total_egresos'], 2) }}</td>
                            <td class="text-end {{ $item['saldo_final'] >= 0 ? 'text-primary' : 'text-danger' }}">
                                ${{ number_format($item['saldo_final'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            {{-- Sin movimientos pero sí mostramos el saldo anterior --}}
            <div class="px-4 py-2 d-flex justify-content-between align-items-center">
                <span class="text-muted small fst-italic">Sin movimientos en el período.</span>
                <span class="small fw-semibold {{ $item['saldo_anterior'] >= 0 ? 'text-primary' : 'text-danger' }}">
                    Saldo: ${{ number_format($item['saldo_anterior'], 2) }}
                </span>
            </div>
            @endif
        </div>
        @endforeach

    </div>
</div>
@endforeach

@endif
@endsection

@section('scripts')
<script>
    // Al elegir un negocio, deja en el selector de cuentas solo las de ese negocio.
    document.addEventListener('DOMContentLoaded', function () {
        const selNegocio = document.getElementById('filtroNegocio');
        const selCuenta  = document.getElementById('filtroCuenta');
        if (!selNegocio || !selCuenta) return;

        function filtrarCuentas() {
            const negocio = selNegocio.value;
            let ocultaSeleccionada = false;

            selCuenta.querySelectorAll('optgroup').forEach(function (grupo) {
                let visibles = 0;
                grupo.querySelectorAll('option').forEach(function (opt) {
                    const coincide = !negocio || opt.dataset.negocio === negocio;
                    opt.hidden = !coincide;
                    opt.disabled = !coincide;
                    if (coincide) visibles++;
                    if (!coincide && opt.selected) ocultaSeleccionada = true;
                });
                grupo.hidden = visibles === 0;
            });

            if (ocultaSeleccionada) selCuenta.value = '';
        }

        selNegocio.addEventListener('change', filtrarCuentas);
        filtrarCuentas();
    });
</script>
@endsection
