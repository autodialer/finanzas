@extends('layouts.app')

@php
    $esAmex = ($estado['banco'] ?? null) === 'amex';

    $etiquetasTipo = ['gasto' => 'Gasto', 'ingreso' => 'Ingreso', 'traspaso' => 'Traspaso', 'prestamo' => 'Préstamo', 'ignorar' => 'Ignorar'];
    $conteo = collect($filas)->countBy('tipo');
    $esTarjeta = $estado['tipo'] === 'tarjeta';
@endphp

@section('titulo', 'Vista Previa — ' . ($esAmex ? 'American Express' : 'Estado de cuenta'))

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>
        Vista previa — {{ $importacion['archivo'] }}
        <span class="badge bg-secondary fs-6 align-middle">{{ $esAmex ? 'American Express' : ($esTarjeta ? 'Tarjeta de crédito' : 'Cuenta Visión') }}</span>
    </h4>
    <form action="{{ route('importacion-afirme.cancelar') }}" method="POST">
        @csrf
        <button class="btn btn-secondary btn-sm"><i class="bi bi-x-lg"></i> Cancelar</button>
    </form>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa estas filas:</strong>
        <ul class="mb-0 mt-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($cuentaDistinta)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        @if($esTarjeta)
            La cuenta elegida (<strong>{{ $cuenta->nombre }}</strong>, terminación {{ substr($cuenta->numero, -4) }}) no coincide con la tarjeta
            del estado de cuenta (terminación {{ collect([$estado['ultimos4']])->merge($estado['tarjetas'] ?? [])->filter()->unique()->implode(', ') }}). Verifica antes de confirmar.
        @else
            La cuenta elegida (<strong>{{ $cuenta->nombre }}</strong>, {{ $cuenta->numero }}) no coincide con la del estado de cuenta
            (cuenta {{ $estado['numero_cuenta'] }}, CLABE {{ $estado['clabe'] }}). Verifica antes de confirmar.
        @endif
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-body small">
                <div class="mb-2">
                    <strong>Cuenta:</strong> {{ $cuenta->nombre }} — {{ $cuenta->negocio->nombre ?? '' }}<br>
                    <strong>Período:</strong> {{ \Carbon\Carbon::parse($estado['periodo_inicio'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($estado['periodo_fin'])->format('d/m/Y') }}
                </div>
                @if($esAmex)
                <table class="table table-sm mb-0">
                    <tr><td>Tarjetas</td><td class="text-end">
                        @foreach($estado['tarjetas'] as $t)<span class="badge bg-light text-dark border ms-1">···{{ $t }}{{ $t === $estado['ultimos4'] ? ' titular' : '' }}</span>@endforeach
                    </td></tr>
                    <tr><td>Saldo anterior</td><td class="text-end">${{ number_format($estado['resumen']['saldo_anterior'], 2) }}</td></tr>
                    <tr><td>− Pagos y créditos</td><td class="text-end text-success">${{ number_format($estado['resumen']['pagos_abonos'], 2) }}</td></tr>
                    <tr><td>+ Nuevos cargos</td><td class="text-end text-danger">${{ number_format($estado['resumen']['nuevos_cargos'], 2) }}</td></tr>
                    <tr class="fw-semibold"><td>Saldo al corte</td><td class="text-end">${{ number_format($estado['resumen']['saldo_corte'], 2) }}
                        <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Cuadra</span></td></tr>
                    @if($estado['resumen']['comisiones'] !== null)
                        <tr class="text-muted"><td>Comisiones del periodo (sin IVA)</td><td class="text-end">${{ number_format($estado['resumen']['comisiones'], 2) }}</td></tr>
                    @endif
                    @if($estado['resumen']['msi_mensualidades'] !== null)
                        <tr class="text-muted"><td>Mensualidades a meses (incluidas en nuevos cargos)</td><td class="text-end">${{ number_format($estado['resumen']['msi_mensualidades'], 2) }}</td></tr>
                        <tr class="text-muted"><td>Saldo pendiente a meses</td><td class="text-end">${{ number_format($estado['resumen']['msi_saldo'], 2) }}</td></tr>
                    @endif
                    @if(($estado['resumen']['comision_mensualidades'] ?? null) !== null)
                        <tr class="text-muted"><td>Planes de pagos diferidos con comisión (mensualidad incluida en nuevos cargos)</td><td class="text-end">${{ number_format($estado['resumen']['comision_mensualidades'], 2) }}</td></tr>
                        <tr class="text-muted"><td>Saldo pendiente de planes con comisión</td><td class="text-end">${{ number_format($estado['resumen']['comision_saldo'], 2) }}</td></tr>
                    @endif
                </table>
                @elseif($esTarjeta)
                <table class="table table-sm mb-0">
                    <tr><td>Tarjeta</td><td class="text-end">terminación {{ $estado['ultimos4'] ?? '—' }}</td></tr>
                    <tr><td>Cargos regulares (no a meses)</td><td class="text-end text-danger">${{ number_format($estado['resumen']['cargos_regulares'], 2) }}</td></tr>
                    <tr><td>Pagos y abonos</td><td class="text-end text-success">${{ number_format($estado['resumen']['pagos_abonos'], 2) }}</td></tr>
                    @if($estado['resumen']['cargos_meses'] !== null)
                        <tr class="text-muted"><td>Cargos a meses (capital)</td><td class="text-end">${{ number_format($estado['resumen']['cargos_meses'], 2) }}</td></tr>
                    @endif
                    @if($estado['resumen']['pago_no_intereses'] !== null)
                        <tr class="fw-semibold"><td>Pago para no generar intereses</td><td class="text-end">${{ number_format($estado['resumen']['pago_no_intereses'], 2) }}
                            <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Cuadra</span></td></tr>
                    @endif
                </table>
                @else
                <table class="table table-sm mb-0">
                    <tr><td>Saldo inicial</td><td class="text-end">${{ number_format($estado['resumen']['saldo_inicial'], 2) }}</td></tr>
                    <tr><td>+ Depósitos</td><td class="text-end text-success">${{ number_format($estado['resumen']['depositos'], 2) }}</td></tr>
                    <tr><td>− Retiros</td><td class="text-end text-danger">${{ number_format($estado['resumen']['retiros'], 2) }}</td></tr>
                    <tr class="fw-semibold"><td>Saldo al corte</td><td class="text-end">${{ number_format($estado['resumen']['saldo_corte'], 2) }}
                        <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Cuadra</span></td></tr>
                </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-body small">
                <strong>Tipos sugeridos</strong>
                <ul class="list-unstyled mb-0 mt-2">
                    @foreach($etiquetasTipo as $tipo => $etiqueta)
                        <li>{{ $etiqueta }}: <strong>{{ $conteo[$tipo] ?? 0 }}</strong></li>
                    @endforeach
                    <li class="text-muted">Duplicados detectados: {{ collect($filas)->where('duplicado', true)->count() }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('importacion-afirme.confirmar') }}" method="POST">
    @csrf
    @if($meses)
    @php
        $C = \App\Services\ConciliadorComprasMeses::class;
        $estadosMeses = [
            $C::CREAR      => ['Nueva (pago 1)', 'bg-primary'],
            $C::SIN_PLAN   => ['Sin plan registrado', 'bg-secondary'],
            $C::VERIFICADA => ['Coincide con el plan', 'bg-success'],
            $C::DIFERENCIA => ['No coincide', 'bg-warning text-dark'],
        ];
    @endphp
    <div class="card mb-3">
        <div class="card-header small fw-semibold">
            <i class="bi bi-calendar3 me-1"></i>Compras a meses sin intereses
            <div class="fw-normal text-muted">
                Cada compra a meses es un gasto por el monto original + su plan; las mensualidades no son gastos (se pagan con el traspaso a la tarjeta).
                @if($esAmex)
                    Las compras a meses sin plan registrado (pago 2 en adelante) son anteriores a lo capturado: se propone registrar solo la mensualidad del periodo como gasto. Las compras nuevas (pago 1) se dan de alta con su plan.
                @else
                    El estado de cuenta no trae la fecha de compra: se propone la del primer cargo.
                @endif
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th style="min-width: 220px">Estado de cuenta</th>
                        <th class="text-end">Importes</th>
                        <th style="min-width: 220px">Resultado</th>
                        <th style="min-width: 380px">Alta de la compra a meses</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($meses as $i => $msi)
                    @php
                        [$textoEstado, $claseEstado] = $estadosMeses[$msi['accion']];
                        $puedeAlta = in_array($msi['accion'], [$C::CREAR, $C::SIN_PLAN], true);
                        $sug = $msi['sugerencia'];
                        $accionMeses = old("meses.$i.accion", $msi['accion_sugerida']);
                        $permiteMensualidad = array_key_exists('mensualidades_previas', $msi);
                        $categoriaMeses = old("meses.$i.categoria_id", $msi['categoria_id']);
                        $plan = $msi['compra_id'] ? ($compras[$msi['compra_id']] ?? null) : null;
                    @endphp
                    <tr class="{{ $errors->has("meses.$i") ? 'table-danger' : '' }}">
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="fw-semibold">{{ $msi['descripcion'] }}</div>
                            <div class="small text-muted">Cargo {{ \Carbon\Carbon::parse($msi['fecha'])->format('d/m/Y') }} · Pago {{ $msi['numero_pago'] }} de {{ $msi['total_pagos'] ?? '?' }}</div>
                            @if(!empty($msi['fecha_compra']))
                                <div class="small text-muted">Compra {{ \Carbon\Carbon::parse($msi['fecha_compra'])->format('d/m/Y') }}</div>
                            @endif
                            @if(!empty($msi['tarjeta']))<span class="badge bg-light text-dark border">Tarjeta ···{{ $msi['tarjeta'] }}</span>@endif
                        </td>
                        <td class="text-end small text-nowrap">
                            Original ${{ number_format($msi['monto_original'], 2) }}<br>
                            Pago ${{ number_format($msi['pago_requerido'], 2) }}<br>
                            Saldo ${{ number_format($msi['saldo_pendiente'], 2) }}
                        </td>
                        <td>
                            <span class="badge {{ $claseEstado }}">{{ $textoEstado }}</span>
                            @if($plan)
                                <div class="small mt-1"><a href="{{ route('compras-meses.show', $plan) }}" target="_blank">{{ $plan->descripcion }}</a></div>
                            @endif
                            @if($previo = $msi['gasto_previo'] ?? null)
                                <div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle"></i>
                                    Ya hay un gasto por ${{ number_format($previo['monto'], 2) }} el {{ \Carbon\Carbon::parse($previo['fecha'])->format('d/m/Y') }}
                                    ("{{ $previo['concepto'] }}"): dar de alta el plan lo duplicaría.</div>
                            @endif
                            @if($msi['mensualidad_registrada'] ?? false)
                                <div class="small text-muted mt-1"><i class="bi bi-check2"></i> La mensualidad de este periodo ya está registrada como gasto.</div>
                            @elseif(($msi['mensualidades_previas'] ?? 0) > 0)
                                <div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> {{ $msi['mensualidades_previas'] }} mensualidad(es) anteriores registradas como gasto.</div>
                            @endif
                            @foreach($msi['avisos'] as $aviso)
                                <div class="small text-danger mt-1"><i class="bi bi-exclamation-circle"></i> {{ $aviso }}</div>
                            @endforeach
                        </td>
                        <td>
                            @if($puedeAlta)
                            <div class="row g-1">
                                <div class="col-12">
                                    <select name="meses[{{ $i }}][accion]" class="form-select form-select-sm">
                                        <option value="alta" @selected($accionMeses === 'alta')>Dar de alta (gasto + plan)</option>
                                        @if($permiteMensualidad)
                                            <option value="mensualidad" @selected($accionMeses === 'mensualidad')>Registrar solo esta mensualidad como gasto</option>
                                        @endif
                                        <option value="ignorar" @selected($accionMeses === 'ignorar')>Ignorar</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="meses[{{ $i }}][descripcion]" class="form-control form-control-sm" maxlength="255"
                                           value="{{ old("meses.$i.descripcion", $sug['descripcion']) }}" placeholder="Descripción">
                                </div>
                                <div class="col-12">
                                    <select name="meses[{{ $i }}][categoria_id]" class="form-select form-select-sm">
                                        <option value="">Categoría del gasto...</option>
                                        @foreach($categoriasGasto as $categoria)
                                            <option value="{{ $categoria->id }}" @selected($categoriaMeses == $categoria->id)>{{ $categoria->nombre }}</option>
                                        @endforeach
                                        @if($categoriasNuevas)
                                            <optgroup label="Nuevas (se crean al confirmar)">
                                                @foreach($categoriasNuevas as $nueva)
                                                    @php $valor = \App\Services\SugeridorCategoria::PREFIJO_NUEVA . $nueva; @endphp
                                                    <option value="{{ $valor }}" @selected($categoriaMeses === $valor)>{{ $nueva }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Meses</label>
                                    <input type="number" min="1" max="60" name="meses[{{ $i }}][meses]" class="form-control form-control-sm" value="{{ old("meses.$i.meses", $sug['meses']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Mensualidad</label>
                                    <input type="number" step="0.01" name="meses[{{ $i }}][mensualidad]" class="form-control form-control-sm" value="{{ old("meses.$i.mensualidad", $sug['mensualidad']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Fecha de compra{{ $msi['accion'] === $C::SIN_PLAN ? ' (estimada)' : '' }}</label>
                                    <input type="date" name="meses[{{ $i }}][fecha_compra]" class="form-control form-control-sm" value="{{ old("meses.$i.fecha_compra", $sug['fecha_compra']) }}">
                                </div>
                                <div class="col-6">
                                    <label class="small text-muted">Primer cargo</label>
                                    <input type="date" name="meses[{{ $i }}][fecha_primer_cargo]" class="form-control form-control-sm" value="{{ old("meses.$i.fecha_primer_cargo", $sug['fecha_primer_cargo']) }}">
                                </div>
                            </div>
                            @else
                                <span class="small text-muted">Ya registrada; no se guarda nada.</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="card">
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
                        $tipo = old("filas.$i.tipo", $fila['tipo']);
                        $esRetiro = $fila['naturaleza'] === 'retiro';
                        $tiposPermitidos = $esRetiro ? ['gasto', 'traspaso', 'prestamo', 'ignorar'] : ['ingreso', 'traspaso', 'ignorar'];
                    @endphp
                    <tr class="fila-importacion {{ $errors->has("filas.$i") ? 'table-danger' : '' }}" data-tipo="{{ $tipo }}">
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="text-nowrap">
                            {{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}
                            @if(($fila['fecha_cargo'] ?? $fila['fecha']) !== $fila['fecha'])
                                <div class="small text-muted">cargo {{ \Carbon\Carbon::parse($fila['fecha_cargo'])->format('d/m') }}</div>
                            @endif
                        </td>
                        <td>
                            <input type="text" name="filas[{{ $i }}][concepto]" class="form-control form-control-sm"
                                   value="{{ old("filas.$i.concepto", $fila['concepto']) }}" maxlength="255">
                            <div class="small text-muted mt-1">
                                {{ $fila['motivo'] }}
                                @if($fila['referencia']) · Ref. {{ $fila['referencia'] }}@endif
                                @if(!empty($fila['tarjeta']))<span class="badge bg-light text-dark border ms-1">Tarjeta ···{{ $fila['tarjeta'] }}</span>@endif
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
                            <select name="filas[{{ $i }}][tipo]" class="form-select form-select-sm selector-tipo">
                                @foreach($tiposPermitidos as $opcion)
                                    <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $etiquetasTipo[$opcion] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="campo-tipo" data-para="gasto">
                                <select name="filas[{{ $i }}][categoria_id]" class="form-select form-select-sm" @disabled($tipo !== 'gasto')>
                                    <option value="">Categoría...</option>
                                    @foreach($categoriasGasto as $categoria)
                                        <option value="{{ $categoria->id }}" @selected(old("filas.$i.categoria_id", $fila['categoria_id']) == $categoria->id)>{{ $categoria->nombre }}</option>
                                    @endforeach
                                    @if($categoriasNuevas)
                                        <optgroup label="Nuevas (se crean al confirmar)">
                                            @foreach($categoriasNuevas as $nueva)
                                                @php $valor = \App\Services\SugeridorCategoria::PREFIJO_NUEVA . $nueva; @endphp
                                                <option value="{{ $valor }}" @selected(old("filas.$i.categoria_id", $fila['categoria_id']) === $valor)>{{ $nueva }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="campo-tipo" data-para="ingreso">
                                <select name="filas[{{ $i }}][categoria_id]" class="form-select form-select-sm" @disabled($tipo !== 'ingreso')>
                                    <option value="">Categoría...</option>
                                    @foreach($categoriasIngreso as $categoria)
                                        <option value="{{ $categoria->id }}" @selected(old("filas.$i.categoria_id", $fila['categoria_id']) == $categoria->id)>{{ $categoria->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="campo-tipo" data-para="traspaso">
                                <select name="filas[{{ $i }}][cuenta_destino_id]" class="form-select form-select-sm">
                                    <option value="">{{ $esRetiro ? 'Cuenta destino...' : 'Cuenta origen...' }}</option>
                                    @foreach($cuentas as $otra)
                                        <option value="{{ $otra->id }}" @selected(old("filas.$i.cuenta_destino_id", $fila['cuenta_destino_id']) == $otra->id)>
                                            {{ $otra->nombre }}@if($otra->negocio) — {{ $otra->negocio->nombre }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="campo-tipo" data-para="prestamo">
                                <select name="filas[{{ $i }}][prestamo_id]" class="form-select form-select-sm mb-1">
                                    <option value="">Préstamo...</option>
                                    @foreach($prestamos as $prestamo)
                                        <option value="{{ $prestamo->id }}" @selected(old("filas.$i.prestamo_id", $fila['prestamo_id']) == $prestamo->id)>
                                            {{ $prestamo->concepto }}@if($prestamo->banco) ({{ $prestamo->banco->nombre }})@endif
                                        </option>
                                    @endforeach
                                </select>
                                <select name="filas[{{ $i }}][prestamo_tipo]" class="form-select form-select-sm">
                                    @foreach(['capital' => 'Capital', 'interes' => 'Interés'] as $valor => $texto)
                                        <option value="{{ $valor }}" @selected(old("filas.$i.prestamo_tipo", $fila['prestamo_tipo']) === $valor)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted">Las filas en "Ignorar" no se guardan. La categoría que elijas se recordará para cada comercio.</span>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg me-1"></i>Confirmar importación
            </button>
        </div>
    </div>
</form>

@endsection

@section('scripts')
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
@endsection
