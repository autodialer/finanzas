@extends('layouts.app')

@section('titulo', 'Contribuyentes')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Contribuyentes (RFC)</h4>
    <a href="{{ route('contribuyentes.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo contribuyente
    </a>
</div>

@include('contribuyentes._errores')

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>RFC</th>
                    <th>Nombre</th>
                    <th>Régimen</th>
                    <th>Negocios</th>
                    <th>e.firma</th>
                    <th>Última descarga</th>
                    <th class="text-center">Gastos sin factura<br><span class="small fw-normal">mes pasado y actual</span></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($contribuyentes as $c)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('contribuyentes.show', $c) }}">{{ $c->rfc }}</a></td>
                    <td>{{ $c->nombre }} <span class="badge bg-light text-dark border">{{ $c->tipo === 'moral' ? 'Moral' : 'Física' }}</span></td>
                    <td class="small">{{ $c->regimen }} {{ $c->nombreRegimen() }}</td>
                    <td class="small">{{ $c->negocios->pluck('nombre')->implode(', ') ?: '—' }}</td>
                    <td>
                        @if($c->efirmaVigente())
                            <span class="badge bg-success">Vence {{ $c->efirma_vence->format('d/m/Y') }}</span>
                        @elseif($c->tieneEfirma())
                            <span class="badge bg-danger">Vencida</span>
                        @else
                            <span class="badge bg-secondary">Sin cargar</span>
                        @endif
                    </td>
                    <td class="small">{{ $c->descargado_hasta?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-center">
                        @if($sinFactura[$c->id])
                            <a href="{{ route('facturas.faltantes', ['contribuyente_id' => $c->id]) }}" class="badge bg-warning text-dark text-decoration-none">{{ $sinFactura[$c->id] }}</a>
                        @else
                            <span class="badge bg-success">0</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('contribuyentes.edit', $c) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Da de alta tus RFCs (Massivecaller, tu persona física y tus otras empresas) para empezar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
