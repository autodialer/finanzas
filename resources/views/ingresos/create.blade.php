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
                    <label class="form-label">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto" class="form-control" value="{{ old('monto') }}">
                    </div>
                    @error('monto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Negocio</label>
                    <select name="negocio_id" class="form-select">
                        <option value="">Selecciona un negocio</option>
                        @foreach($negocios as $negocio)
                        <option value="{{ $negocio->id }}" {{ old('negocio_id') == $negocio->id ? 'selected' : '' }}>
                            {{ $negocio->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('negocio_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Área <span class="text-muted small">(opcional)</span></label>
                    <select name="area_id" class="form-select">
                        <option value="">Sin área</option>
                        @foreach($areas as $area)
                        <option value="{{ $area->id }}" {{ old('area_id') == $area->id ? 'selected' : '' }}>
                            {{ $area->negocio->nombre }} — {{ $area->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">Selecciona una categoría</option>
                        @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" {{ old('categoria_id') == $categoria->id ? 'selected' : '' }}>
                            {{ $categoria->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('categoria_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cliente <span class="text-muted small">(opcional)</span></label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Sin cliente</option>
                        @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuenta</label>
                    <select name="cuenta_id" class="form-select">
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" {{ old('cuenta_id') == $cuenta->id ? 'selected' : '' }}>
                            {{ $cuenta->negocio->nombre }} — {{ $cuenta->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('cuenta_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Forma de Pago</label>
                    <select name="forma_pago" class="form-select">
                        <option value="">Selecciona la forma de pago</option>
                        <option value="efectivo" {{ old('forma_pago') == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transferencia" {{ old('forma_pago') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        <option value="tarjeta" {{ old('forma_pago') == 'tarjeta' ? 'selected' : '' }}>Tarjeta</option>
                    </select>
                    @error('forma_pago') <div class="text-danger small">{{ $message }}</div> @enderror
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
@endsection