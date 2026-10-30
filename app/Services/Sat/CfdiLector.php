<?php

namespace App\Services\Sat;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Lee los datos fiscales de un XML de CFDI 3.3 o 4.0 (ingreso, egreso, pago, nómina, traslado).
 * Se busca por nombre local para no depender del prefijo del namespace.
 * Los impuestos son los totales del comprobante (nodo Impuestos directo), no los de cada concepto.
 */
class CfdiLector
{
    private const IVA  = '002';
    private const ISR  = '001';
    private const IEPS = '003';

    public function leer(string $xml): array
    {
        $doc = new DOMDocument();
        if (trim($xml) === '' || !@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('El archivo no es un XML válido.');
        }

        $comprobante = $doc->documentElement;
        if ($comprobante->localName !== 'Comprobante') {
            throw new InvalidArgumentException('El XML no es un CFDI (falta el nodo Comprobante).');
        }

        $x = new DOMXPath($doc);
        $hijo = fn (string $nombre, DOMElement $padre): ?DOMElement => $x->query("./*[local-name()='{$nombre}']", $padre)->item(0);

        $emisor   = $hijo('Emisor', $comprobante);
        $receptor = $hijo('Receptor', $comprobante);
        $timbre   = $x->query("./*[local-name()='Complemento']/*[local-name()='TimbreFiscalDigital']", $comprobante)->item(0);

        $uuid = $timbre?->getAttribute('UUID');
        if (!$uuid || !$emisor || !$receptor) {
            throw new InvalidArgumentException('El CFDI no está timbrado o le falta emisor/receptor.');
        }

        [$ivaTrasladado, $ieps] = [0.0, 0.0];
        [$ivaRetenido, $isrRetenido] = [0.0, 0.0];
        if ($impuestos = $hijo('Impuestos', $comprobante)) {
            foreach ($x->query("./*[local-name()='Traslados']/*[local-name()='Traslado']", $impuestos) as $traslado) {
                $importe = (float) $traslado->getAttribute('Importe');
                match ($traslado->getAttribute('Impuesto')) {
                    self::IVA  => $ivaTrasladado += $importe,
                    self::IEPS => $ieps += $importe,
                    default    => null,
                };
            }
            foreach ($x->query("./*[local-name()='Retenciones']/*[local-name()='Retencion']", $impuestos) as $retencion) {
                $importe = (float) $retencion->getAttribute('Importe');
                match ($retencion->getAttribute('Impuesto')) {
                    self::IVA => $ivaRetenido += $importe,
                    self::ISR => $isrRetenido += $importe,
                    default   => null,
                };
            }
        }

        $descripciones = [];
        foreach ($x->query("./*[local-name()='Conceptos']/*[local-name()='Concepto']", $comprobante) as $i => $concepto) {
            if ($i >= 3) {
                $descripciones[] = '…';
                break;
            }
            $descripciones[] = trim($concepto->getAttribute('Descripcion'));
        }

        $attr = fn (DOMElement $el, string $nombre): ?string => $el->hasAttribute($nombre) ? trim($el->getAttribute($nombre)) : null;
        $serieFolio = trim(($attr($comprobante, 'Serie') ?? '') . ' ' . ($attr($comprobante, 'Folio') ?? ''));

        return [
            'uuid'            => mb_strtoupper($uuid),
            'version'         => $attr($comprobante, 'Version'),
            'tipo'            => $attr($comprobante, 'TipoDeComprobante') ?? 'I',
            'fecha'           => str_replace('T', ' ', (string) $attr($comprobante, 'Fecha')),
            'serie_folio'     => $serieFolio !== '' ? mb_substr($serieFolio, 0, 60) : null,
            'rfc_emisor'      => mb_strtoupper((string) $attr($emisor, 'Rfc')),
            'nombre_emisor'   => $attr($emisor, 'Nombre'),
            'rfc_receptor'    => mb_strtoupper((string) $attr($receptor, 'Rfc')),
            'nombre_receptor' => $attr($receptor, 'Nombre'),
            'uso_cfdi'        => $attr($receptor, 'UsoCFDI'),
            'metodo_pago'     => $attr($comprobante, 'MetodoPago'),
            'forma_pago'      => $attr($comprobante, 'FormaPago'),
            'moneda'          => $attr($comprobante, 'Moneda') ?? 'MXN',
            'tipo_cambio'     => (float) ($attr($comprobante, 'TipoCambio') ?? 1) ?: 1.0,
            'subtotal'        => round((float) $attr($comprobante, 'SubTotal'), 2),
            'descuento'       => round((float) ($attr($comprobante, 'Descuento') ?? 0), 2),
            'total'           => round((float) $attr($comprobante, 'Total'), 2),
            'iva_trasladado'  => round($ivaTrasladado, 2),
            'iva_retenido'    => round($ivaRetenido, 2),
            'isr_retenido'    => round($isrRetenido, 2),
            'ieps'            => round($ieps, 2),
            'conceptos'       => $descripciones ? mb_substr(implode(' | ', array_filter($descripciones)), 0, 500) : null,
        ];
    }
}
