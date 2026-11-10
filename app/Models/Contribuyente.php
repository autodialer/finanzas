<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;

class Contribuyente extends Model
{
    public const REGIMENES = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios',
        '606' => 'Arrendamiento',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '621' => 'Incorporación Fiscal',
        '625' => 'Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza (RESICO)',
    ];

    protected $table = 'contribuyentes';
    protected $fillable = ['rfc', 'nombre', 'tipo', 'regimen', 'descarga_automatica'];

    // La e.firma nunca sale en JSON ni en arreglos
    protected $hidden = ['efirma_cer', 'efirma_key', 'efirma_password'];

    protected $casts = [
        'efirma_cer'          => 'encrypted',
        'efirma_key'          => 'encrypted',
        'efirma_password'     => 'encrypted',
        'efirma_vence'        => 'date',
        'descargado_hasta'    => 'datetime',
        'descarga_automatica' => 'boolean',
    ];

    public function negocios()
    {
        return $this->hasMany(Negocio::class);
    }

    // Cuentas asignadas directamente a este RFC
    public function cuentasPropias()
    {
        return $this->hasMany(Cuenta::class);
    }

    /** Cuentas de este RFC: las asignadas a él y las de sus negocios que no tienen otro RFC. */
    public function cuentas()
    {
        $id = $this->id;
        return Cuenta::where(fn ($q) => $q->where('contribuyente_id', $id)
            ->orWhere(fn ($q) => $q->whereNull('contribuyente_id')->whereHas('negocio', fn ($n) => $n->where('contribuyente_id', $id))));
    }

    /** Gastos que se comparan con las facturas de este RFC: los pagados con sus cuentas. */
    public function gastos()
    {
        $id = $this->id;
        return Gasto::whereHas('cuenta', fn ($c) => $c->where('contribuyente_id', $id)
            ->orWhere(fn ($q) => $q->whereNull('contribuyente_id')->whereHas('negocio', fn ($n) => $n->where('contribuyente_id', $id))));
    }

    public function cfdis()
    {
        return $this->hasMany(Cfdi::class);
    }

    public function solicitudes()
    {
        return $this->hasMany(SatSolicitud::class);
    }

    public function setRfcAttribute($valor): void
    {
        $this->attributes['rfc'] = mb_strtoupper(trim((string) $valor));
    }

    public function tieneEfirma(): bool
    {
        return $this->efirma_cer !== null && $this->efirma_key !== null;
    }

    public function efirmaVigente(): bool
    {
        return $this->tieneEfirma() && $this->efirma_vence && $this->efirma_vence->endOfDay()->isFuture();
    }

    /** Guarda la e.firma ya validada (contenido binario de .cer y .key). $serie: número de certificado del SAT (20 dígitos). */
    public function guardarEfirma(string $cer, string $key, string $password, string $serie, \DateTimeInterface $vence): void
    {
        $this->efirma_cer      = base64_encode($cer);
        $this->efirma_key      = base64_encode($key);
        $this->efirma_password = $password;
        $this->efirma_serie    = $serie;
        $this->efirma_vence    = $vence;
        $this->save();
    }

    public function fiel(): Fiel
    {
        if (!$this->tieneEfirma()) {
            throw new \RuntimeException("El contribuyente {$this->rfc} no tiene e.firma cargada.");
        }
        return Fiel::create(base64_decode($this->efirma_cer), base64_decode($this->efirma_key), (string) $this->efirma_password);
    }

    public function nombreRegimen(): ?string
    {
        return $this->regimen ? (self::REGIMENES[$this->regimen] ?? $this->regimen) : null;
    }
}
