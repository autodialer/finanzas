<?php

namespace App\Console\Commands;

use App\Models\Contribuyente;
use App\Models\SatSolicitud;
use App\Services\Sat\DescargaMasiva;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pide al SAT las facturas nuevas de cada contribuyente con e.firma vigente.
 * El periodo va desde la última descarga (3 días antes, porque una factura puede
 * timbrarse días después de su fecha) hasta ahora. La primera vez, desde el mes pasado.
 */
class SatDescargar extends Command
{
    protected $signature = 'sat:descargar
        {--rfc= : Solo este RFC}
        {--desde= : Fecha inicial (AAAA-MM-DD)}
        {--hasta= : Fecha final (AAAA-MM-DD), por omisión ahora}';

    protected $description = 'Presenta solicitudes de Descarga Masiva al SAT (facturas emitidas y recibidas)';

    public function handle(DescargaMasiva $descarga): int
    {
        $contribuyentes = Contribuyente::query()
            ->when($this->option('rfc'), fn ($q, $rfc) => $q->where('rfc', mb_strtoupper($rfc)), fn ($q) => $q->where('descarga_automatica', true))
            ->get()
            ->filter->efirmaVigente();

        if ($contribuyentes->isEmpty()) {
            $this->warn('No hay contribuyentes con e.firma vigente.');
            return self::SUCCESS;
        }

        foreach ($contribuyentes as $contribuyente) {
            // Sin periodo explícito no se encima con una solicitud que el SAT todavía no termina
            if (!$this->option('desde') && $contribuyente->solicitudes()->whereIn('estado', SatSolicitud::ABIERTAS)->exists()) {
                $this->line("{$contribuyente->rfc}: hay solicitudes en proceso, se omite.");
                continue;
            }

            $hasta = $this->option('hasta') ? Carbon::parse($this->option('hasta'))->endOfDay() : now()->subMinute();
            $desde = $this->option('desde')
                ? Carbon::parse($this->option('desde'))->startOfDay()
                : ($contribuyente->descargado_hasta?->copy()->subDays(3) ?? now()->subMonthNoOverflow()->startOfMonth());

            foreach ($descarga->solicitar($contribuyente, $desde, $hasta) as $solicitud) {
                $this->line("{$contribuyente->rfc} {$solicitud->direccion}s {$desde->format('d/m/Y H:i')} – {$hasta->format('d/m/Y H:i')}: {$solicitud->estado} {$solicitud->mensaje}");
            }
        }

        return self::SUCCESS;
    }
}
