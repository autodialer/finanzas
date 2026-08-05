@extends('layouts.app')

@section('titulo', 'Nuevo Préstamo')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nuevo Préstamo</h4>
    <a href="{{ route('prestamos.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('prestamos.store') }}" method="POST">
            @csrf
            <div class="row">
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
                    <label class="form-label">Banco</label>
                    <select name="banco_id" class="form-select">
                        <option value="">Selecciona un banco</option>
                        @foreach($bancos as $banco)
                        <option value="{{ $banco->id }}" {{ old('banco_id') == $banco->id ? 'selected' : '' }}>{{ $banco->nombre }}</option>
                        @endforeach
                    </select>
                    @error('banco_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="auto" {{ old('tipo') == 'auto' ? 'selected' : '' }}>Automóvil</option>
                        <option value="equipo" {{ old('tipo') == 'equipo' ? 'selected' : '' }}>Equipo</option>
                        <option value="otro" {{ old('tipo', 'otro') == 'otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Concepto</label>
                    <input type="text" name="concepto" class="form-control" value="{{ old('concepto') }}" placeholder="Ej: Crédito auto Versa 2024">
                    @error('concepto') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Monto original</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="monto_original" class="form-control" value="{{ old('monto_original') }}">
                    </div>
                    @error('monto_original') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Tasa de interés <span class="text-muted small">(opcional)</span></label>
                    <div class="input-group">
                        <input type="number" step="0.01" name="tasa_interes" class="form-control" value="{{ old('tasa_interes') }}">
                        <span class="input-group-text">%</span>
                    </div>
                    @error('tasa_interes') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Plazo <span class="text-muted small">(opcional)</span></label>
                    <div class="input-group">
                        <input type="number" name="plazo_meses" class="form-control" value="{{ old('plazo_meses') }}">
                        <span class="input-group-text">meses</span>
                    </div>
                    @error('plazo_meses') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Fecha de inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio', date('Y-m-d')) }}">
                    @error('fecha_inicio') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">Notas <span class="text-muted small">(opcional)</span></label>
                    <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-danger">Guardar Préstamo</button>
        </form>
    </div>
</div>
@endsection
