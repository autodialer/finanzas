<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña — Finanzas</title>
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
            <i class="bi bi-key fs-1 text-dark"></i>
            <h5 class="fw-bold mt-2 mb-0">Nueva Contraseña</h5>
            <p class="text-muted small">Sistema de Control Financiero</p>
        </div>

        @if($errors->any())
        <div class="alert alert-danger py-2">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
                <label class="form-label fw-semibold">Correo electrónico</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email', $email) }}" required readonly>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Nueva contraseña</label>
                <input type="password" name="password" class="form-control"
                       required autofocus autocomplete="new-password">
                <div class="form-text">Mínimo 8 caracteres.</div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="form-control"
                       required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-floppy me-1"></i>Guardar Nueva Contraseña
            </button>
        </form>
    </div>
</body>
</html>
