<?php

namespace App\Services\Sat;

use App\Models\Cfdi;
use App\Models\Contribuyente;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use ZipArchive;

/**
 * Guarda facturas en la base de datos: el XML queda en storage/app/private/cfdi/{RFC}/{UUID}.xml.
 * Una factura ya importada se actualiza sin perder el gasto al que está vinculada.
 */
class CfdiImportador
{
    public function __construct(private CfdiLector $lector)
    {
    }

    /** @return array{0: Cfdi, 1: bool} la factura y si es nueva */
    public function importar(Contribuyente $contribuyente, string $xml): array
    {
        $datos = $this->lector->leer($xml);

        $direccion = match ($contribuyente->rfc) {
            $datos['rfc_emisor']   => 'emitido',
            $datos['rfc_receptor'] => 'recibido',
            default => throw new InvalidArgumentException("La factura {$datos['uuid']} no es de {$contribuyente->rfc} (emisor {$datos['rfc_emisor']}, receptor {$datos['rfc_receptor']})."),
        };

        $archivo = "cfdi/{$contribuyente->rfc}/{$datos['uuid']}.xml";
        Storage::disk('local')->put($archivo, $xml);

        $campos = array_intersect_key($datos, array_flip([
            'tipo', 'fecha', 'serie_folio', 'rfc_emisor', 'nombre_emisor', 'rfc_receptor', 'nombre_receptor',
            'uso_cfdi', 'metodo_pago', 'forma_pago', 'moneda', 'tipo_cambio', 'subtotal', 'descuento', 'total',
            'iva_trasladado', 'iva_retenido', 'isr_retenido', 'ieps', 'conceptos',
        ]));

        $cfdi = Cfdi::firstOrNew(['contribuyente_id' => $contribuyente->id, 'uuid' => $datos['uuid']]);
        $nueva = !$cfdi->exists;
        $cfdi->fill($campos + ['direccion' => $direccion, 'archivo' => $archivo]);
        if ($nueva) {
            $cfdi->estado = 'vigente';
        }
        $cfdi->save();

        return [$cfdi, $nueva];
    }

    /**
     * Importa un .xml o un .zip con varios XML. Cada factura se asigna al contribuyente
     * registrado cuyo RFC sea el emisor o el receptor (si son dos propios, a los dos).
     *
     * @return array{nuevas: int, actualizadas: int, errores: string[]}
     */
    public function importarArchivo(string $ruta, string $nombreOriginal): array
    {
        $resultado = ['nuevas' => 0, 'actualizadas' => 0, 'errores' => []];

        foreach ($this->xmlsDelArchivo($ruta, $nombreOriginal) as $nombre => $xml) {
            try {
                $datos = $this->lector->leer($xml);
            } catch (InvalidArgumentException $e) {
                $resultado['errores'][] = "{$nombre}: {$e->getMessage()}";
                continue;
            }

            $propios = Contribuyente::whereIn('rfc', [$datos['rfc_emisor'], $datos['rfc_receptor']])->get();
            if ($propios->isEmpty()) {
                $resultado['errores'][] = "{$nombre}: ni el emisor ({$datos['rfc_emisor']}) ni el receptor ({$datos['rfc_receptor']}) están dados de alta como contribuyentes.";
                continue;
            }

            foreach ($propios as $contribuyente) {
                [, $nueva] = $this->importar($contribuyente, $xml);
                $resultado[$nueva ? 'nuevas' : 'actualizadas']++;
            }
        }

        return $resultado;
    }

    /** @return iterable<string, string> nombre => contenido XML */
    private function xmlsDelArchivo(string $ruta, string $nombreOriginal): iterable
    {
        if (!str_ends_with(mb_strtolower($nombreOriginal), '.zip')) {
            yield $nombreOriginal => (string) file_get_contents($ruta);
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new InvalidArgumentException('No se pudo abrir el archivo ZIP.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = $zip->getNameIndex($i);
                if (str_ends_with(mb_strtolower($nombre), '.xml') && !str_starts_with(basename($nombre), '.')) {
                    yield $nombre => (string) $zip->getFromIndex($i);
                }
            }
        } finally {
            $zip->close();
        }
    }
}
