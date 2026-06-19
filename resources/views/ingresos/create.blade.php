@extends('layouts.app')

@section('titulo', 'Nuevo Ingreso')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Ingreso</h4>
    <a href="{{ route('ingresos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('ingresos.store') }}" method="POST">
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
                    <label class="form-label">Cliente <span class="text-muted small">(opcional)</span></label>
                    <select name="cliente_id" class="form-select ts-select" data-placeholder="Sin cliente">
                        <option value="">Sin cliente</option>
                        @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>{{ $cliente->nombre }}</option>
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

                {{-- Sección IVA ingresos: solo transferencia/tarjeta --}}
                <div class="col-12 mb-3" id="seccion-iva" style="display:none">
                    <div class="card border-success">
                        <div class="card-body py-2 px-3">
                            <div class="row g-2 align-items-center">
                                <div class="col-auto text-muted small">Subtotal (sin IVA):</div>
                                <div class="col-auto fw-bold" id="txt-base">$0.00</div>
                                <div class="col-auto text-muted small ms-3">IVA 16%:</div>
                                <div class="col-auto fw-bold text-success" id="txt-iva">$0.00</div>
                                <div class="col-auto text-muted small ms-3">Total:</div>
                                <div class="col-auto fw-bold text-success" id="txt-total">$0.00</div>
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
            <button type="submit" class="btn btn-success">Guardar Ingreso</button>
        </form>
    </div>
</div>

<script>
function manejarFormaPago() {
    const forma = document.getElementById('forma_pago').value;
    const seccion = document.getElementById('seccion-iva');

    if (forma === 'transferencia' || forma === 'tarjeta') {
        seccion.style.display = '';
        actualizarIva();
    } else {
        seccion.style.display = 'none';
    }
}

function actualizarIva() {
    const monto = parseFloat(document.getElementById('monto').value) || 0;
    const iva = Math.round(monto * 16 / 116 * 100) / 100;
    const base = Math.round((monto - iva) * 100) / 100;
    document.getElementById('txt-base').textContent = '$' + base.toFixed(2);
    document.getElementById('txt-iva').textContent = '$' + iva.toFixed(2);
    document.getElementById('txt-total').textContent = '$' + monto.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    const forma = document.getElementById('forma_pago').value;
    if (forma) manejarFormaPago();
});
</script>
@endsection
