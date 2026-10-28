@extends('layouts.app')

@section('titulo', 'Nueva compra a meses')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Nueva compra a meses</h4>
    <a href="{{ route('compras-meses.index') }}" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="alert alert-info small py-2">
    Se registra <strong>un solo gasto</strong> por el monto original en la fecha de compra, cargado a la tarjeta.
    Las mensualidades se pagan con el traspaso normal a la tarjeta.
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('compras-meses.store') }}" method="POST">
            @csrf
            @include('compras_meses._form')
            <button type="submit" class="btn btn-danger">Guardar compra</button>
        </form>
    </div>
</div>
@endsection
