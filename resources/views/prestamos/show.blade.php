@extends('layouts.app')

@section('titulo', 'Detalle de Préstamo')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>{{ $prestamo->concepto }}</h4>
    <a href="{{ route('prestamos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row mb-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Negocio</dt>
                    <dd class="col-sm-8">{{ $prestamo->negocio->nombre }}</dd>

                    <dt class="col-sm-4">Banco</dt>
                    <dd class="col-sm-8">{{ $prestamo->banco->nombre }}</dd>

                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8">
                        @if($prestamo->tipo == 'auto') Automóvil
                        @elseif($prestamo->tipo == 'equipo') Equipo
                        @else Otro
                        @endif
                    </dd>

                    <dt class="col-sm-4">Fecha de inicio</dt>
                    <dd class="col-sm-8">{{ \Carbon\Carbon::parse($prestamo->fecha_inicio)->format('d/m/Y') }}</dd>

                    @if($prestamo->tasa_interes)
                    <dt class="col-sm-4">Tasa de interés</dt>
                    <dd class="col-sm-8">{{ number_format($prestamo->tasa_interes, 2) }}%</dd>
                    @endif

                    @if($prestamo->plazo_meses)
                    <dt class="col-sm-4">Plazo</dt>
                    <dd class="col-sm-8">{{ $prestamo->plazo_meses }} meses</dd>
                    @endif

                    @if($prestamo->notas)
                    <dt class="col-sm-4">Notas</dt>
                    <dd class="col-sm-8">{{ $prestamo->notas }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="text-muted small">Monto original</div>
                <div class="fs-5 mb-2">${{ number_format($prestamo->monto_original, 2) }}</div>
                <div class="text-muted small">Pagado a capital</div>
                <div class="fs-6 text-success">${{ number_format($prestamo->pagado_capital, 2) }}</div>
                <div class="text-muted small">Pagado en intereses</div>
                <div class="fs-6 text-warning mb-2">${{ number_format($prestamo->pagado_interes, 2) }}</div>
                <div class="text-muted small">Saldo pendiente (capital)</div>
                <div class="fs-4 fw-bold {{ $prestamo->saldo_pendiente > 0 ? 'text-danger' : 'text-success' }}">
                    ${{ number_format($prestamo->saldo_pendiente, 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Formulario nuevo pago --}}
<div class="card mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="bi bi-plus-circle me-1"></i>Registrar pago
    </div>
    <div class="card-body">
        <form action="{{ route('prestamos.pagos.store', $prestamo) }}" method="POST">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-1">Fecha</label>
                    <input type="date" name="fecha" class="form-control form-control-sm" value="{{ old('fecha', date('Y-m-d')) }}">
                    @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Monto</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto" class="form-control" value="{{ old('monto') }}">
                    </div>
                    @error('monto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Tipo de pago</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="capital" {{ old('tipo', 'capital') == 'capital' ? 'selected' : '' }}>Capital</option>
                        <option value="interes" {{ old('tipo') == 'interes' ? 'selected' : '' }}>Interés</option>
                    </select>
                    @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Cuenta (de dónde sale)</label>
                    <select name="cuenta_id" class="form-select form-select-sm">
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_id') == $cuenta->id ? 'selected' : '' }}>
                            {{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('cuenta_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Notas <span class="text-muted">(opcional)</span></label>
                    <input type="text" name="notas" class="form-control form-control-sm" value="{{ old('notas') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-check-lg"></i> Registrar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Historial de pagos --}}
<div class="card">
    <div class="card-header bg-light fw-semibold">
        <i class="bi bi-clock-history me-1"></i>Historial de pagos
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Cuenta</th>
                    <th>Notas</th>
                    <th class="text-end">Monto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prestamo->pagos as $pago)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($pago->fecha)->format('d/m/Y') }}</td>
                    <td>
                        @if($pago->tipo == 'capital')
                        <span class="badge bg-success">Capital</span>
                        @else
                        <span class="badge bg-warning text-dark">Interés</span>
                        @endif
                    </td>
                    <td>{{ $pago->cuenta->nombre }}</td>
                    <td>{{ $pago->notas ?? '-' }}</td>
                    <td class="text-end fw-bold text-danger">${{ number_format($pago->monto, 2) }}</td>
                    <td>
                        <form action="{{ route('prestamos.pagos.destroy', [$prestamo, $pago]) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('¿Eliminar este pago? También se eliminará el gasto asociado.')" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Sin pagos registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
