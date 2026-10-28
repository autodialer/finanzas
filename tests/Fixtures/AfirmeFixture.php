<?php

namespace Tests\Fixtures;

/**
 * Texto ficticio con el mismo formato que smalot/pdfparser extrae de un estado
 * de cuenta Afirme Cuenta Visión. Ningún dato corresponde a una cuenta real.
 */
class AfirmeFixture
{
    public static function texto(array $reemplazos = []): string
    {
        $l = fn (string $s) => str_pad($s, 30);      // líneas de continuación de ancho fijo
        $d = fn (string $s) => str_pad($s, 50);      // columna de descripción de la primera línea

        $lineas = [
            'BANCA AFIRME, S. A., INSTITUCIÓN DE BANCA MÚLTIPLE, AFIRME GRUPO FINANCIERO',
            'Página   1   de   2',
            'CLIENTE DE PRUEBA',
            'CUENTA VISIÓN',
            "Número de cuenta  000111222333\tClave Bancaria Estándar\t(CLABE):\t062580000111222333\tMoneda Nacional",
            'INFORMACION GENERAL',
            "Fecha de corte\t31 MAR 2026",
            "Período de\t01 MAR 2026 AL 31 MAR 2026",
            'Saldo inicial	$ 50,000.00',
            'Depósitos	$ 13,006.96',
            'Retiros	$ 12,538.28',
            'Saldo al corte	$ 50,468.68',
            'DETALLE DE OPERACIONES',
            "Día Descripción\tReferencia\tDepósitos\tRetiros\tSaldo",
            "02\t$ 49,000.00" . $d('C/Online Interes  000999888777') . "\t$ 1,000.00",
            "02\t$ 47,000.00" . $d('C/Online Capital  000999888777') . "\t$ 2,000.00",
            "05\t$ 57,000.00" . $d('SPEI RECIBIDO DE 058-BANREGIO') . "\t123456",
            $l('CUENTA:058000000000000001'),
            $l('EMISOR:EMPRESA FICTICIA S.A. D'),
            $l('RFC EMISOR:XAXX010101000'),
            $l('CVE RASTREO:058-05/03/2026/05-'),
            $l('000ABC123'),
            $l('CONCEPTO:Honorarios marzo'),
            $l('HORA:10:00:00'),
            '$ 10,000.00 ',
            "06\t$ 52,000.00" . $d('DOMI-AMERICAN EXPRESS CO MEXIC') . "\t777001",
            $l('O SA DE CV'),
            $l('W00000000000777001'),
            '$ 5,000.00',
            "08\t$ 49,000.00" . $d('ENVIO SPEI (AFIRMENET) A 012-BBVA MEXICO') . "\t8000001",
            $l('CUENTA:012000000000000002'),
            $l('HORA:00:00:00 DESTINATARIO:FIN'),
            $l(' ANCIERA FALSA            (DA'),
            $l('TO NO VERIFICADO POR ESTA INST'),
            $l('ITUCION)  RFC DESTINATARIO:'),
            $l('ND            CVE RASTREO:2603'),
            $l('08000000000000000000000000 CON'),
            $l('CEPTO:123'),
            '$ 3,000.00',
            "08\t$ 48,994.00" . $d('TRANSFERENCIA - ENVIO ; (SPEI;'),
            $l(' BANCA POR INTERNET)'),
            '$ 6.00',
            "08\t$ 48,993.04" . $d('IVA') . "\t$ 0.96 ",
            'Página   2   de   2',
            'CLIENTE DE PRUEBA',
            'DETALLE DE OPERACIONES',
            "Día Descripción\tReferencia\tDepósitos\tRetiros\tSaldo",
            "08\t$ 51,993.04" . $d('260308000000000000000000000000 AFIRMENET') . "\t5000001\t$ 3,000.00",
            "08\t$ 51,999.04" . $d('COMISION') . "\t$ 6.00",
            "08\t$ 52,000.00" . $d('IVA COMISION CARGO ANTERIOR    AFIRMENET') . "\t5000001\t$ 0.96",
            "15\t$ 50,500.00" . $d('ENVIO SPEI (AFIRMENET) A 012-BBVA MEXICO') . "\t8000002",
            $l('CUENTA:012000000000000003'),
            $l('HORA:09:00:00 DESTINATARIO:Pro'),
            $l('veedor Ficticio           (DA'),
            $l('TO NO VERIFICADO POR ESTA INST'),
            $l('ITUCION)  RFC DESTINATARIO:'),
            $l('ND            CVE RASTREO:2603'),
            $l('15000000000000000000000001 CON'),
            $l('CEPTO:Factura 42'),
            '$ 1,500.00',
            "15\t$ 50,498.00" . $d('TRANSFERENCIA - ENVIO ; (SPEI)') . "\t$ 2.00",
            "15\t$ 50,497.68" . $d('IVA') . "\t$ 0.32",
            "31\t$ 50,472.68" . $d('ADMINISTRACION DE ESQUEMA DE S'),
            $l('EGURIDAD; GENERADOR DE CLAVES'),
            $l('DINAMICAS (OTP)'),
            '$ 25.00',
            "31\t$ 50,468.68" . $d('IVA') . "\t$ 4.00",
            'Sus ahorros  están protegidos y garantizados por el  IPAB',
            "SALDO INICIAL\t$ 50,000.00",
            'SECCIÓN ESPECIAL  INFORMATIVA DE PAGOS',
            'DETALLE DE OPERACIONES',
            "Día\tDescripción\tCargos\tAbonos",
            "02 C/Online Interes  000999888777                    \t1,000.00",
            "02 C/Online Capital  000999888777                    \t2,000.00",
        ];

        return strtr(implode("\n", $lineas), $reemplazos);
    }
}
