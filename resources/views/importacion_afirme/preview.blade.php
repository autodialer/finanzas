@extends('layouts.app')

@section('titulo', 'Vista Previa — Afirme')

@php
    $etiquetasTipo = ['gasto' => 'Gasto', 'ingreso' => 'Ingreso', 'traspaso' => 'Traspaso', 'prestamo' => 'Préstamo', 'ignorar' => 'Ignorar'];
    $conteo = collect($filas)->countBy('tipo');
@endphp

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Vista previa — {{ $importacion['archivo'] }}</h4>
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
        La cuenta elegida (<strong>{{ $cuenta->nombre }}</strong>, {{ $cuenta->numero }}) no coincide con la del estado de cuenta
        (cuenta {{ $estado['numero_cuenta'] }}, CLABE {{ $estado['clabe'] }}). Verifica antes de confirmar.
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
                <table class="table table-sm mb-0">
                    <tr><td>Saldo inicial</td><td class="text-end">${{ number_format($estado['resumen']['saldo_inicial'], 2) }}</td></tr>
                    <tr><td>+ Depósitos</td><td class="text-end text-success">${{ number_format($estado['resumen']['depositos'], 2) }}</td></tr>
                    <tr><td>− Retiros</td><td class="text-end text-danger">${{ number_format($estado['resumen']['retiros'], 2) }}</td></tr>
                    <tr class="fw-semibold"><td>Saldo al corte</td><td class="text-end">${{ number_format($estado['resumen']['saldo_corte'], 2) }}
                        <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Cuadra</span></td></tr>
                </table>
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
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                        <td>
                            <input type="text" name="filas[{{ $i }}][concepto]" class="form-control form-control-sm"
                                   value="{{ old("filas.$i.concepto", $fila['concepto']) }}" maxlength="255">
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
            <span class="small text-muted">Las filas en "Ignorar" no se guardan.</span>
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
