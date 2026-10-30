<?php

namespace App\Services\Sat;

use App\Models\Contribuyente;
use App\Models\SatSolicitud;
use Carbon\Carbon;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;
use Throwable;

/**
 * Descarga Masiva del SAT (web service v1.5) con la e.firma del contribuyente.
 *
 * El SAT no responde al momento: se presenta la solicitud, se verifica más tarde (minutos
 * u horas) y, cuando termina, se descargan sus paquetes ZIP. Se piden solo XML vigentes
 * (el SAT ya no entrega XML cancelados de recibidos). Una misma solicitud solo se puede
 * repetir dos veces en la vida, por eso cada periodo se pide una vez y se verifica después.
 */
class DescargaMasiva
{
    /** @var array<int, Service> */
    private array $servicios = [];

    public function __construct(private CfdiImportador $importador)
    {
    }

    /** Presenta solicitudes de emitidos y recibidos para el periodo. */
    public function solicitar(Contribuyente $contribuyente, Carbon $desde, Carbon $hasta): array
    {
        return [
            $this->solicitarDireccion($contribuyente, 'emitido', $desde, $hasta),
            $this->solicitarDireccion($contribuyente, 'recibido', $desde, $hasta),
        ];
    }

    public function solicitarDireccion(Contribuyente $contribuyente, string $direccion, Carbon $desde, Carbon $hasta): SatSolicitud
    {
        $solicitud = SatSolicitud::create([
            'contribuyente_id' => $contribuyente->id,
            'direccion'        => $direccion,
            'desde'            => $desde,
            'hasta'            => $hasta,
        ]);

        try {
            $parametros = QueryParameters::create(DateTimePeriod::createFromValues($desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')))
                ->withDownloadType($direccion === 'emitido' ? DownloadType::issued() : DownloadType::received())
                ->withRequestType(RequestType::xml())
                ->withDocumentStatus(DocumentStatus::active());

            if ($errores = $parametros->validate()) {
                return $this->fallo($solicitud, implode(' ', $errores));
            }

            $resultado = $this->servicio($contribuyente)->query($parametros);
            if (!$resultado->getStatus()->isAccepted()) {
                return $this->fallo($solicitud, $resultado->getStatus()->getMessage());
            }

            $solicitud->update(['request_id' => $resultado->getRequestId(), 'mensaje' => 'Solicitud recibida por el SAT']);
        } catch (Throwable $e) {
            $this->fallo($solicitud, $e->getMessage());
        }

        return $solicitud;
    }

    /** Revisa si el SAT ya terminó; si sí, descarga e importa los paquetes. */
    public function verificar(SatSolicitud $solicitud): SatSolicitud
    {
        if (!$solicitud->abierta() || !$solicitud->request_id) {
            return $solicitud;
        }

        // Las solicitudes y paquetes del SAT vencen a las 72 horas
        if ($solicitud->created_at->lt(now()->subHours(72))) {
            $solicitud->update(['estado' => 'vencida', 'mensaje' => 'El SAT no terminó la solicitud en 72 horas.', 'verificada_at' => now()]);
            return $solicitud;
        }

        $contribuyente = $solicitud->contribuyente;

        try {
            $servicio = $this->servicio($contribuyente);
            $verificacion = $servicio->verify($solicitud->request_id);
            $solicitud->verificada_at = now();

            if (!$verificacion->getStatus()->isAccepted()) {
                $solicitud->update(['mensaje' => $verificacion->getStatus()->getMessage()]);
                return $solicitud; // falla temporal: se reintenta en la siguiente verificación
            }

            $codigo = $verificacion->getCodeRequest();
            if ($codigo->isEmptyResult()) {
                return $this->terminar($solicitud, 'sin_datos', 'No hay facturas en el periodo.', 0);
            }
            if (!$codigo->isAccepted()) {
                return $this->fallo($solicitud, $codigo->getMessage());
            }

            $estado = $verificacion->getStatusRequest();
            if ($estado->isAccepted() || $estado->isInProgress()) {
                $solicitud->update(['estado' => 'en_proceso', 'mensaje' => 'El SAT está generando los paquetes.']);
                return $solicitud;
            }
            if (!$estado->isFinished()) {
                return $this->fallo($solicitud, 'El SAT reportó la solicitud como: ' . $estado->getMessage());
            }

            $paquetes = $verificacion->getPackagesIds();
            $nuevas = 0;
            foreach ($paquetes as $paquete) {
                $descarga = $servicio->download($paquete);
                if (!$descarga->getStatus()->isAccepted()) {
                    return $this->fallo($solicitud, "No se pudo descargar el paquete {$paquete}: " . $descarga->getStatus()->getMessage());
                }
                foreach (CfdiPackageReader::createFromContents($descarga->getPackageContent())->cfdis() as $xml) {
                    [, $nueva] = $this->importador->importar($contribuyente, $xml);
                    $nuevas += (int) $nueva;
                }
            }

            $solicitud->paquetes = $paquetes;
            return $this->terminar($solicitud, 'terminada', count($paquetes) . ' paquete(s) descargados.', $nuevas);
        } catch (Throwable $e) {
            // Error de red o del SAT: se deja abierta para reintentar
            $solicitud->update(['mensaje' => mb_substr('Reintentando: ' . $e->getMessage(), 0, 255), 'verificada_at' => now()]);
            return $solicitud;
        }
    }

    protected function servicio(Contribuyente $contribuyente): Service
    {
        return $this->servicios[$contribuyente->id] ??= new Service(new FielRequestBuilder($contribuyente->fiel()), new GuzzleSieteWebClient());
    }

    private function terminar(SatSolicitud $solicitud, string $estado, string $mensaje, int $nuevas): SatSolicitud
    {
        $solicitud->fill(['estado' => $estado, 'mensaje' => $mensaje, 'cfdis_nuevos' => $nuevas])->save();

        $contribuyente = $solicitud->contribuyente;
        if (!$contribuyente->descargado_hasta || $solicitud->hasta->gt($contribuyente->descargado_hasta)) {
            $contribuyente->forceFill(['descargado_hasta' => $solicitud->hasta])->save();
        }
        return $solicitud;
    }

    private function fallo(SatSolicitud $solicitud, string $mensaje): SatSolicitud
    {
        $solicitud->update(['estado' => 'error', 'mensaje' => mb_substr($mensaje, 0, 255), 'verificada_at' => now()]);
        return $solicitud;
    }
}
