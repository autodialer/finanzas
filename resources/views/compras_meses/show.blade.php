@extends('layouts.app')

@section('titulo', 'Compra a meses')

@section('contenido')
@php $pagados = $compra->pagos_realizados; @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>{{ $compra->descripcion }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('compras-meses.edit', $compra) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('compras-meses.index') }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="row mb-3">
    @foreach([
        ['Monto original', '$' . number_format($compra->monto_original, 2), ''],
        ['Mensualidad', '$' . number_format($compra->mensualidad, 2) . ' × ' . $compra->meses, ''],
        ['Pagos', $pagados . ' realizados · ' . $compra->pagos_restantes . ' restantes', ''],
        ['Saldo pendiente', '$' . number_format($compra->saldo_pendiente, 2), $compra->saldo_pendiente > 0 ? 'text-danger' : 'text-success'],
    ] as [$etiqueta, $valor, $clase])
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">{{ $etiqueta }}</div>
                <div class="fs-5 fw-bold {{ $clase }}">{{ $valor }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Datos</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Tarjeta</span><span>{{ $compra->cuenta->nombre }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Negocio</span><span>{{ $compra->cuenta->negocio->nombre }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Fecha de compra</span><span>{{ $compra->fecha_compra->format('d/m/Y') }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Primer cargo</span><span>{{ $compra->fecha_primer_cargo->format('d/m/Y') }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Último pago</span><span>{{ $compra->fecha_ultimo_pago->format('d/m/Y') }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Categoría</span><span>{{ $compra->gasto->categoria->nombre ?? '-' }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Registrado por</span><span>{{ $compra->gasto->user->name ?? '-' }}</span></li>
                @if($compra->notas)
                <li class="list-group-item"><span class="text-muted">Notas:</span> {{ $compra->notas }}</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="col-md-8 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Calendario de mensualidades</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Fecha de cargo</th>
                            <th class="text-end">Mensualidad</th>
                            <th class="text-end">Saldo después del pago</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $saldo = (float) $compra->monto_original; @endphp
                        @foreach($compra->calendario as $cargo)
                        @php $saldo = round($saldo - $cargo['monto'], 2); @endphp
                        <tr class="{{ $cargo['numero'] === $pagados + 1 ? 'table-warning' : '' }}">
                            <td>{{ $cargo['numero'] }}/{{ $compra->meses }}</td>
                            <td>{{ $cargo['fecha']->format('d/m/Y') }}</td>
                            <td class="text-end">${{ number_format($cargo['monto'], 2) }}</td>
                            <td class="text-end">${{ number_format($saldo, 2) }}</td>
                            <td>
                                @if($cargo['numero'] <= $pagados)
                                <span class="badge bg-success">Pagado</span>
                                @elseif($cargo['numero'] === $pagados + 1)
                                <span class="badge bg-warning text-dark">Próximo</span>
                                @else
                                <span class="badge bg-light text-dark border">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('compras-meses.destroy', $compra) }}" method="POST">
    @csrf @method('DELETE')
    <button onclick="return confirm('¿Eliminar esta compra a meses? También se eliminará su gasto.')" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash"></i> Eliminar compra y su gasto
    </button>
</form>
@endsection
