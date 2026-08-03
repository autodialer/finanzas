@extends('layouts.app')

@section('titulo', 'Editar Gasto')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Gasto</h4>
    <a href="{{ route('gastos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('gastos.update', $gasto) }}" method="POST">
            @csrf @method('PUT')
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="{{ old('fecha', $gasto->fecha) }}">
                    @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Monto total</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto" id="monto" class="form-control"
                               value="{{ old('monto', $gasto->monto) }}" oninput="actualizarIva()">
                    </div>
                    @error('monto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Negocio</label>
                    <select name="negocio_id" class="form-select">
                        <option value="">Selecciona un negocio</option>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ old('negocio_id', $gasto->negocio_id) == $negocio->id ? 'selected' : '' }}>{{ $negocio->nombre }}</option>
                        @endforeach
                    </select>
                    @error('negocio_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">Selecciona una categoría</option>
                        @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ old('categoria_id', $gasto->categoria_id) == $categoria->id ? 'selected' : '' }}>{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                    @error('categoria_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Proveedor <span class="text-muted small">(opcional)</span></label>
                    <select name="proveedor_id" class="form-select ts-select" data-placeholder="Sin proveedor">
                        <option value="">Sin proveedor</option>
                        @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ old('proveedor_id', $gasto->proveedor_id) == $proveedor->id ? 'selected' : '' }}>{{ $proveedor->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuenta</label>
                    <select name="cuenta_id" class="form-select ts-select" data-placeholder="Selecciona una cuenta">
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_id', $gasto->cuenta_id) == $cuenta->id ? 'selected' : '' }}>{{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    @error('cuenta_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Forma de Pago</label>
                    <select name="forma_pago" id="forma_pago" class="form-select" onchange="manejarFormaPago()">
                        <option value="">Selecciona la forma de pago</option>
                        <option value="efectivo" {{ old('forma_pago', $gasto->forma_pago) == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transferencia" {{ old('forma_pago', $gasto->forma_pago) == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        <option value="tarjeta" {{ old('forma_pago', $gasto->forma_pago) == 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                    </select>
                    @error('forma_pago') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                {{-- Sección Propina --}}
                <div class="col-12 mb-3">
                    <div class="card border-secondary">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center flex-wrap gap-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="tiene_propina" id="tiene_propina"
                                           value="1" onchange="manejarTogglePropina()"
                                           {{ old('tiene_propina', $gasto->tiene_propina) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="tiene_propina">
                                        Incluye propina
                                    </label>
                                </div>
                                <div id="propina-campos" class="d-flex align-items-center gap-2" style="{{ old('tiene_propina', $gasto->tiene_propina) ? '' : 'display:none!important' }}">
                                    <label class="mb-0 small text-muted">Porcentaje:</label>
                                    <div class="input-group input-group-sm" style="width:100px">
                                        <input type="number" step="1" min="0" max="100" name="porcentaje_propina"
                                               id="porcentaje_propina" class="form-control"
                                               value="{{ old('porcentaje_propina', $gasto->porcentaje_propina ?? 20) }}"
                                               oninput="actualizarDesglose()">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <span class="text-muted small">Propina: <strong class="text-secondary" id="txt-propina">$0.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sección IVA --}}
                <div class="col-12 mb-3" id="seccion-iva" style="display:none">
                    <div class="card border-warning">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="tiene_iva" id="tiene_iva"
                                           value="1" onchange="actualizarDesglose()"
                                           {{ old('tiene_iva', $gasto->tiene_iva) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="tiene_iva">
                                        Incluye IVA (16%)
                                    </label>
                                </div>
                                <div id="iva-desglose" class="d-flex flex-wrap gap-3 align-items-center">
                                    <span class="text-muted small">Subtotal: <strong id="txt-base">$0.00</strong></span>
                                    <span class="text-muted small">IVA 16%: <strong class="text-warning" id="txt-iva">$0.00</strong></span>
                                    <span class="text-muted small">Total c/IVA: <strong class="text-danger" id="txt-total">$0.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">Concepto</label>
                    <input type="text" name="concepto" class="form-control" value="{{ old('concepto', $gasto->concepto) }}">
                    @error('concepto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notas <span class="text-muted small">(opcional)</span></label>
                    <textarea name="notas" class="form-control" rows="2">{{ old('notas', $gasto->notas) }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Gasto</button>
        </form>
    </div>
</div>

<script>
function manejarFormaPago() {
    const forma = document.getElementById('forma_pago').value;
    document.getElementById('seccion-iva').style.display = forma ? '' : 'none';
    actualizarDesglose();
}

function manejarTogglePropina() {
    const chk = document.getElementById('tiene_propina');
    const pct = document.getElementById('porcentaje_propina');
    if (chk.checked && (!pct.value || parseFloat(pct.value) === 0)) {
        pct.value = 20;
    }
    actualizarDesglose();
}

function actualizarDesglose() {
    const monto      = parseFloat(document.getElementById('monto').value) || 0;
    const conPropina = document.getElementById('tiene_propina').checked;
    const conIva     = document.getElementById('tiene_iva').checked;

    document.getElementById('propina-campos').style.display = conPropina ? '' : 'none';

    let montoPropina = 0;
    if (conPropina) {
        const pct    = parseFloat(document.getElementById('porcentaje_propina').value) || 0;
        montoPropina = Math.round(monto * pct / (100 + pct) * 100) / 100;
        document.getElementById('txt-propina').textContent = '$' + montoPropina.toFixed(2);
    }

    const baseParaIva = monto - montoPropina;

    if (conIva) {
        const iva  = Math.round(baseParaIva * 16 / 116 * 100) / 100;
        const base = Math.round((baseParaIva - iva) * 100) / 100;
        document.getElementById('txt-base').textContent  = '$' + base.toFixed(2);
        document.getElementById('txt-iva').textContent   = '$' + iva.toFixed(2);
        document.getElementById('txt-total').textContent = '$' + baseParaIva.toFixed(2);
        document.getElementById('iva-desglose').style.display = '';
    } else {
        document.getElementById('iva-desglose').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const forma = document.getElementById('forma_pago').value;
    if (forma) manejarFormaPago();
    else actualizarDesglose();
});
</script>
@endsection
