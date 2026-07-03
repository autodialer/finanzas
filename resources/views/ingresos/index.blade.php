@extends('layouts.app')

@section('titulo', 'Ingresos')

@section('styles')
<style>
    .header-fijo {
        position: sticky;
        top: 0;
        background-color: #f8fafc;
        z-index: 100;
        padding-bottom: 0.75rem;
    }
    .tabla-scroll thead th {
        position: sticky;
        z-index: 50;
    }
</style>
@endsection

@section('contenido')
<div class="header-fijo d-flex justify-content-between align-items-center mb-3">
    <h4>Ingresos</h4>
    <a href="{{ route('ingresos.create') }}" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Ingreso
    </a>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('ingresos.index') }}" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Negocio</label>
                <select name="negocio_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($negocios as $n)
                        <option value="{{ $n->id }}" {{ ($filtros['negocio_id'] ?? '') == $n->id ? 'selected' : '' }}>{{ $n->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Cliente</label>
                <select name="cliente_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ ($filtros['cliente_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Categoría</label>
                <select name="categoria_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ ($filtros['categoria_id'] ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1 fw-semibold">Forma pago</label>
                <select name="forma_pago" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="efectivo" {{ ($filtros['forma_pago'] ?? '') == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="transferencia" {{ ($filtros['forma_pago'] ?? '') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="tarjeta" {{ ($filtros['forma_pago'] ?? '') == 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
        @if(!empty($filtros))
        <div class="mt-2">
            <a href="{{ route('ingresos.index', ['limpiar' => 1]) }}" class="text-muted small"><i class="bi bi-x-circle"></i> Limpiar filtros</a>
        </div>
        @endif
    </div>
</form>

<div class="card">
    <div class="card-body p-0 tabla-scroll">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Negocio</th>
                    <th>Categoría</th>
                    <th>Cliente</th>
                    <th>Concepto</th>
                    <th>Forma Pago</th>
                    <th>Cuenta</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Total</th>
                    <th>Registró</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ingresos as $ingreso)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $ingreso->negocio->nombre }}</td>
                    <td>{{ $ingreso->categoria->nombre }}</td>
                    <td>{{ $ingreso->cliente->nombre ?? '-' }}</td>
                    <td>{{ $ingreso->concepto }}</td>
                    <td>
                        @if($ingreso->forma_pago == 'efectivo')
                        <span class="badge bg-success">Efectivo</span>
                        @elseif($ingreso->forma_pago == 'transferencia')
                        <span class="badge bg-primary">Transferencia</span>
                        @else
                        <span class="badge bg-warning text-dark">Tarjeta</span>
                        @endif
                    </td>
                    <td>{{ $ingreso->cuenta->nombre }}</td>
                    <td class="text-end text-muted">
                        @if($ingreso->tiene_iva) ${{ number_format($ingreso->monto_base, 2) }} @else - @endif
                    </td>
                    <td class="text-end text-success small">
                        @if($ingreso->tiene_iva) ${{ number_format($ingreso->monto_iva, 2) }} @else - @endif
                    </td>
                    <td class="text-end text-success fw-bold">${{ number_format($ingreso->monto, 2) }}</td>
                    <td class="text-muted small">{{ $ingreso->user->name ?? '-' }}</td>
                    <td>
                        <a href="{{ route('ingresos.edit', $ingreso) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('ingresos.destroy', $ingreso) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar?')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="text-center text-muted">Sin registros</td>
                </tr>
                @endforelse
            </tbody>
            @if($ingresos->count() > 0)
            <tfoot class="table-light">
                <tr>
                    <td colspan="8" class="text-end fw-bold">Totales:</td>
                    <td class="text-end text-muted fw-bold">${{ number_format($ingresos->sum('monto') - $ingresos->sum('monto_iva'), 2) }}</td>
                    <td class="text-end text-success fw-bold">${{ number_format($ingresos->sum('monto_iva'), 2) }}</td>
                    <td class="text-end fw-bold text-success">${{ number_format($ingresos->sum('monto'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var header = document.querySelector('.header-fijo');
        var ths = document.querySelectorAll('.tabla-scroll thead th');
        var top = header.offsetHeight + 'px';
        ths.forEach(function(th) { th.style.top = top; });
    });
</script>
@endsection
