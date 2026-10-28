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

<!-- Tarjetas IVA -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">IVA cobrado (ingresos)</div>
                        <div class="fs-4 fw-bold text-success">${{ number_format($ivaCobradomMes, 2) }}</div>
                        <div class="text-muted" style="font-size:0.75rem">IVA trasladado a clientes</div>
                    </div>
                    <i class="bi bi-receipt fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">IVA pagado (gastos)</div>
                        <div class="fs-4 fw-bold text-danger">${{ number_format($ivaPagadoMes, 2) }}</div>
                        <div class="text-muted" style="font-size:0.75rem">IVA acreditable de proveedores</div>
                    </div>
                    <i class="bi bi-receipt-cutoff fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm {{ $saldoIva > 0 ? 'border-warning' : '' }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Saldo IVA con SAT</div>
                        <div class="fs-4 fw-bold {{ $saldoIva > 0 ? 'text-warning' : 'text-success' }}">
                            ${{ number_format(abs($saldoIva), 2) }}
                        </div>
                        <div class="small {{ $saldoIva > 0 ? 'text-warning' : 'text-success' }}">
                            @if($saldoIva > 0)
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>A pagar al SAT
                            @elseif($saldoIva < 0)
                                <i class="bi bi-check-circle-fill me-1"></i>A favor tuyo
                            @else
                                <i class="bi bi-dash-circle me-1"></i>Sin saldo
                            @endif
                        </div>
                    </div>
                    <i class="bi bi-bank2 fs-1 {{ $saldoIva > 0 ? 'text-warning' : 'text-success' }} opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compromisos a meses -->
@if($comprasMesesActivas > 0)
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-calendar3 me-1"></i> Compromisos a meses</span>
                <a href="{{ route('compras-meses.index') }}" class="small">{{ $comprasMesesActivas }} {{ $comprasMesesActivas === 1 ? 'compra activa' : 'compras activas' }}</a>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <div class="text-muted small">Mensualidades de este mes</div>
                        <div class="fs-4 fw-bold text-danger">${{ number_format($compromisoMesActual, 2) }}</div>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <div class="text-muted small">Saldo pendiente total</div>
                        <div class="fs-4 fw-bold text-danger">${{ number_format($saldoPendienteMeses, 2) }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Próximos 6 meses</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($compromisosMeses as $mes => $total)
                            <div class="border rounded px-2 py-1 text-center flex-fill">
                                <div class="small text-muted">{{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $mes . '-01')->locale('es')->isoFormat('MMM YY')) }}</div>
                                <div class="fw-semibold small">${{ number_format($total, 2) }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Saldo disponible en cuentas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Saldo disponible en cuentas</span>
                <span class="fs-5 fw-bold {{ $saldoTotalCuentas >= 0 ? 'text-success' : 'text-danger' }}">
                    Total: ${{ number_format($saldoTotalCuentas, 2) }}
                </span>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cuenta</th>
                            <th>Negocio</th>
                            <th class="text-end">Total entradas</th>
                            <th class="text-end">Total salidas</th>
                            <th class="text-end">Saldo disponible</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cuentasConSaldo as $cuenta)
                        <tr>
                            <td>{{ $cuenta->nombre }}</td>
                            <td class="text-muted small">{{ $cuenta->negocio->nombre ?? '-' }}</td>
                            <td class="text-end text-success">${{ number_format($cuenta->total_entradas, 2) }}</td>
                            <td class="text-end text-danger">${{ number_format($cuenta->total_salidas, 2) }}</td>
                            <td class="text-end fw-bold {{ $cuenta->saldo >= 0 ? 'text-success' : 'text-danger' }}">
                                ${{ number_format($cuenta->saldo, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
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
                    <th>Registró</th>
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
                    <td class="text-muted small">{{ $movimiento['registrado_por'] }}</td>
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