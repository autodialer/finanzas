@extends('layouts.app')

@section('titulo', 'Reportes')

@section('contenido')
<h4 class="mb-4">Reportes</h4>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Filtros</div>
    <div class="card-body">
        <form method="GET" action="{{ route('reportes.index') }}">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Negocio</label>
                    <select name="negocio_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ ($filtros['negocio_id'] ?? '') == $negocio->id ? 'selected' : '' }}>
                            {{ $negocio->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ ($filtros['categoria_id'] ?? '') == $categoria->id ? 'selected' : '' }}>
                            {{ $categoria->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="{{ $filtros['fecha_inicio'] ?? '' }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Fecha fin</label>
                    <input type="date" name="fecha_fin" class="form-control" value="{{ $filtros['fecha_fin'] ?? '' }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="" {{ ($filtros['tipo'] ?? '') == '' ? 'selected' : '' }}>Todos</option>
                        <option value="ingreso" {{ ($filtros['tipo'] ?? '') == 'ingreso' ? 'selected' : '' }}>Solo ingresos</option>
                        <option value="gasto" {{ ($filtros['tipo'] ?? '') == 'gasto' ? 'selected' : '' }}>Solo gastos</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="{{ route('reportes.index') }}" class="btn btn-secondary w-100">
                        <i class="bi bi-x"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Total Ingresos</div>
                <div class="fs-4 fw-bold text-success">${{ number_format($totalIngresos, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Total Gastos</div>
                <div class="fs-4 fw-bold text-danger">${{ number_format($totalGastos, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Balance</div>
                <div class="fs-4 fw-bold {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                    ${{ number_format($balance, 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ingresos -->
@if($ingresos->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold text-success">
        <i class="bi bi-arrow-down-circle"></i> Ingresos ({{ $ingresos->count() }})
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Negocio</th>
                    <th>Categoría</th>
                    <th>Cliente</th>
                    <th>Concepto</th>
                    <th>Forma Pago</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ingresos as $ingreso)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $ingreso->negocio->nombre }}</td>
                    <td>{{ $ingreso->categoria->nombre }}</td>
                    <td>{{ $ingreso->cliente->nombre ?? '-' }}</td>
                    <td>{{ $ingreso->concepto }}</td>
                    <td>{{ ucfirst($ingreso->forma_pago) }}</td>
                    <td class="text-end text-success fw-bold">${{ number_format($ingreso->monto, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="7" class="text-end fw-bold">Total:</td>
                    <td class="text-end fw-bold text-success">${{ number_format($totalIngresos, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

<!-- Gastos -->
@if($gastos->count() > 0)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold text-danger">
        <i class="bi bi-arrow-up-circle"></i> Gastos ({{ $gastos->count() }})
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Negocio</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>Concepto</th>
                    <th>Forma Pago</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gastos as $gasto)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $gasto->negocio->nombre }}</td>
                    <td>{{ $gasto->categoria->nombre }}</td>
                    <td>{{ $gasto->proveedor->nombre ?? '-' }}</td>
                    <td>{{ $gasto->concepto }}</td>
                    <td>{{ ucfirst($gasto->forma_pago) }}</td>
                    <td class="text-end text-danger fw-bold">${{ number_format($gasto->monto, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="7" class="text-end fw-bold">Total:</td>
                    <td class="text-end fw-bold text-danger">${{ number_format($totalGastos, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

@if($ingresos->count() == 0 && $gastos->count() == 0)
<div class="alert alert-info">No hay movimientos con los filtros seleccionados.</div>
@endif

@endsection