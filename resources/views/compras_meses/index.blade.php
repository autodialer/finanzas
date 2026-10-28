@extends('layouts.app')

@section('titulo', 'Compras a meses')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Compras a meses sin intereses</h4>
    <a href="{{ route('compras-meses.create') }}" class="btn btn-danger btn-sm">
        <i class="bi bi-plus-lg"></i> Nueva compra a meses
    </a>
</div>

<form method="GET" action="{{ route('compras-meses.index') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1 fw-semibold">Tarjeta</label>
                <select name="cuenta_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($cuentas as $c)
                        <option value="{{ $c->id }}" {{ request('cuenta_id') == $c->id ? 'selected' : '' }}>{{ $c->negocio->nombre }} — {{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-danger btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</form>

@foreach(['Activas' => $activas, 'Terminadas' => $terminadas] as $titulo => $lista)
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold">{{ $titulo }} ({{ $lista->count() }})</span>
        @if($titulo === 'Activas')
        <span class="small text-muted">
            Este mes: <strong class="text-danger">${{ number_format($lista->sum(fn($c) => $c->cargosEntre(now()->startOfMonth(), now()->endOfMonth())->sum('monto')), 2) }}</strong>
            · Saldo pendiente: <strong class="text-danger">${{ number_format($lista->sum('saldo_pendiente'), 2) }}</strong>
        </span>
        @endif
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Compra</th>
                    <th>Tarjeta</th>
                    <th>Descripción</th>
                    <th class="text-end">Monto original</th>
                    <th class="text-end">Mensualidad</th>
                    <th class="text-center">Pagos</th>
                    <th class="text-end">Saldo pendiente</th>
                    <th>Último pago</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lista as $compra)
                <tr>
                    <td>{{ $compra->fecha_compra->format('d/m/Y') }}</td>
                    <td class="small">{{ $compra->cuenta->nombre }}<div class="text-muted">{{ $compra->cuenta->negocio->nombre }}</div></td>
                    <td>{{ $compra->descripcion }}</td>
                    <td class="text-end">${{ number_format($compra->monto_original, 2) }}</td>
                    <td class="text-end">${{ number_format($compra->mensualidad, 2) }}</td>
                    <td class="text-center">
                        <span class="badge {{ $compra->terminada ? 'bg-success' : 'bg-secondary' }}">{{ $compra->pagos_realizados }}/{{ $compra->meses }}</span>
                    </td>
                    <td class="text-end fw-bold {{ $compra->saldo_pendiente > 0 ? 'text-danger' : 'text-success' }}">${{ number_format($compra->saldo_pendiente, 2) }}</td>
                    <td>{{ $compra->fecha_ultimo_pago->format('d/m/Y') }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('compras-meses.show', $compra) }}" class="btn btn-sm btn-primary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('compras-meses.edit', $compra) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                        <form action="{{ route('compras-meses.destroy', $compra) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar esta compra a meses? También se eliminará su gasto.')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Sin compras {{ mb_strtolower($titulo) }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endforeach
@endsection
