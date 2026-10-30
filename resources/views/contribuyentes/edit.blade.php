@extends('layouts.app')

@section('titulo', 'Editar contribuyente')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Editar {{ $contribuyente->rfc }}</h4>
    <form action="{{ route('contribuyentes.destroy', $contribuyente) }}" method="POST">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar el contribuyente y todas sus facturas descargadas?')">
            <i class="bi bi-trash"></i> Eliminar
        </button>
    </form>
</div>
<form action="{{ route('contribuyentes.update', $contribuyente) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('contribuyentes._form')
</form>
@endsection
