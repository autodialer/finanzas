<?php

namespace Tests\Fixtures;

// CFDI 4.0 ficticio para pruebas (RFCs y UUIDs inventados).
class CfdiFixture
{
    public static function xml(array $datos = []): string
    {
        $d = array_merge([
            'uuid'          => 'AAAAAAAA-1111-4111-8111-000000000001',
            'fecha'         => '2026-09-05T10:00:00',
            'tipo'          => 'I',
            'rfc_emisor'    => 'GAS010101AB1',
            'nombre_emisor' => 'GASOLINERA FICTICIA',
            'rfc_receptor'  => 'MAS010101XY2',
            'subtotal'      => '1000.00',
            'iva'           => '160.00',
            'total'         => '1160.00',
            'moneda'        => 'MXN',
            'tipo_cambio'   => null,
            'isr_retenido'  => null,
        ], $datos);

        $tc = $d['tipo_cambio'] ? " TipoCambio=\"{$d['tipo_cambio']}\"" : '';
        $retenciones = $d['isr_retenido']
            ? "<cfdi:Retenciones><cfdi:Retencion Impuesto=\"001\" Importe=\"{$d['isr_retenido']}\"/></cfdi:Retenciones>"
            : '';
        $totalRet = $d['isr_retenido'] ? " TotalImpuestosRetenidos=\"{$d['isr_retenido']}\"" : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    Version="4.0" Serie="A" Folio="123" Fecha="{$d['fecha']}" SubTotal="{$d['subtotal']}" Moneda="{$d['moneda']}"{$tc} Total="{$d['total']}"
    TipoDeComprobante="{$d['tipo']}" MetodoPago="PUE" FormaPago="04" Exportacion="01" LugarExpedicion="64000">
  <cfdi:Emisor Rfc="{$d['rfc_emisor']}" Nombre="{$d['nombre_emisor']}" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="{$d['rfc_receptor']}" Nombre="RECEPTOR FICTICIO" DomicilioFiscalReceptor="64000" RegimenFiscalReceptor="601" UsoCFDI="G03"/>
  <cfdi:Conceptos>
    <cfdi:Concepto ClaveProdServ="15101514" Cantidad="1" ClaveUnidad="LTR" Descripcion="Combustible" ValorUnitario="{$d['subtotal']}" Importe="{$d['subtotal']}" ObjetoImp="02">
      <cfdi:Impuestos><cfdi:Traslados><cfdi:Traslado Base="{$d['subtotal']}" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$d['iva']}"/></cfdi:Traslados></cfdi:Impuestos>
    </cfdi:Concepto>
  </cfdi:Conceptos>
  <cfdi:Impuestos TotalImpuestosTrasladados="{$d['iva']}"{$totalRet}>
    {$retenciones}
    <cfdi:Traslados><cfdi:Traslado Base="{$d['subtotal']}" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$d['iva']}"/></cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital Version="1.1" UUID="{$d['uuid']}" FechaTimbrado="{$d['fecha']}" RfcProvCertif="SAT970701NN3" SelloCFD="x" NoCertificadoSAT="1" SelloSAT="x"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
    }
}
