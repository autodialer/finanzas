<?php

namespace App\Console\Commands;

use App\Models\SatSolicitud;
use App\Services\Sat\ConciliadorFacturas;
use App\Services\Sat\DescargaMasiva;
use Illuminate\Console\Command;

/** Revisa las solicitudes abiertas, descarga las terminadas y vincula las facturas nuevas con los gastos. */
class SatVerificar extends Command
{
    protected $signature = 'sat:verificar';
    protected $description = 'Verifica solicitudes de Descarga Masiva pendientes, descarga sus facturas y concilia gastos';

    public function handle(DescargaMasiva $descarga, ConciliadorFacturas $conciliador): int
    {
        $solicitudes = SatSolicitud::with('contribuyente')->whereIn('estado', SatSolicitud::ABIERTAS)->orderBy('id')->get();

        $porConciliar = [];
        foreach ($solicitudes as $solicitud) {
            $descarga->verificar($solicitud);
            $this->line("{$solicitud->contribuyente->rfc} {$solicitud->direccion}s #{$solicitud->id}: {$solicitud->estado} — {$solicitud->mensaje}");
            if ($solicitud->estado === 'terminada' && $solicitud->cfdis_nuevos > 0) {
                $porConciliar[$solicitud->contribuyente_id] = $solicitud->contribuyente;
            }
        }

        foreach ($porConciliar as $contribuyente) {
            $vinculadas = $conciliador->conciliar($contribuyente);
            $this->info("{$contribuyente->rfc}: {$vinculadas} factura(s) vinculadas a gastos.");
        }

        return self::SUCCESS;
    }
}
