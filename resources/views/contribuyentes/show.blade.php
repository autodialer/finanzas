@extends('layouts.app')

@section('titulo', $contribuyente->rfc)

@section('contenido')
@php
    $estados = [
        'pendiente'  => ['Enviada', 'bg-secondary'],
        'en_proceso' => ['En proceso en el SAT', 'bg-info text-dark'],
        'terminada'  => ['Descargada', 'bg-success'],
        'sin_datos'  => ['Sin facturas', 'bg-light text-dark border'],
        'error'      => ['Error', 'bg-danger'],
        'vencida'    => ['Vencida', 'bg-warning text-dark'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">{{ $contribuyente->nombre }}</h4>
        <div class="text-muted">
            {{ $contribuyente->rfc }} · {{ $contribuyente->tipo === 'moral' ? 'Persona moral' : 'Persona física' }}
            @if($contribuyente->regimen) · {{ $contribuyente->regimen }} {{ $contribuyente->nombreRegimen() }}@endif
        </div>
        <div class="small text-muted">Negocios: {{ $contribuyente->negocios->pluck('nombre')->implode(', ') ?: 'ninguno (asígnalos para conciliar gastos)' }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('facturas.index', ['contribuyente_id' => $contribuyente->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt"></i> Facturas</a>
        <a href="{{ route('facturas.faltantes', ['contribuyente_id' => $contribuyente->id]) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-exclamation-triangle"></i> Gastos sin factura</a>
        <a href="{{ route('contribuyentes.edit', $contribuyente) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
    </div>
</div>

@include('contribuyentes._errores')

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-cloud-download me-1"></i>Descargar del SAT</div>
            <div class="card-body">
                @if(!$contribuyente->efirmaVigente())
                    <div class="alert alert-warning small mb-0">
                        {{ $contribuyente->tieneEfirma() ? 'La e.firma está vencida.' : 'Falta cargar la e.firma.' }}
                        <a href="{{ route('contribuyentes.edit', $contribuyente) }}">Cárgala aquí</a> para descargar automáticamente.
                        Mientras, puedes subir los XML a mano (abajo).
                    </div>
                @else
                    <p class="small text-muted mb-2">
                        e.firma vigente hasta el {{ $contribuyente->efirma_vence->format('d/m/Y') }}.
                        @if($contribuyente->descarga_automatica) Se descarga sola todos los días. @endif
                        Descargado hasta: <strong>{{ $contribuyente->descargado_hasta?->format('d/m/Y H:i') ?? 'nunca' }}</strong>.
                    </p>
                    <form action="{{ route('contribuyentes.descargar', $contribuyente) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-5">
                            <label class="form-label small mb-0">Desde</label>
                            <input type="date" name="desde" class="form-control form-control-sm" required value="{{ old('desde', now()->subMonthNoOverflow()->startOfMonth()->toDateString()) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0">Hasta</label>
                            <input type="date" name="hasta" class="form-control form-control-sm" required value="{{ old('hasta', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-3">
                            <button class="btn btn-primary btn-sm w-100">Solicitar</button>
                        </div>
                    </form>
                    <div class="form-text">El SAT tarda de minutos a horas. Un mismo periodo solo se puede pedir 2 veces, así que no repitas la misma solicitud.</div>
                    <form action="{{ route('contribuyentes.verificar', $contribuyente) }}" method="POST" class="mt-2 d-inline">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Verificar ahora</button>
                    </form>
                @endif
                <form action="{{ route('facturas.conciliar', $contribuyente) }}" method="POST" class="mt-2 d-inline">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-link-45deg"></i> Vincular facturas con gastos</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-upload me-1"></i>Subir XML a mano</div>
            <div class="card-body">
                <form action="{{ route('facturas.subir') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="archivos[]" class="form-control form-control-sm mb-2" accept=".xml,.zip" multiple required>
                    <button class="btn btn-success btn-sm"><i class="bi bi-upload"></i> Subir</button>
                </form>
                <div class="form-text">Archivos .xml o .zip (como los que bajas del portal del SAT o te mandan por correo). Cada factura se asigna sola al RFC que corresponda.</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">Facturas por mes</div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Mes</th><th class="text-end">Emitidas</th><th class="text-end">Recibidas</th></tr></thead>
                <tbody>
                @forelse($resumen as $mes => $porDireccion)
                    <tr>
                        <td>{{ \Carbon\Carbon::createFromFormat('!Y-m', $mes)->translatedFormat('M Y') }}</td>
                        @foreach(['emitido', 'recibido'] as $dir)
                            <td class="text-end">
                                @if($d = $porDireccion[$dir] ?? null)
                                    <a href="{{ route('facturas.index', ['contribuyente_id' => $contribuyente->id, 'mes' => $mes, 'direccion' => $dir]) }}">{{ $d['facturas'] }}</a>
                                    <div class="small text-muted">${{ number_format($d['total'], 2) }}</div>
                                @else — @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Todavía no hay facturas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Solicitudes al SAT</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Periodo</th><th>Estado</th><th class="text-end">Nuevas</th></tr></thead>
                    <tbody>
                    @forelse($solicitudes as $s)
                        @php [$texto, $clase] = $estados[$s->estado] ?? [$s->estado, 'bg-secondary']; @endphp
                        <tr>
                            <td class="small">{{ $s->created_at->format('d/m H:i') }}</td>
                            <td class="small">{{ $s->direccion === 'emitido' ? 'Emitidas' : 'Recibidas' }}</td>
                            <td class="small">{{ $s->desde->format('d/m/y') }} – {{ $s->hasta->format('d/m/y') }}</td>
                            <td><span class="badge {{ $clase }}">{{ $texto }}</span><div class="small text-muted">{{ $s->mensaje }}</div></td>
                            <td class="text-end">{{ $s->cfdis_nuevos }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Sin solicitudes.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
