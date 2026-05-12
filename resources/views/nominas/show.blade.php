@extends('layouts.app')

@section('titulo', 'Nómina — ' . $nomina->nombre)

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ $nomina->nombre }}</h4>
        <small class="text-muted">
            {{ $nomina->negocio->nombre }} &bull;
            {{ $nomina->fecha_inicio->format('d/m/Y') }} al {{ $nomina->fecha_fin->format('d/m/Y') }} &bull;
            Cuenta: {{ $nomina->cuenta->nombre }}
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

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Empleado</th>
                    <th>Cargo</th>
                    <th class="text-end">Monto</th>
                    <th>Notas</th>
                    @if($nomina->estado === 'borrador')
                    <th>Ajustar</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($nomina->nominas as $linea)
                <tr>
                    <td>{{ $linea->empleado->nombre }}</td>
                    <td class="text-muted">{{ $linea->empleado->cargo ?? '-' }}</td>
                    <td class="text-end fw-bold">${{ number_format($linea->monto, 2) }}</td>
                    <td>{{ $linea->notas ?? '-' }}</td>
                    @if($nomina->estado === 'borrador')
                    <td>
                        <button class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#modal-{{ $linea->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Modal ajuste -->
                        <div class="modal fade" id="modal-{{ $linea->id }}" tabindex="-1">
                            <div class="modal-dialog modal-sm">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title">{{ $linea->empleado->nombre }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="{{ route('nominas.linea.update', $linea) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <div class="modal-body">
                                            <label class="form-label">Monto</label>
                                            <div class="input-group mb-2">
                                                <span class="input-group-text">$</span>
                                                <input type="number" name="monto" class="form-control"
                                                       value="{{ $linea->monto }}" min="0" step="0.01" required>
                                            </div>
                                            <label class="form-label">Notas</label>
                                            <textarea name="notas" class="form-control" rows="2">{{ $linea->notas }}</textarea>
                                        </div>
                                        <div class="modal-footer">
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
                    <td colspan="5" class="text-center text-muted">Sin empleados en este período</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="{{ $nomina->estado === 'borrador' ? 4 : 3 }}" class="text-end fw-bold">Total nómina:</td>
                    <td class="text-end fw-bold text-danger fs-5">
                        ${{ number_format($nomina->nominas->sum('monto'), 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
