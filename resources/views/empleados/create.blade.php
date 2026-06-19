@extends('layouts.app')

@section('titulo', 'Nuevo Empleado')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Empleado</h4>
    <a href="{{ route('empleados.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form action="{{ route('empleados.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Negocio <span class="text-danger">*</span></label>
                    <select id="negocio_id" name="negocio_id" class="form-select" required>
                        <option value="">Selecciona un negocio</option>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ old('negocio_id') == $negocio->id ? 'selected' : '' }}>
                            {{ $negocio->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div id="fila-empresa" class="col-md-6" style="display:none">
                    <label class="form-label">Empresa</label>
                    <select id="empresa_id" name="empresa_id" class="form-select">
                        <option value="">Todos los empleados del negocio</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Período de pago</label>
                    <select name="periodo_pago" class="form-select">
                        <option value="">Sin especificar</option>
                        <option value="semanal" {{ old('periodo_pago') == 'semanal' ? 'selected' : '' }}>Semanal</option>
                        <option value="quincenal" {{ old('periodo_pago') == 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" class="form-control" value="{{ old('cargo') }}" placeholder="Ej. Operador, Vendedor...">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Salario por período <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="salario" class="form-control" value="{{ old('salario') }}" min="0" step="0.01" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="1" {{ old('activo', '1') == '1' ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ old('activo') == '0' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Guardar Empleado
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const negociosConEmpresas = @json($negociosConEmpresas);
const empresasPorNegocio  = @json($empresas->groupBy('negocio_id')->map(fn($g) => $g->map(fn($e) => ['id' => $e->id, 'nombre' => $e->nombre])->values()));

const selNegocio  = document.getElementById('negocio_id');
const filaEmpresa = document.getElementById('fila-empresa');
const selEmpresa  = document.getElementById('empresa_id');

function actualizarEmpresas() {
    const negId = parseInt(selNegocio.value);
    const tieneEmpresas = negociosConEmpresas.includes(negId);
    filaEmpresa.style.display = tieneEmpresas ? '' : 'none';

    // repoblar select
    while (selEmpresa.options.length > 1) selEmpresa.remove(1);
    if (tieneEmpresas && empresasPorNegocio[negId]) {
        empresasPorNegocio[negId].forEach(e => {
            selEmpresa.add(new Option(e.nombre, e.id));
        });
    }
    if (!tieneEmpresas) selEmpresa.value = '';
}

selNegocio.addEventListener('change', actualizarEmpresas);

// Restore on back-navigation
if (selNegocio.value) actualizarEmpresas();
const oldEmpresa = "{{ old('empresa_id') }}";
if (oldEmpresa) selEmpresa.value = oldEmpresa;
</script>
@endsection
