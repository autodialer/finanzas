@extends('layouts.app')

@section('titulo', 'Dashboard')

@section('contenido')
<h4 class="mb-4">Resumen de {{ \Carbon\Carbon::now()->locale('es')->isoFormat('MMMM YYYY') }}</h4>

<!-- Tarjetas de resumen -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Ingresos del mes</div>
                        <div class="fs-4 fw-bold text-success">${{ number_format($totalIngresosMes, 2) }}</div>
                    </div>
                    <i class="bi bi-arrow-down-circle fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Gastos del mes</div>
                        <div class="fs-4 fw-bold text-danger">${{ number_format($totalGastosMes, 2) }}</div>
                    </div>
                    <i class="bi bi-arrow-up-circle fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Balance del mes</div>
                        <div class="fs-4 fw-bold {{ $balanceMes >= 0 ? 'text-success' : 'text-danger' }}">
                            ${{ number_format($balanceMes, 2) }}
                        </div>
                    </div>
                    <i class="bi bi-wallet2 fs-1 {{ $balanceMes >= 0 ? 'text-success' : 'text-danger' }} opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resumen por negocio -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Ingresos por negocio</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Negocio</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ingresosPorNegocio as $negocio)
                        <tr>
                            <td>{{ $negocio->nombre }}</td>
                            <td class="text-end text-success fw-bold">
                                ${{ number_format($negocio->ingresos_sum_monto ?? 0, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Gastos por negocio</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Negocio</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gastosPorNegocio as $negocio)
                        <tr>
                            <td>{{ $negocio->nombre }}</td>
                            <td class="text-end text-danger fw-bold">
                                ${{ number_format($negocio->gastos_sum_monto ?? 0, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Últimos movimientos -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">Últimos movimientos</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Negocio</th>
                    <th>Concepto</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ultimosMovimientos as $movimiento)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($movimiento['fecha'])->format('d/m/Y') }}</td>
                    <td>
                        @if($movimiento['tipo'] == 'ingreso')
                        <span class="badge bg-success">Ingreso</span>
                        @else
                        <span class="badge bg-danger">Gasto</span>
                        @endif
                    </td>
                    <td>{{ $movimiento['negocio'] }}</td>
                    <td>{{ $movimiento['concepto'] }}</td>
                    <td class="text-end fw-bold {{ $movimiento['tipo'] == 'ingreso' ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($movimiento['monto'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection