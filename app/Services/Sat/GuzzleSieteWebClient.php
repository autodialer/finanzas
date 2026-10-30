<?php

namespace App\Services\Sat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Exceptions\WebClientException;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Request;
use PhpCfdi\SatWsDescargaMasiva\WebClient\Response;
use PhpCfdi\SatWsDescargaMasiva\WebClient\WebClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente web para la Descarga Masiva con Guzzle 7 (el que trae Laravel).
 * El GuzzleWebClient de la librería ya requiere Guzzle 8, que Laravel 13 todavía no admite.
 */
class GuzzleSieteWebClient implements WebClientInterface
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        // Las descargas de paquetes pueden tardar: hasta 5 minutos por llamada
        $this->client = $client ?? new Client(['connect_timeout' => 30, 'timeout' => 300]);
    }

    public function fireRequest(Request $request): void
    {
    }

    public function fireResponse(Response $response): void
    {
    }

    public function call(Request $request): Response
    {
        try {
            $respuesta = $this->client->request($request->getMethod(), $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body'    => $request->getBody(),
            ]);
        } catch (GuzzleException $e) {
            $respuesta = $e instanceof RequestException ? $e->getResponse() : null;
            throw new WebClientException("Error al conectar con {$request->getUri()}", $request, $this->convertir($respuesta), $e);
        }

        return $this->convertir($respuesta);
    }

    private function convertir(?ResponseInterface $respuesta): Response
    {
        if ($respuesta === null) {
            return new Response(500, '', []);
        }
        $encabezados = [];
        foreach (array_keys($respuesta->getHeaders()) as $nombre) {
            $encabezados[(string) $nombre] = $respuesta->getHeaderLine((string) $nombre);
        }
        return new Response($respuesta->getStatusCode(), (string) $respuesta->getBody(), $encabezados);
    }
}
