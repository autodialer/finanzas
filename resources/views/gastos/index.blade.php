@extends('layouts.app')

@section('titulo', 'Gastos')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Gastos</h4>
    <a href="{{ route('gastos.create') }}" class="btn btn-danger btn-sm">
        <i class="bi bi-plus-lg"></i> Nuevo Gasto
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Negocio</th>
                    <th>Área</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>Concepto</th>
                    <th>Forma Pago</th>
                    <th>Cuenta</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($gastos as $gasto)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($gasto->fecha)->format('d/m/Y') }}</td>
                    <td>{{ $gasto->negocio->nombre }}</td>
                    <td>{{ $gasto->area->nombre ?? '-' }}</td>
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