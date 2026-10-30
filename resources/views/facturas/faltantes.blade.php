@extends('layouts.app')

@section('titulo', 'Gastos sin factura')

@section('contenido')
@php
    $urgencias = [
        'a_tiempo' => ['bg-success', 'A tiempo'],
        'urgente'  => ['bg-warning text-dark', 'Urgente'],
        'vencida'  => ['bg-danger', 'Pasó el mes'],
        'tarde'    => ['bg-dark', 'Después de la declaración'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Gastos sin factura</h4>
    @if($contribuyente)
        <form action="{{ route('facturas.conciliar', $contribuyente) }}" method="POST">
            @csrf
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-link-45deg"></i> Volver a vincular automáticamente</button>
        </form>
    @endif
</div>

@include('contribuyentes._errores')

@if(!$contribuyente)
    <div class="alert alert-info">Primero <a href="{{ route('contribuyentes.create') }}">da de alta un contribuyente</a> y asígnale sus negocios.</div>
@else
<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
        <label class="form-label small mb-0">Contribuyente</label>
        <select name="contribuyente_id" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($contribuyentes as $c)
                <option value="{{ $c->id }}" @selected($contribuyente->id === $c->id)>{{ $c->rfc }} — {{ $c->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0">Mes</label>
        <input type="month" name="mes" class="form-control form-control-sm" value="{{ $mes }}" onchange="this.form.submit()">
    </div>
    <div class="col-md-6 small text-muted">
        Negocios: {{ $contribuyente->negocios->pluck('nombre')->implode(', ') ?: 'ninguno asignado' }}
    </div>
</form>

@if($pendientesPorMes->isNotEmpty())
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($pendientesPorMes as $m => $p)
        <a href="{{ route('facturas.faltantes', ['contribuyente_id' => $contribuyente->id, 'mes' => $m]) }}"
           class="btn btn-sm {{ $m === $mes ? 'btn-dark' : 'btn-outline-dark' }}">
            {{ \Carbon\Carbon::createFromFormat('!Y-m', $m)->translatedFormat('M Y') }}:
            <strong>{{ $p['gastos'] }}</strong> · ${{ number_format($p['monto'], 0) }}
        </a>
    @endforeach
</div>
@endif

<div class="alert alert-light border small">
    Pide la factura <strong>antes de que termine el mes de la compra</strong>: muchos comercios ya no facturan después.
    La declaración del mes se presenta a más tardar el <strong>día 17 del mes siguiente</strong>.
    Si un gasto no lleva factura (propinas, comisiones sin CFDI, gastos personales), márcalo para que no aparezca aquí.
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Gasto</th>
                    <th class="text-end">Monto</th>
                    <th>Pedir factura antes de</th>
                    <th style="min-width: 300px">Facturas que podrían ser</th>
                    <th style="min-width: 200px">No requiere factura</th>
                </tr>
            </thead>
            <tbody>
            @forelse($gastos as $g)
                @php [$clase, $texto] = $urgencias[$g->limite['urgencia']]; @endphp
                <tr>
                    <td class="text-nowrap small">{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                    <td>
                        <a href="{{ route('gastos.edit', $g) }}" class="fw-semibold small">{{ $g->concepto }}</a>
                        <div class="small text-muted">{{ $g->categoria?->nombre }} · {{ $g->cuenta?->nombre }} · {{ $g->negocio?->nombre }}</div>
                    </td>
                    <td class="text-end text-nowrap">${{ number_format($g->monto, 2) }}</td>
                    <td class="small text-nowrap">
                        {{ $g->limite['fin_mes']->format('d/m/Y') }}
                        <div><span class="badge {{ $clase }}">{{ $texto }}@if($g->limite['urgencia'] === 'a_tiempo' || $g->limite['urgencia'] === 'urgente') · {{ $g->limite['dias'] }} días @endif</span></div>
                    </td>
                    <td>
                        @forelse($g->candidatas as $f)
                            <form action="{{ route('facturas.vincular') }}" method="POST" class="d-flex align-items-center gap-1 mb-1">
                                @csrf
                                <input type="hidden" name="gasto_id" value="{{ $g->id }}">
                                <input type="hidden" name="cfdi_id" value="{{ $f->id }}">
                                <button class="btn btn-outline-success btn-sm py-0" title="Vincular"><i class="bi bi-link"></i></button>
                                <span class="small">{{ $f->fecha->format('d/m') }} · {{ \Illuminate\Support\Str::limit($f->nombre_emisor, 28) }} · ${{ number_format($f->totalMxn(), 2) }}</span>
                            </form>
                        @empty
                            <span class="small text-muted">Ninguna todavía</span>
                        @endforelse
                    </td>
                    <td>
                        <form action="{{ route('facturas.sin-factura', $g) }}" method="POST" class="d-flex gap-1">
                            @csrf
                            <select name="motivo" class="form-select form-select-sm" required>
                                <option value="">Motivo...</option>
                                @foreach(['No deducible / personal', 'Comisión o interés bancario', 'Propina', 'Extranjero sin CFDI', 'Pago a tarjeta o traspaso', 'Otro'] as $motivo)
                                    <option>{{ $motivo }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-outline-secondary btn-sm" title="Marcar"><i class="bi bi-check2"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> Todos los gastos del mes tienen factura o están marcados como sin factura.</td></tr>
            @endforelse
            </tbody>
            @if($gastos->isNotEmpty())
            <tfoot class="table-light">
                <tr><td colspan="2" class="fw-semibold">{{ $gastos->count() }} gastos sin factura</td><td class="text-end fw-semibold">${{ number_format($gastos->sum('monto'), 2) }}</td><td colspan="3"></td></tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@if($sinFacturaMarcados->isNotEmpty())
<details class="card">
    <summary class="card-header small">Marcados como "no requiere factura" este mes ({{ $sinFacturaMarcados->count() }})</summary>
    <table class="table table-sm mb-0">
        @foreach($sinFacturaMarcados as $g)
            <tr>
                <td class="small">{{ \Carbon\Carbon::parse($g->fecha)->format('d/m/Y') }}</td>
                <td class="small">{{ $g->concepto }}</td>
                <td class="small text-end">${{ number_format($g->monto, 2) }}</td>
                <td class="small text-muted">{{ $g->motivo_sin_factura }}</td>
                <td class="text-end">
                    <form action="{{ route('facturas.requiere-factura', $g) }}" method="POST">
                        @csrf
                        <button class="btn btn-link btn-sm p-0">Sí requiere</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </table>
</details>
@endif
@endif
@endsection
