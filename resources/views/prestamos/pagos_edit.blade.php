@extends('layouts.app')

@section('titulo', 'Editar Pago')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar pago — {{ $prestamo->concepto }}</h4>
    <a href="{{ route('prestamos.show', $prestamo) }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('prestamos.pagos.update', [$prestamo, $pago]) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="{{ old('fecha', $pago->fecha) }}">
                    @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto" id="pago-monto" class="form-control" value="{{ old('monto', $pago->monto) }}" oninput="actualizarDesglosePago()">
                    </div>
                    @error('monto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de pago</label>
                    <select name="tipo" class="form-select">
                        <option value="capital" {{ old('tipo', $pago->tipo) == 'capital' ? 'selected' : '' }}>Capital</option>
                        <option value="interes" {{ old('tipo', $pago->tipo) == 'interes' ? 'selected' : '' }}>Interés</option>
                    </select>
                    @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cuenta (de dónde sale)</label>
                    <select name="cuenta_id" class="form-select">
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_id', $pago->cuenta_id) == $cuenta->id ? 'selected' : '' }}>
                            {{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('cuenta_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <div class="d-flex align-items-center flex-wrap gap-3 border rounded p-2">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="tiene_iva" id="pago-tiene-iva"
                                   value="1" onchange="actualizarDesglosePago()" {{ old('tiene_iva', $pago->tiene_iva) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="pago-tiene-iva">Incluye IVA</label>
                        </div>
                        <div id="pago-iva-campos" class="d-flex flex-wrap gap-2 align-items-center" style="display:none">
                            <label class="mb-0 small text-muted">IVA (según tu estado de cuenta):</label>
                            <div class="input-group input-group-sm" style="width:140px">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="monto_iva" id="pago-monto-iva" class="form-control" value="{{ old('monto_iva', $pago->monto_iva) }}" oninput="actualizarDesglosePago(true)">
                            </div>
                            <span class="text-muted small">Subtotal: <strong id="pago-txt-base">$0.00</strong></span>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Notas <span class="text-muted small">(opcional)</span></label>
                    <input type="text" name="notas" class="form-control" value="{{ old('notas', $pago->notas) }}">
                </div>
            </div>
            <button type="submit" class="btn btn-danger mt-3">Guardar cambios</button>
        </form>
    </div>
</div>

<script>
function actualizarDesglosePago(editadoManualmente) {
    const monto    = parseFloat(document.getElementById('pago-monto').value) || 0;
    const conIva   = document.getElementById('pago-tiene-iva').checked;
    const campos   = document.getElementById('pago-iva-campos');
    const ivaInput = document.getElementById('pago-monto-iva');

    if (conIva) {
        campos.style.display = '';
        if (!editadoManualmente && (!ivaInput.value || parseFloat(ivaInput.value) === 0)) {
            ivaInput.value = (Math.round(monto * 16 / 116 * 100) / 100).toFixed(2);
        }
        const iva  = parseFloat(ivaInput.value) || 0;
        const base = Math.round((monto - iva) * 100) / 100;
        document.getElementById('pago-txt-base').textContent = '$' + base.toFixed(2);
    } else {
        campos.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () { actualizarDesglosePago(true); });
</script>
@endsection
