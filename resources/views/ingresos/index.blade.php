@extends('layouts.app')

@section('titulo', 'Ingresos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Ingresos</h4>
    <a href="{{ route('ingresos.create') }}" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Ingreso
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
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