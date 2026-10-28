<?php

namespace Tests\Fixtures;

/**
 * Texto ficticio con el formato que smalot/pdfparser extrae del estado de
 * cuenta de la tarjeta de crédito Afirme. Ningún dato corresponde a una tarjeta real.
 */
class AfirmeTarjetaFixture
{
    public const NUMERO_TARJETA = '4000123412349999';

    public static function texto(array $reemplazos = []): string
    {
        $encabezadoPagina = fn (int $n) => [
            '000-111',
            'Número de Tarjeta: ' . self::NUMERO_TARJETA,
            'Notas: Ver notas en las sección "NOTAS ACLARATORIAS" en este estado de cuenta.',
            "Página   {$n}   de   3",
        ];
        $encabezadoRegulares = [
            'CARGOS, ABONOS Y COMPRAS REGULARES (NO A MESES)',
            'Tarjeta titular ' . self::NUMERO_TARJETA,
            'Fecha', 'de la', 'operación', 'Fecha', 'de', 'cargo',
            "Descripción del movimiento\tMonto",
        ];

        $lineas = array_merge(
            $encabezadoPagina(1),
            [
                'CLIENTE DE PRUEBA',
                'TARJETA DE CREDITO AFIRME',
                'Número de Tarjeta: ' . self::NUMERO_TARJETA,
                "Período:\t10-FEB-2026 al 10-MAR-2026",
                "Fecha de corte:\t10-MAR-2026",
                'RESUMEN DE CARGOS Y ABONOS DEL PERIODO',
                "Adeudo del periodo anterior\t= $1,000.00",
                "Cargos regulares (no a meses)\t+ $1,720.25",
                'Cargos y compras a meses (capital)',
                '7',
                '+ $1,500.00',
                "Pagos y abonos\t- $1,000.00",
                "PAGO PARA NO GENERAR INTERESES\t= $3,220.25",
                // Tabla informativa con el mismo formato que los MSI: no debe tomarse
                "\$0.00\t\$0.00\t\$0.00\t\$0.00\t\$0.00\t0 0.00 %",
                'DESGLOSE DE MOVIMIENTOS',
                'COMPRAS Y CARGOS DIFERIDOS A MESES SIN INTERESES',
                'Tarjeta titular ' . self::NUMERO_TARJETA,
                'Fecha de', 'la operación', 'Descripción', 'Monto', 'original', 'Saldo', ' pendiente', 'Pago', 'requerido', 'Núm. de', 'pago', 'Tasa de', 'interés', 'aplicable',
                '11-FEB-2026',
                'MESES SIN INTERESES',
                'TIENDA ELECTRONICA FICTICIA',
                "\$12,000.00\t\$11,000.00\t\$1,000.00\t1 0.00 %",
                // La fecha es la del cargo del periodo, no la de compra (en el PDF real todas caen en el periodo)
                '11-FEB-2026',
                'MESES SIN INTERESES',
                'LIBRERIA FALSA',
                "\$3,000.00\t\$1,500.00\t\$500.00\t3 0.00 %",
            ],
            $encabezadoRegulares,
            [
                'COMPRA NACIONAL',
                'OXXO CENTRO FICTICIO',
                "11-FEB-2026\t12-FEB-2026\t+ \$50.00",
                'COMPRA NACIONAL',
                'REST LA PRUEBA',
                "12-FEB-2026\t13-FEB-2026\t+ \$450.00",
                "PAGO ONLINE15-FEB-2026\t15-FEB-2026\t- \$1,000.00",
            ],
            $encabezadoPagina(2),
            $encabezadoRegulares,
            [
                'COMPRA NACIONAL',
                'GASOL FICTICIA NORTE',
                '20-FEB-2026',
                "21-FEB-2026\t+ \$800.00",
                'COMPRA NACIONAL',
                'FARMACIA INVENTADA',
                "22-FEB-2026\t23-FEB-2026\t+ \$120.25 ",
                'COMPRA NACIONAL',
                'TIENDA SIN CLAVE',
                "01-MAR-2026\t02-MAR-2026\t+ \$300.00",
                "Total de cargos:\t+\$3,220.25",
                "Total de abonos:\t- \$1,000.00",
                'CARGOS NO RECONOCIDOS',
            ],
            $encabezadoPagina(3),
            [
                'NOTAS ACLARATORIAS',
                '1. Texto de ejemplo que menciona 05-MAR-2026 y un importe + $5.00',
            ]
        );

        return strtr(implode("\n", $lineas), $reemplazos);
    }
}
