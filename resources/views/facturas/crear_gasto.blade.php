@extends('layouts.app')

@section('titulo', 'Crear gasto desde factura')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Crear gasto desde factura</h4>
    <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

@include('contribuyentes._errores')

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-receipt me-1"></i>Factura</div>
            <div class="card-body small">
                <div class="fw-semibold fs-6">{{ $cfdi->nombre_emisor }}</div>
                <div class="text-muted mb-2">{{ $cfdi->rfc_emisor }} @if($cfdi->serie_folio)· {{ $cfdi->serie_folio }}@endif</div>
                <table class="table table-sm mb-2">
                    <tr><td>Fecha</td><td class="text-end">{{ $cfdi->fecha->format('d/m/Y H:i') }}</td></tr>
                    <tr><td>Subtotal</td><td class="text-end">${{ number_format($cfdi->subtotal - $cfdi->descuento, 2) }}</td></tr>
                    <tr><td>IVA</td><td class="text-end">${{ number_format($cfdi->iva_trasladado, 2) }}</td></tr>
                    @if($cfdi->isr_retenido > 0 || $cfdi->iva_retenido > 0)
                        <tr><td>Retenciones (ISR / IVA)</td><td class="text-end">−${{ number_format($cfdi->isr_retenido, 2) }} / −${{ number_format($cfdi->iva_retenido, 2) }}</td></tr>
                    @endif
                    <tr class="fw-semibold"><td>Total</td><td class="text-end">${{ number_format($cfdi->total, 2) }} {{ $cfdi->moneda }}</td></tr>
                    @if(!in_array($cfdi->moneda, ['MXN', 'XXX'], true))
                        <tr><td>En pesos (T.C. {{ $cfdi->tipo_cambio }})</td><td class="text-end">${{ number_format($cfdi->totalMxn(), 2) }}</td></tr>
                    @endif
                    <tr><td>Método / forma de pago</td><td class="text-end">{{ $cfdi->metodo_pago }} / {{ $cfdi->forma_pago }}</td></tr>
                </table>
                <div class="text-muted">{{ $cfdi->conceptos }}</div>
                @if($cfdi->metodo_pago === 'PPD')
                    <div class="alert alert-warning py-1 px-2 mt-2 mb-0">Factura PPD (pago diferido): registra el gasto con la fecha en que la pagaste.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">
                Gasto
                @if($sugerencia['origen'])<span class="badge bg-info text-dark ms-1">Sugerido por {{ $sugerencia['origen'] }}</span>@endif
            </div>
            <div class="card-body">
                <form action="{{ route('facturas.guardar-gasto', $cfdi) }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha del pago</label>
                            <input type="date" name="fecha" class="form-control" required value="{{ old('fecha', $sugerencia['fecha']) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monto</label>
                            <input type="text" class="form-control" value="${{ number_format($cfdi->totalMxn(), 2) }}" disabled>
                            <div class="form-text">Se toma de la factura, con su IVA.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Concepto</label>
                            <input type="text" name="concepto" class="form-control" maxlength="255" required value="{{ old('concepto', $sugerencia['concepto']) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Negocio</label>
                            <select name="negocio_id" id="negocio" class="form-select" required>
                                @foreach($negocios as $negocio)
                                    <option value="{{ $negocio->id }}" @selected(old('negocio_id', $sugerencia['negocio_id']) == $negocio->id)>{{ $negocio->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            @php $cat = old('categoria_id', $sugerencia['categoria_id']); @endphp
                            <select name="categoria_id" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach($categoriasGasto as $categoria)
                                    <option value="{{ $categoria->id }}" @selected($cat == $categoria->id)>{{ $categoria->nombre }}</option>
                                @endforeach
                                @if($categoriasNuevas)
                                    <optgroup label="Nuevas (se crean al guardar)">
                                        @foreach($categoriasNuevas as $nueva)
                                            @php $valor = \App\Services\SugeridorCategoria::PREFIJO_NUEVA . $nueva; @endphp
                                            <option value="{{ $valor }}" @selected($cat === $valor)>{{ $nueva }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cuenta con la que se pagó</label>
                            <select name="cuenta_id" id="cuenta" class="form-select" required>
                                <option value="">Selecciona...</option>
                                @foreach($cuentas as $cuenta)
                                    <option value="{{ $cuenta->id }}" data-negocio="{{ $cuenta->negocio_id }}" @selected(old('cuenta_id', $sugerencia['cuenta_id']) == $cuenta->id)>{{ $cuenta->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Forma de pago</label>
                            <select name="forma_pago" class="form-select" required>
                                @foreach(['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo'] as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('forma_pago', $sugerencia['forma_pago']) === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2 align-items-center">
                        <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Crear gasto y vincular</button>
                        <span class="small text-muted">La categoría y el negocio se recordarán para este proveedor.</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Solo las cuentas del negocio elegido
    (function () {
        const negocio = document.getElementById('negocio');
        const cuenta = document.getElementById('cuenta');
        const filtrar = function () {
            cuenta.querySelectorAll('option[data-negocio]').forEach(function (op) {
                const visible = op.dataset.negocio === negocio.value;
                op.hidden = !visible;
                if (!visible && op.selected) { cuenta.value = ''; }
            });
        };
        negocio.addEventListener('change', filtrar);
        filtrar();
    })();
</script>
@endsection
