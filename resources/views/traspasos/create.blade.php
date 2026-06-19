@extends('layouts.app')

@section('titulo', 'Nuevo Traspaso')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Traspaso</h4>
    <a href="{{ route('traspasos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('traspasos.store') }}" method="POST">
            @csrf
            <div class="row">

                <div class="col-md-6 mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control"
                           value="{{ old('fecha', date('Y-m-d')) }}">
                    @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto" class="form-control"
                               value="{{ old('monto') }}" placeholder="0.00">
                    </div>
                    @error('monto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuenta Origen <span class="text-muted small">(de dónde sale)</span></label>
                    <select name="cuenta_origen_id" class="form-select ts-select" data-placeholder="Selecciona cuenta origen">
                        <option value=""></option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_origen_id') == $cuenta->id ? 'selected' : '' }}>
                            {{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('cuenta_origen_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuenta Destino <span class="text-muted small">(a dónde llega)</span></label>
                    <select name="cuenta_destino_id" class="form-select ts-select" data-placeholder="Selecciona cuenta destino">
                        <option value=""></option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_destino_id') == $cuenta->id ? 'selected' : '' }}>
                            {{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('cuenta_destino_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Concepto <span class="text-muted small">(opcional)</span></label>
                    <input type="text" name="concepto" class="form-control"
                           value="{{ old('concepto') }}" placeholder="Ej: Reposición de caja">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Notas <span class="text-muted small">(opcional)</span></label>
                    <textarea name="notas" class="form-control" rows="1">{{ old('notas') }}</textarea>
                </div>

            </div>

            {{-- Resumen visual --}}
            <div id="resumen-traspaso" class="alert alert-primary d-none mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Vas a mover <strong id="res-monto">$0.00</strong>
                de <strong id="res-origen">—</strong>
                hacia <strong id="res-destino">—</strong>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-left-right me-1"></i> Registrar Traspaso
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const montoEl   = document.querySelector('[name="monto"]');
    const origenEl  = document.querySelector('[name="cuenta_origen_id"]');
    const destinoEl = document.querySelector('[name="cuenta_destino_id"]');
    const resumen   = document.getElementById('resumen-traspaso');
    const resMonto  = document.getElementById('res-monto');
    const resOrigen = document.getElementById('res-origen');
    const resDest   = document.getElementById('res-destino');

    function actualizarResumen() {
        const monto   = parseFloat(montoEl.value) || 0;
        const origenTxt  = origenEl.options[origenEl.selectedIndex]?.text  || '—';
        const destinoTxt = destinoEl.options[destinoEl.selectedIndex]?.text || '—';

        if (monto > 0 && origenEl.value && destinoEl.value) {
            resMonto.textContent  = '$' + monto.toFixed(2);
            resOrigen.textContent = origenTxt;
            resDest.textContent   = destinoTxt;
            resumen.classList.remove('d-none');
        } else {
            resumen.classList.add('d-none');
        }
    }

    montoEl.addEventListener('input', actualizarResumen);

    // Tom Select dispara 'change' en el select subyacente
    origenEl.addEventListener('change', actualizarResumen);
    destinoEl.addEventListener('change', actualizarResumen);
});
</script>
@endsection
