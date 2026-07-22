@extends('layouts.app')

@section('titulo', 'Reporte Mensual')

@section('contenido')
@php
    $mesAnterior = $inicioMes->copy()->subMonth();
    $mesSiguiente = $inicioMes->copy()->addMonth();
    $nombreMes = ucfirst($inicioMes->copy()->locale('es')->translatedFormat('F Y'));
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Reporte Mensual</h4>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('reportes.mensual', ['mes' => $mesAnterior->month, 'anio' => $mesAnterior->year]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="fw-bold">{{ $nombreMes }}</span>
        <a href="{{ route('reportes.mensual', ['mes' => $mesSiguiente->month, 'anio' => $mesSiguiente->year]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>

<!-- Resumen general -->
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
                <div class="text-muted small">Balance General</div>
                <div class="fs-4 fw-bold {{ $balanceGeneral >= 0 ? 'text-success' : 'text-danger' }}">
                    ${{ number_format($balanceGeneral, 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Desglose por negocio -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">
        <i class="bi bi-building"></i> Desglose por negocio
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Negocio</th>
                    <th class="text-end">Ingresos</th>
                    <th class="text-end">Gastos</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reporte as $fila)
                <tr>
                    <td>{{ $fila['negocio']->nombre }}</td>
                    <td class="text-end text-success">${{ number_format($fila['ingresos'], 2) }}</td>
                    <td class="text-end text-danger">${{ number_format($fila['gastos'], 2) }}</td>
                    <td class="text-end fw-bold {{ $fila['balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($fila['balance'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-3">No hay negocios visibles.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td class="fw-bold">Total</td>
                    <td class="text-end fw-bold text-success">${{ number_format($totalIngresos, 2) }}</td>
                    <td class="text-end fw-bold text-danger">${{ number_format($totalGastos, 2) }}</td>
                    <td class="text-end fw-bold {{ $balanceGeneral >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($balanceGeneral, 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection
