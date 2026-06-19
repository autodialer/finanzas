@extends('layouts.app')

@section('titulo', 'Editar Usuario')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-gear me-2"></i>Editar Usuario</h4>
    <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="card border-0 shadow-sm" style="max-width: 540px;">
    <div class="card-body p-4">
        @if($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('usuarios.update', $usuario) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold">Nombre</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $usuario->name) }}" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Correo electrónico</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $usuario->email) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Rol</label>
                <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                    <option value="admin" {{ old('role', $usuario->role) === 'admin' ? 'selected' : '' }}>
                        Administrador (acceso total, puede gestionar usuarios)
                    </option>
                    <option value="usuario" {{ old('role', $usuario->role) === 'usuario' ? 'selected' : '' }}>
                        Usuario (acceso general, sin gestión de usuarios)
                    </option>
                </select>
            </div>

            <hr class="my-3">
            <p class="text-muted small mb-2">
                <i class="bi bi-info-circle me-1"></i>Deja la contraseña en blanco para no cambiarla.
            </p>

            <div class="mb-3">
                <label class="form-label fw-semibold">Nueva contraseña</label>
                <input type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="new-password">
                <div class="form-text">Mínimo 8 caracteres.</div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Confirmar nueva contraseña</label>
                <input type="password" name="password_confirmation" class="form-control"
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-floppy me-1"></i>Guardar Cambios
            </button>
        </form>
    </div>
</div>
@endsection
