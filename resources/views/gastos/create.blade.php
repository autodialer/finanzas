@extends('layouts.app')

@section('titulo', 'Nuevo Gasto')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Gasto</h4>
    <a href="{{ route('gastos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('gastos.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}">
                    @error('fecha') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Monto total</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto" id="monto" class="form-control" value="{{ old('monto') }}" oninput="actualizarIva()">
                    </div>
                    @error('monto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Negocio</label>
                    <select name="negocio_id" class="form-select">
                        <option value="">Selecciona un negocio</option>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ old('negocio_id') == $negocio->id ? 'selected' : '' }}>{{ $negocio->nombre }}</option>
                        @endforeach
                    </select>
                    @error('negocio_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">Selecciona una categoría</option>
                        @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ old('categoria_id') == $categoria->id ? 'selected' : '' }}>{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                    @error('categoria_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Proveedor <span class="text-muted small">(opcional)</span></label>
                    <select name="proveedor_id" class="form-select ts-select" data-placeholder="Sin proveedor">
                        <option value="">Sin proveedor</option>
                        @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ old('proveedor_id') == $proveedor->id ? 'selected' : '' }}>{{ $proveedor->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuenta</label>
                    <select name="cuenta_id" class="form-select ts-select" data-placeholder="Selecciona una cuenta">
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_id') == $cuenta->id ? 'selected' : '' }}>{{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    @error('cuenta_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Forma de Pago</label>
                    <select name="forma_pago" id="forma_pago" class="form-select" onchange="manejarFormaPago()">
                        <option value="">Selecciona la forma de pago</option>
                        <option value="efectivo" {{ old('forma_pago') == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transferencia" {{ old('forma_pago') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        <option value="tarjeta" {{ old('forma_pago') == 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                    </select>
                    @error('forma_pago') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                {{-- Sección IVA --}}
                <div class="col-12 mb-3" id="seccion-iva" style="display:none">
                    <div class="card border-warning">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                {{-- Switch siempre visible --}}
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="tiene_iva" id="tiene_iva"
                                           value="1" onchange="actualizarIva()"
                                           {{ old('tiene_iva', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="tiene_iva">
                                        Incluye IVA (16%)
                                    </label>
                                </div>
                                {{-- Desglose --}}
                                <div id="iva-desglose" class="d-flex flex-wrap gap-3 align-items-center">
                                    <span class="text-muted small">Subtotal: <strong id="txt-base">$0.00</strong></span>
                                    <span class="text-muted small">IVA 16%: <strong class="text-warning" id="txt-iva">$0.00</strong></span>
                                    <span class="text-muted small">Total: <strong class="text-danger" id="txt-total">$0.00</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">Concepto</label>
                    <input type="text" name="concepto" class="form-control" value="{{ old('concepto') }}">
                    @error('concepto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Notas <span class="text-muted small">(opcional)</span></label>
                    <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-danger">Guardar Gasto</button>
        </form>
    </div>
</div>

<script>
function manejarFormaPago() {
    const forma   = document.getElementById('forma_pago').value;
    const seccion = document.getElementById('seccion-iva');
    seccion.style.display = forma ? '' : 'none';
    actualizarIva();
}

function actualizarIva() {
    const checkbox  = document.getElementById('tiene_iva');
    const desglose  = document.getElementById('iva-desglose');
    const monto     = parseFloat(document.getElementById('monto').value) || 0;

    if (checkbox.checked) {
        const iva  = Math.round(monto * 16 / 116 * 100) / 100;
        const base = Math.round((monto - iva) * 100) / 100;
        document.getElementById('txt-base').textContent  = '$' + base.toFixed(2);
        document.getElementById('txt-iva').textContent   = '$' + iva.toFixed(2);
        document.getElementById('txt-total').textContent = '$' + monto.toFixed(2);
        desglose.style.display = '';
    } else {
        desglose.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const forma = document.getElementById('forma_pago').value;
    if (forma) manejarFormaPago();
    else actualizarIva();
});
</script>
@endsection
