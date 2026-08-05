@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Gestión de Usuarios</h4>
    <a href="{{ route('usuarios.create') }}" class="btn btn-dark btn-sm">
        <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
    </a>
</div>

{{-- Enlace de recuperación generado --}}
@if(session('enlace_reset'))
<div class="alert alert-warning alert-dismissible fade show">
    <strong><i class="bi bi-link-45deg me-1"></i>Enlace de recuperación:</strong>
    <div class="mt-2 d-flex gap-2 align-items-center">
        <input type="text" class="form-control form-control-sm font-monospace"
               id="enlaceReset" value="{{ session('enlace_reset') }}" readonly>
        <button class="btn btn-sm btn-outline-dark text-nowrap"
                onclick="navigator.clipboard.writeText(document.getElementById('enlaceReset').value); this.innerHTML='<i class=\'bi bi-check\'></i> Copiado'">
            <i class="bi bi-clipboard"></i> Copiar
        </button>
    </div>
    <small class="text-muted mt-1 d-block">Envía este enlace al usuario. Expira en 60 minutos.</small>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $usuario)
                <tr>
                    <td class="align-middle">
                        <i class="bi bi-person-circle me-1 text-muted"></i>
                        {{ $usuario->name }}
                        @if($usuario->id === auth()->id())
                            <span class="badge bg-secondary ms-1">Tú</span>
                        @endif
                    </td>
                    <td class="align-middle text-muted">{{ $usuario->email }}</td>
                    <td class="align-middle">
                        @if($usuario->isAdmin())
                            <span class="badge bg-dark">
                                <i class="bi bi-shield-check me-1"></i>Administrador
                            </span>
                        @elseif($usuario->isGerente())
                            <span class="badge bg-secondary">
                                <i class="bi bi-person me-1"></i>Gerente
                            </span>
                        @else
                            <span class="badge bg-secondary">
                                <i class="bi bi-pencil-square me-1"></i>Capturista
                            </span>
                        @endif
                    </td>
                    <td class="align-middle text-end">
                        <div class="d-flex gap-2 justify-content-end">

                            {{-- Generar enlace de reset --}}
                            <form action="{{ route('usuarios.reset-link', $usuario) }}" method="POST"
                                  onsubmit="return confirm('¿Generar enlace de recuperación para {{ $usuario->name }}?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                        title="Generar enlace de recuperación de contraseña">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>

                            <a href="{{ route('usuarios.edit', $usuario) }}"
                               class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>

                            @if($usuario->id !== auth()->id())
                            <form action="{{ route('usuarios.destroy', $usuario) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar al usuario {{ $usuario->name }}? Esta acción no se puede deshacer.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No hay usuarios registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
