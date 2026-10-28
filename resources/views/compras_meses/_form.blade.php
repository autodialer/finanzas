@php
    $gasto = $compra->gasto;
    $valor = fn ($campo, $defecto = null) => old($campo, $compra->exists ? $compra->$campo : $defecto);
    $fechaValor = fn ($campo, $defecto) => old($campo, $compra->exists ? $compra->$campo->format('Y-m-d') : $defecto);
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Tarjeta</label>
        <select name="cuenta_id" class="form-select ts-select" data-placeholder="Selecciona la tarjeta">
            <option value="">Selecciona la tarjeta</option>
            @foreach($cuentas as $cuenta)
            <option value="{{ $cuenta->id }}" {{ $valor('cuenta_id') == $cuenta->id ? 'selected' : '' }}>{{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}</option>
            @endforeach
        </select>
        @error('cuenta_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Categoría del gasto</label>
        <select name="categoria_id" class="form-select ts-select" data-placeholder="Selecciona una categoría">
            <option value="">Selecciona una categoría</option>
            @foreach($categorias as $categoria)
            <option value="{{ $categoria->id }}" {{ old('categoria_id', $gasto?->categoria_id) == $categoria->id ? 'selected' : '' }}>{{ $categoria->nombre }}</option>
            @endforeach
        </select>
        @error('categoria_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Comercio / descripción</label>
        <input type="text" name="descripcion" class="form-control" value="{{ $valor('descripcion') }}" placeholder="Ej: Tienda departamental — refrigerador">
        @error('descripcion') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Monto original</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" min="0" name="monto_original" id="monto_original" class="form-control" value="{{ $valor('monto_original') }}">
        </div>
        @error('monto_original') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Número de meses</label>
        <div class="input-group">
            <input type="number" min="1" max="60" name="meses" id="meses" class="form-control" value="{{ $valor('meses') }}">
            <span class="input-group-text">meses</span>
        </div>
        @error('meses') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Mensualidad <span class="text-muted small">(se calcula sola; ajústala si el banco redondea distinto)</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" min="0" name="mensualidad" id="mensualidad" class="form-control" value="{{ $valor('mensualidad') }}">
        </div>
        @error('mensualidad') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Fecha de compra</label>
        <input type="date" name="fecha_compra" class="form-control" value="{{ $fechaValor('fecha_compra', date('Y-m-d')) }}">
        @error('fecha_compra') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Fecha del primer cargo</label>
        <input type="date" name="fecha_primer_cargo" id="fecha_primer_cargo" class="form-control" value="{{ $fechaValor('fecha_primer_cargo', now()->addMonthNoOverflow()->format('Y-m-d')) }}">
        @error('fecha_primer_cargo') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="tiene_iva" id="tiene_iva" value="1"
                   {{ old('tiene_iva', $gasto?->tiene_iva) ? 'checked' : '' }}>
            <label class="form-check-label" for="tiene_iva">Incluye IVA (16%)</label>
        </div>
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Notas <span class="text-muted small">(opcional)</span></label>
        <textarea name="notas" class="form-control" rows="2">{{ $valor('notas') }}</textarea>
    </div>
</div>

{{-- Simulador: compromisos mensuales actuales + esta compra --}}
<div class="card border-info mb-3">
    <div class="card-header bg-white fw-bold d-flex justify-content-between">
        <span><i class="bi bi-calculator"></i> Simulador</span>
        <span class="small">Mensualidad: <strong class="text-danger" id="sim-mensualidad">—</strong> · Última: <strong id="sim-ultima">—</strong></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mes</th>
                    <th class="text-end">Compromisos actuales</th>
                    <th class="text-end">Esta compra</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody id="sim-filas"></tbody>
        </table>
    </div>
</div>

@section('scripts')
<script>
(function () {
    const compromisos = @json($compromisos);
    const nombresMes = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const $ = id => document.getElementById(id);
    const dinero = n => '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function cargosPorMes(monto, meses, mensualidad, primerCargo) {
        const cargos = {};
        if (!(monto > 0) || !(meses > 0) || !(mensualidad > 0) || !primerCargo) return cargos;
        const [anio, mes] = primerCargo.split('-').map(Number);
        for (let n = 1; n <= meses; n++) {
            const fecha = new Date(anio, mes - 1 + n - 1, 1);
            const clave = fecha.getFullYear() + '-' + String(fecha.getMonth() + 1).padStart(2, '0');
            cargos[clave] = n < meses ? mensualidad : Math.round((monto - mensualidad * (meses - 1)) * 100) / 100;
        }
        return cargos;
    }

    function actualizar() {
        const monto = parseFloat($('monto_original').value);
        const meses = parseInt($('meses').value, 10);
        const mensualidad = parseFloat($('mensualidad').value);
        const cargos = cargosPorMes(monto, meses, mensualidad, $('fecha_primer_cargo').value);
        const ultima = Object.values(cargos).pop();

        $('sim-mensualidad').textContent = mensualidad > 0 ? dinero(mensualidad) : '—';
        $('sim-ultima').textContent = ultima !== undefined ? dinero(ultima) : '—';

        $('sim-filas').innerHTML = Object.entries(compromisos).map(([clave, actual]) => {
            const [a, m] = clave.split('-').map(Number);
            const esta = cargos[clave] || 0;
            return `<tr${esta ? ' class="table-warning"' : ''}>
                <td>${nombresMes[m - 1]} ${a}</td>
                <td class="text-end">${dinero(actual)}</td>
                <td class="text-end">${esta ? dinero(esta) : '—'}</td>
                <td class="text-end fw-bold">${dinero(actual + esta)}</td>
            </tr>`;
        }).join('');
    }

    // Al cambiar monto o meses se sugiere la mensualidad; después se puede ajustar a mano
    function sugerirMensualidad() {
        const monto = parseFloat($('monto_original').value);
        const meses = parseInt($('meses').value, 10);
        if (monto > 0 && meses > 0) {
            $('mensualidad').value = (Math.round(monto / meses * 100) / 100).toFixed(2);
        }
    }

    ['monto_original', 'meses'].forEach(id => $(id).addEventListener('input', () => { sugerirMensualidad(); actualizar(); }));
    ['mensualidad', 'fecha_primer_cargo'].forEach(id => $(id).addEventListener('input', actualizar));
    if ($('mensualidad').value === '') sugerirMensualidad();
    actualizar();
})();
</script>
@endsection
