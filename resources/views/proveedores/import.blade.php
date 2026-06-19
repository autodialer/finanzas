@extends('layouts.app')

@section('titulo', 'Importar Proveedores')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Importar Proveedores desde CSV</h4>
    <a href="{{ route('proveedores.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Regresar
    </a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('proveedores.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Negocio</label>
                        <select name="negocio_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            @foreach($negocios as $negocio)
                                <option value="{{ $negocio->id }}">{{ $negocio->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Archivo CSV</label>
                        <input type="file" name="archivo" class="form-control" accept=".csv,.txt" required>
                        @error('archivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Importar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle me-1"></i>Formato del archivo CSV
            </div>
            <div class="card-body">
                <p>El archivo debe tener estas columnas en este orden:</p>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Columna A</th>
                            <th>Columna B</th>
                            <th>Columna C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>nombre</strong></td>
                            <td>telefono</td>
                            <td>email</td>
                        </tr>
                        <tr class="text-muted">
                            <td>Proveedor SA</td>
                            <td>3311234567</td>
                            <td>contacto@prov.com</td>
                        </tr>
                        <tr class="text-muted">
                            <td>Distribuidora XYZ</td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <ul class="small text-muted mb-0">
                    <li>La primera fila debe ser el encabezado (se ignora)</li>
                    <li>Solo <strong>nombre</strong> es obligatorio</li>
                    <li>Teléfono y email son opcionales</li>
                    <li>Guarda tu Excel como: <strong>Archivo → Guardar como → CSV UTF-8</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
