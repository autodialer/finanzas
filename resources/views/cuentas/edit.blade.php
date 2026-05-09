@extends('layouts.app')

@section('titulo', 'Editar Cuenta')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Editar Cuenta</h4>
    <a href="{{ route('cuentas.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('cuentas.update', $cuenta) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Negocio</label>
                <select name="negocio_id" class="form-select">
                    <option value="">Selecciona un negocio</option>
                    @foreach($negocios as $negocio)
                    <option value="{{ $negocio->id }}" {{ old('negocio_id', $cuenta->negocio_id) == $negocio->id ? 'selected' : '' }}>
                        {{ $negocio->nombre }}
                    </option>
                    @endforeach
                </select>
                @error('negocio_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Nombre de la Cuenta</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $cuenta->nombre) }}">
                @error('nombre') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select" id="tipo">
                    <option value="">Selecciona el tipo</option>
                    <option value="efectivo" {{ old('tipo', $cuenta->tipo) == 'efectivo' ? 'selected' : '' }}>Efectivo</option>
                    <option value="banco" {{ old('tipo', $cuenta->tipo) == 'banco' ? 'selected' : '' }}>Banco</option>
                </select>
                @error('tipo') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3" id="campo-banco" style="{{ old('tipo', $cuenta->tipo) == 'banco' ? '' : 'display:none' }}">
                <label class="form-label">Banco</label>
                <select name="banco_id" class="form-select">
                    <option value="">Selecciona un banco</option>
                    @foreach($bancos as $banco)
                    <option value="{{ $banco->id }}" {{ old('banco_id', $cuenta->banco_id) == $banco->id ? 'selected' : '' }}>
                        {{ $banco->nombre }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3" id="campo-numero" style="{{ old('tipo', $cuenta->tipo) == 'banco' ? '' : 'display:none' }}">
                <label class="form-label">Número de Cuenta</label>
                <input type="text" name="numero" class="form-control" value="{{ old('numero', $cuenta->numero) }}">
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('tipo').addEventListener('change', function() {
        const esBanco = this.value === 'banco';
        document.getElementById('campo-banco').style.display = esBanco ? 'block' : 'none';
        document.getElementById('campo-numero').style.display = esBanco ? 'block' : 'none';
    });
</script>
@endsection