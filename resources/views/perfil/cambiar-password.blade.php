@extends('layouts.app')

@section('titulo', 'Cambiar Contraseña')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="d-flex align-items-center mb-4">
            <i class="bi bi-shield-lock fs-3 text-primary me-2"></i>
            <div>
                <h4 class="mb-0 fw-bold">Cambiar contraseña</h4>
                <small class="text-muted">{{ auth()->user()->name }} · {{ auth()->user()->email }}</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('perfil.password.update') }}">
                    @csrf @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Contraseña actual</label>
                        <div class="input-group">
                            <input type="password" name="password_actual" id="password_actual"
                                   class="form-control @error('password_actual') is-invalid @enderror"
                                   placeholder="Tu contraseña actual" autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('password_actual', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            @error('password_actual')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   placeholder="Mínimo 8 caracteres" autocomplete="new-password"
                                   oninput="medirFuerza(this.value)">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        {{-- Barra de fuerza --}}
                        <div class="progress mt-2" style="height:5px;">
                            <div id="barra-fuerza" class="progress-bar" style="width:0%; transition: width .3s;"></div>
                        </div>
                        <small id="txt-fuerza" class="text-muted"></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirmar nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" name="password_confirmation" id="password_confirmation"
                                   class="form-control"
                                   placeholder="Repite la nueva contraseña" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('password_confirmation', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i> Actualizar contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function medirFuerza(val) {
    const barra = document.getElementById('barra-fuerza');
    const txt   = document.getElementById('txt-fuerza');
    let puntos  = 0;

    if (val.length >= 8)  puntos++;
    if (val.length >= 12) puntos++;
    if (/[A-Z]/.test(val)) puntos++;
    if (/[0-9]/.test(val)) puntos++;
    if (/[^A-Za-z0-9]/.test(val)) puntos++;

    const niveles = [
        { pct: 0,   color: '',          label: '' },
        { pct: 20,  color: 'bg-danger',  label: 'Muy débil' },
        { pct: 40,  color: 'bg-warning', label: 'Débil' },
        { pct: 60,  color: 'bg-info',    label: 'Regular' },
        { pct: 80,  color: 'bg-primary', label: 'Fuerte' },
        { pct: 100, color: 'bg-success', label: 'Muy fuerte' },
    ];

    const n = niveles[puntos];
    barra.style.width   = n.pct + '%';
    barra.className     = 'progress-bar ' + n.color;
    txt.textContent     = n.label;
}
</script>
@endsection
