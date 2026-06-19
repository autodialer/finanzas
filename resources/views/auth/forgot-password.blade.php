<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña — Finanzas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="card border-0 p-4">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock fs-1 text-dark"></i>
            <h5 class="fw-bold mt-2 mb-0">Recuperar Contraseña</h5>
            <p class="text-muted small">Sistema de Control Financiero</p>
        </div>

        @if(session('exito'))
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>{{ session('exito') }}
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger py-2">
            {{ $errors->first() }}
        </div>
        @endif

        @if(!session('exito'))
        <p class="text-muted small mb-3">
            Ingresa tu correo y el administrador generará un enlace de recuperación para ti.
        </p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Correo electrónico</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email') }}" required autofocus>
            </div>
            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-send me-1"></i>Enviar Solicitud
            </button>
        </form>
        @endif

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="text-muted small">
                <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
            </a>
        </div>
    </div>
</body>
</html>
