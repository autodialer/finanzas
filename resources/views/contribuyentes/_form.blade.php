@include('contribuyentes._errores')

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Datos fiscales</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" required>
                            <option value="moral" @selected(old('tipo', $contribuyente->tipo) === 'moral')>Persona moral</option>
                            <option value="fisica" @selected(old('tipo', $contribuyente->tipo) === 'fisica')>Persona física</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">RFC</label>
                        <input type="text" name="rfc" class="form-control text-uppercase" maxlength="13" required value="{{ old('rfc', $contribuyente->rfc) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nombre o razón social</label>
                        <input type="text" name="nombre" class="form-control" maxlength="255" required value="{{ old('nombre', $contribuyente->nombre) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Régimen fiscal</label>
                        <select name="regimen" class="form-select">
                            <option value="">—</option>
                            @foreach(\App\Models\Contribuyente::REGIMENES as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('regimen', $contribuyente->regimen) === $clave)>{{ $clave }} — {{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Negocios de este RFC</label>
                        <div class="form-text mt-0 mb-2">Los gastos de estos negocios se comparan con las facturas recibidas de este RFC.</div>
                        @php $elegidos = collect(old('negocios', $contribuyente->exists ? $contribuyente->negocios->pluck('id')->all() : []))->map(fn ($id) => (int) $id); @endphp
                        @foreach($negocios as $negocio)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="negocios[]" value="{{ $negocio->id }}" id="negocio{{ $negocio->id }}" @checked($elegidos->contains($negocio->id))>
                                <label class="form-check-label" for="negocio{{ $negocio->id }}">
                                    {{ $negocio->nombre }}
                                    @if($negocio->contribuyente && $negocio->contribuyente->id !== $contribuyente->id)
                                        <span class="small text-muted">(hoy asignado a {{ $negocio->contribuyente->rfc }})</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="descarga_automatica" value="0">
                            <input class="form-check-input" type="checkbox" name="descarga_automatica" value="1" id="descargaAutomatica" @checked(old('descarga_automatica', $contribuyente->descarga_automatica))>
                            <label class="form-check-label" for="descargaAutomatica">Descargar sus facturas del SAT automáticamente todos los días</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-shield-lock me-1"></i>e.firma (FIEL)</div>
            <div class="card-body">
                @if($contribuyente->tieneEfirma())
                    <div class="alert {{ $contribuyente->efirmaVigente() ? 'alert-success' : 'alert-danger' }} small py-2">
                        Cargada · serie {{ $contribuyente->efirma_serie }} · vence {{ $contribuyente->efirma_vence?->format('d/m/Y') }}.
                        Sube archivos nuevos solo si quieres reemplazarla.
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label">Certificado (.cer)</label>
                    <input type="file" name="cer" class="form-control" accept=".cer">
                </div>
                <div class="mb-3">
                    <label class="form-label">Llave privada (.key)</label>
                    <input type="file" name="key" class="form-control" accept=".key">
                </div>
                <div class="mb-2">
                    <label class="form-label">Contraseña de la llave</label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password">
                </div>
                <div class="form-text">
                    Se valida que sea una e.firma (no un sello digital), que esté vigente y que sea de este RFC.
                    Se guarda cifrada en tu servidor y solo se usa para descargar facturas del SAT.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
    <a href="{{ route('contribuyentes.index') }}" class="btn btn-secondary">Cancelar</a>
</div>
