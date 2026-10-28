{{-- Tabla de movimientos a importar (gasto / ingreso / traspaso / préstamo). Espera $filas y $campo (nombre del arreglo del formulario). --}}
@php $etiquetasTipo = ['gasto' => 'Gasto', 'ingreso' => 'Ingreso', 'traspaso' => 'Traspaso', 'prestamo' => 'Préstamo', 'ignorar' => 'Ignorar']; @endphp
<div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th style="min-width: 280px">Descripción</th>
                        <th class="text-end">Monto</th>
                        <th>Tipo</th>
                        <th style="min-width: 240px">Categoría / destino</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($filas as $i => $fila)
                    @php
                        $tipo = old("$campo.$i.tipo", $fila['tipo']);
                        $esRetiro = $fila['naturaleza'] === 'retiro';
                        $tiposPermitidos = $esRetiro ? ['gasto', 'traspaso', 'prestamo', 'ignorar'] : ['ingreso', 'traspaso', 'ignorar'];
                    @endphp
                    <tr class="fila-importacion {{ $errors->has("$campo.$i") ? 'table-danger' : '' }}" data-tipo="{{ $tipo }}">
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                        <td>
                            <input type="text" name="{{ $campo }}[{{ $i }}][concepto]" class="form-control form-control-sm"
                                   value="{{ old("$campo.$i.concepto", $fila['concepto']) }}" maxlength="255">
                            <div class="small text-muted mt-1">
                                {{ $fila['motivo'] }}
                                @if($fila['referencia']) · Ref. {{ $fila['referencia'] }}@endif
                                @if($fila['duplicado'])<span class="badge bg-warning text-dark ms-1">Duplicado</span>@endif
                            </div>
                            <details class="small text-muted"><summary>Detalle</summary>{{ $fila['detalle'] }}</details>
                        </td>
                        <td class="text-end text-nowrap {{ $esRetiro ? 'text-danger' : 'text-success' }}">
                            {{ $esRetiro ? '−' : '+' }}${{ number_format($fila['monto'], 2) }}
                            @if($fila['monto_iva'] > 0)
                                <div class="small text-muted">IVA ${{ number_format($fila['monto_iva'], 2) }}</div>
                            @endif
                        </td>
                        <td>
                            <select name="{{ $campo }}[{{ $i }}][tipo]" class="form-select form-select-sm selector-tipo">
                                @foreach($tiposPermitidos as $opcion)
                                    <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $etiquetasTipo[$opcion] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="campo-tipo" data-para="gasto">
                                <select name="{{ $campo }}[{{ $i }}][categoria_id]" class="form-select form-select-sm" @disabled($tipo !== 'gasto')>
                                    <option value="">Categoría...</option>
                                    @foreach($categoriasGasto as $categoria)
                                        <option value="{{ $categoria->id }}" @selected(old("$campo.$i.categoria_id", $fila['categoria_id']) == $categoria->id)>{{ $categoria->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="campo-tipo" data-para="ingreso">
                                <select name="{{ $campo }}[{{ $i }}][categoria_id]" class="form-select form-select-sm" @disabled($tipo !== 'ingreso')>
                                    <option value="">Categoría...</option>
                                    @foreach($categoriasIngreso as $categoria)
                                        <option value="{{ $categoria->id }}" @selected(old("$campo.$i.categoria_id", $fila['categoria_id']) == $categoria->id)>{{ $categoria->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="campo-tipo" data-para="traspaso">
                                <select name="{{ $campo }}[{{ $i }}][cuenta_destino_id]" class="form-select form-select-sm">
                                    <option value="">{{ $esRetiro ? 'Cuenta destino...' : 'Cuenta origen...' }}</option>
                                    @foreach($cuentas as $otra)
                                        <option value="{{ $otra->id }}" @selected(old("$campo.$i.cuenta_destino_id", $fila['cuenta_destino_id']) == $otra->id)>
                                            {{ $otra->nombre }}@if($otra->negocio) — {{ $otra->negocio->nombre }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="campo-tipo" data-para="prestamo">
                                <select name="{{ $campo }}[{{ $i }}][prestamo_id]" class="form-select form-select-sm mb-1">
                                    <option value="">Préstamo...</option>
                                    @foreach($prestamos as $prestamo)
                                        <option value="{{ $prestamo->id }}" @selected(old("$campo.$i.prestamo_id", $fila['prestamo_id']) == $prestamo->id)>
                                            {{ $prestamo->concepto }}@if($prestamo->banco) ({{ $prestamo->banco->nombre }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <select name="{{ $campo }}[{{ $i }}][prestamo_tipo]" class="form-select form-select-sm">
                                    @foreach(['capital' => 'Capital', 'interes' => 'Interés'] as $valor => $texto)
                                        <option value="{{ $valor }}" @selected(old("$campo.$i.prestamo_tipo", $fila['prestamo_tipo']) === $valor)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

<script>
    // Muestra solo los campos del tipo elegido. Los selects de categoría ocultos se
    // deshabilitan porque gasto e ingreso comparten el mismo nombre de campo.
    document.querySelectorAll('.fila-importacion').forEach(function (fila) {
        const selector = fila.querySelector('.selector-tipo');
        const actualizar = function () {
            fila.querySelectorAll('.campo-tipo').forEach(function (campo) {
                const visible = campo.dataset.para === selector.value;
                campo.classList.toggle('d-none', !visible);
                campo.querySelectorAll('select').forEach(function (s) { s.disabled = !visible; });
            });
        };
        selector.addEventListener('change', actualizar);
        actualizar();
    });
</script>
