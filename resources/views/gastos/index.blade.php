@extends('layouts.app')

@section('titulo', 'Gastos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Gastos</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('gastos.import.form') }}" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-excel"></i> Importar Amex
        </a>
        <a href="{{ route('gastos.create') }}" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Gasto
        </a>
    </div>
</div>

{{-- Filtros --}}
<form method="GET" action="{{ route('gastos.index') }}" class="card mb-3">
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
                <label class="form-label small mb-1 fw-semibold">Proveedor</label>
                <select name="proveedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($proveedores as $p)
                        <option value="{{ $p->id }}" {{ ($filtros['proveedor_id'] ?? '') == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1 fw-semibold">Categoría</label>
                <select name="categoria_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categorias as $c)
                        <option value="{{ $c->id }}" {{ ($filtros['categoria_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
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
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-danger btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
        @if(!empty($filtros))
        <div class="mt-2">
            <a href="{{ route('gastos.index', ['limpiar' => 1]) }}" class="text-muted small"><i class="bi bi-x-circle"></i> Limpiar filtros</a>
        </div>
        @endif
    </div>
</form>

<div class="card">
    <div class="card-body p-0" style="overflow-y: auto; max-height: calc(100vh - 260px);">
        <table class="table table-hover mb-0">
            <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th>Fecha</th>
                    <th>Negocio</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
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
                @forelse($gastos as $gasto)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $gasto->negocio->nombre }}</td>
                    <td>{{ $gasto->categoria->nombre }}</td>
                    <td>{{ $gasto->proveedor->nombre ?? '-' }}</td>
                    <td>{{ $gasto->concepto }}</td>
                    <td>
                        @if($gasto->forma_pago == 'efectivo')
                        <span class="badge bg-success">Efectivo</span>
                        @elseif($gasto->forma_pago == 'transferencia')
                        <span class="badge bg-primary">Transferencia</span>
                        @else
                        <span class="badge bg-warning text-dark">Tarjeta</span>
                        @endif
                    </td>
                    <td>{{ $gasto->cuenta->nombre }}</td>
                    <td class="text-end text-muted">
                        @if($gasto->tiene_iva) ${{ number_format($gasto->monto_base, 2) }} @else - @endif
                    </td>
                    <td class="text-end text-warning small">
                        @if($gasto->tiene_iva) ${{ number_format($gasto->monto_iva, 2) }} @else - @endif
                    </td>
                    <td class="text-end text-danger fw-bold">${{ number_format($gasto->monto, 2) }}</td>
                    <td class="text-muted small">{{ $gasto->user->name ?? '-' }}</td>
                    <td>
                        <a href="{{ route('gastos.edit', $gasto) }}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('gastos.destroy', $gasto) }}" method="POST" class="d-inline">
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
            @if($gastos->count() > 0)
            <tfoot class="table-light">
                <tr>
                    <td colspan="8" class="text-end fw-bold">Totales:</td>
                    <td class="text-end text-muted fw-bold">${{ number_format($gastos->sum('monto') - $gastos->sum('monto_iva'), 2) }}</td>
                    <td class="text-end text-warning fw-bold">${{ number_format($gastos->sum('monto_iva'), 2) }}</td>
                    <td class="text-end fw-bold text-danger">${{ number_format($gastos->sum('monto'), 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection