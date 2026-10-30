@extends('layouts.app')

@section('titulo', 'Nuevo contribuyente')

@section('contenido')
<h4 class="mb-3">Nuevo contribuyente</h4>
<form action="{{ route('contribuyentes.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('contribuyentes._form')
</form>
@endsection
