@extends('layouts.app')

@section('titulo', 'Facturas')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Facturas (CFDI)</h4>
    @if($contribuyente)
        <a href="{{ route('facturas.faltantes', ['contribuyente_id' => $contribuyente->id, 'mes' => $mes]) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-exclamation-triangle"></i> Gastos sin factura</a>
    @endif
</div>

@include('contribuyentes._errores')

@if(!$contribuyente)
    <div class="alert alert-info">Primero <a href="{{ route('contribuyentes.create') }}">da de alta un contribuyente</a>.</div>
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
    <div class="col-md-2">
        <label class="form-label small mb-0">Tipo</label>
        <select name="direccion" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="recibido" @selected($direccion === 'recibido')>Recibidas (gastos)</option>
            <option value="emitido" @selected($direccion === 'emitido')>Emitidas (ingresos)</option>
        </select>
    </div>
    @if($direccion === 'recibido')
    <div class="col-md-2">
        <label class="form-label small mb-0">Vínculo</label>
        <select name="vinculo" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Todas</option>
            <option value="libres" @selected($vinculo === 'libres')>Sin gasto</option>
            <option value="vinculadas" @selected($vinculo === 'vinculadas')>Con gasto</option>
        </select>
    </div>
    @endif
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Facturas</div><div class="fs-5 fw-semibold">{{ $totales['facturas'] }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total vigente (ingresos − egresos)</div><div class="fs-5 fw-semibold">${{ number_format($totales['total'], 2) }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">IVA trasladado</div><div class="fs-5 fw-semibold">${{ number_format($totales['iva'], 2) }}</div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>{{ $direccion === 'recibido' ? 'Emisor' : 'Receptor' }}</th>
                    <th>Concepto</th>
                    <th>Tipo</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">IVA</th>
                    @if($direccion === 'recibido')<th>Gasto</th>@endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($facturas as $f)
                <tr class="{{ $f->estado === 'cancelado' ? 'text-decoration-line-through text-muted' : '' }}">
                    <td class="text-nowrap small">{{ $f->fecha->format('d/m/Y') }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $f->contraparteNombre() }}</div>
                        <div class="small text-muted">{{ $f->contraparteRfc() }} @if($f->serie_folio)· {{ $f->serie_folio }}@endif</div>
                    </td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($f->conceptos, 80) }}</td>
                    <td class="small">
                        {{ \App\Models\Cfdi::TIPOS[$f->tipo] ?? $f->tipo }}
                        @if($f->metodo_pago)<span class="badge bg-light text-dark border">{{ $f->metodo_pago }}</span>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        ${{ number_format($f->total, 2) }}
                        @if($f->moneda !== 'MXN' && $f->moneda !== 'XXX')<div class="small text-muted">{{ $f->moneda }} · ${{ number_format($f->totalMxn(), 2) }} MXN</div>@endif
                    </td>
                    <td class="text-end small">${{ number_format($f->iva_trasladado, 2) }}</td>
                    @if($direccion === 'recibido')
                    <td class="small">
                        @if($f->gasto)
                            <a href="{{ route('gastos.edit', $f->gasto) }}">{{ \Illuminate\Support\Str::limit($f->gasto->concepto, 30) }}</a>
                            <span class="badge {{ $f->vinculo === 'auto' ? 'bg-info text-dark' : 'bg-secondary' }}">{{ $f->vinculo }}</span>
                            <form action="{{ route('facturas.desvincular', $f) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-link btn-sm p-0 text-danger" title="Desvincular"><i class="bi bi-x-circle"></i></button>
                            </form>
                        @elseif($f->tipo === 'I')
                            <span class="badge bg-warning text-dark">Sin gasto</span>
                        @endif
                    </td>
                    @endif
                    <td><a href="{{ route('facturas.xml', $f) }}" class="btn btn-outline-secondary btn-sm" title="Descargar XML"><i class="bi bi-filetype-xml"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay facturas en este mes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
