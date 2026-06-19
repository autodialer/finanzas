@extends('layouts.app')

@section('titulo', 'Nómina — ' . $nomina->nombre)

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ $nomina->nombre }}</h4>
        <small class="text-muted">
            {{ $nomina->negocio->nombre }}
            @if($nomina->empresa) &bull; {{ $nomina->empresa->nombre }} @endif
            @if($nomina->tipo_periodo) &bull; <span class="badge bg-secondary">{{ ucfirst($nomina->tipo_periodo) }}</span> @endif
            &bull; {{ $nomina->fecha_inicio->format('d/m/Y') }} al {{ $nomina->fecha_fin->format('d/m/Y') }}
            &bull; Cuenta: {{ $nomina->cuenta->nombre }}
        </small>
    </div>
    <div class="d-flex gap-2">
        @if($nomina->estado === 'borrador')
        <form action="{{ route('nominas.cerrar', $nomina) }}" method="POST"
              onsubmit="return confirm('¿Cerrar período? Se registrarán los gastos automáticamente.')">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-check-circle me-1"></i> Cerrar y Registrar Gastos
            </button>
        </form>
        @else
        <span class="badge bg-secondary fs-6 py-2 px-3">Período Cerrado</span>
        @endif
        <a href="{{ route('nominas.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

@if(session('exito'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('exito') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 table-sm">
            <thead class="table-dark">
                <tr>
                    <th>Empleado</th>
                    <th>Cargo</th>
                    <th>Cuenta</th>
                    <th class="text-end">Salario bruto</th>
                    <th class="text-end text-warning">ISR</th>
                    <th class="text-end text-info">IMSS obrero</th>
                    <th class="text-end text-success">Salario neto</th>
                    <th>Notas</th>
                    @if($nomina->estado === 'borrador')
                    <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($nomina->nominas as $linea)
                <tr>
                    <td class="fw-semibold">{{ $linea->empleado->nombre }}</td>
                    <td class="text-muted">{{ $linea->empleado->cargo ?? '-' }}</td>
                    <td>
                        @if($linea->cuenta)
                            <span class="badge bg-primary">{{ $linea->cuenta->nombre }}</span>
                        @else
                            <span class="text-muted small">Default</span>
                        @endif
                    </td>
                    <td class="text-end">${{ number_format($linea->monto, 2) }}</td>
                    <td class="text-end text-warning">- ${{ number_format($linea->isr, 2) }}</td>
                    <td class="text-end text-info">- ${{ number_format($linea->imss_empleado, 2) }}</td>
                    <td class="text-end fw-bold text-success">${{ number_format($linea->salario_neto, 2) }}</td>
                    <td class="text-muted small">{{ $linea->notas ?? '-' }}</td>
                    @if($nomina->estado === 'borrador')
                    <td>
                        <button class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#modal-{{ $linea->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Modal ajuste -->
                        <div class="modal fade" id="modal-{{ $linea->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title">{{ $linea->empleado->nombre }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="{{ route('nominas.linea.update', $linea) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Cuenta de pago (opcional)</label>
                                                <select name="cuenta_id" class="form-select">
                                                    <option value="">Usar cuenta del período ({{ $nomina->cuenta->nombre }})</option>
                                                    @foreach($cuentas->groupBy('negocio.nombre') as $negNombre => $grupo)
                                                    <optgroup label="{{ $negNombre }}">
                                                        @foreach($grupo as $cuenta)
                                                        <option value="{{ $cuenta->id }}" {{ $linea->cuenta_id == $cuenta->id ? 'selected' : '' }}>
                                                            {{ $cuenta->nombre }}
                                                        </option>
                                                        @endforeach
                                                    </optgroup>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">Solo si este empleado se paga de una cuenta diferente.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Salario bruto</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="monto" id="bruto-{{ $linea->id }}"
                                                           class="form-control" value="{{ $linea->monto }}"
                                                           min="0" step="0.01" required
                                                           oninput="calcularNeto({{ $linea->id }})">
                                                </div>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label text-warning fw-semibold">ISR retenido</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" name="isr" id="isr-{{ $linea->id }}"
                                                               class="form-control" value="{{ $linea->isr }}"
                                                               min="0" step="0.01" required
                                                               oninput="calcularNeto({{ $linea->id }})">
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label text-info fw-semibold">IMSS obrero</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" name="imss_empleado" id="imss-{{ $linea->id }}"
                                                               class="form-control" value="{{ $linea->imss_empleado }}"
                                                               min="0" step="0.01" required
                                                               oninput="calcularNeto({{ $linea->id }})">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="alert alert-success py-2 mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-semibold">Salario neto a pagar:</span>
                                                    <span class="fs-5 fw-bold" id="neto-display-{{ $linea->id }}">
                                                        ${{ number_format($linea->salario_neto, 2) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Notas</label>
                                                <textarea name="notas" class="form-control" rows="2">{{ $linea->notas }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer justify-content-between">
                                            <form action="{{ route('nominas.linea.recalcular', $linea) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-arrow-clockwise me-1"></i> Recalcular automático
                                                </button>
                                            </form>
                                            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted">Sin empleados en este período</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td colspan="3" class="text-end">Totales:</td>
                    <td class="text-end">${{ number_format($nomina->nominas->sum('monto'), 2) }}</td>
                    <td class="text-end text-warning">- ${{ number_format($nomina->nominas->sum('isr'), 2) }}</td>
                    <td class="text-end text-info">- ${{ number_format($nomina->nominas->sum('imss_empleado'), 2) }}</td>
                    <td class="text-end text-success fs-6">${{ number_format($nomina->nominas->sum('salario_neto'), 2) }}</td>
                    <td colspan="{{ $nomina->estado === 'borrador' ? 2 : 1 }}"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
function calcularNeto(id) {
    const bruto = parseFloat(document.getElementById('bruto-' + id).value) || 0;
    const isr   = parseFloat(document.getElementById('isr-'   + id).value) || 0;
    const imss  = parseFloat(document.getElementById('imss-'  + id).value) || 0;
    const neto  = bruto - isr - imss;
    document.getElementById('neto-display-' + id).textContent =
        '$' + neto.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endsection
