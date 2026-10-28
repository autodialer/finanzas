<?php

namespace Tests\Fixtures;

/**
 * Texto ficticio con el formato que smalot/pdfparser extrae del estado de
 * cuenta American Express, incluidos los desórdenes de los saltos de página.
 * Ningún dato corresponde a una tarjeta o persona real.
 *
 * Periodo del 23-dic-2025 al 22-ene-2026 (cruza de año). Titular ···1111 y
 * adicional ···2222. Cuadra: 5,050.00 − 5,050.00 + 28,939.50 = 28,939.50.
 */
class AmexFixture
{
    public const CUENTA_TITULAR   = '3700-000000-01111';
    public const CUENTA_ADICIONAL = '3700-000000-02222';

    public static function texto(array $reemplazos = []): string
    {
        $encabezado = fn (int $n) => [
            'Estado de Cuenta',
            "Página {$n}",
            'Número de Cuenta FechaSiguiente Fecha',
            "Tarjetahabiente\t" . self::CUENTA_TITULAR . ' de Corte de Corte',
            "PERSONA TITULAR FICTICIA\t22-Ene-2026 22-Feb-2026 ",
        ];
        $columnas = "Fecha y Detalle de las operaciones\tImporte en MN.";
        $titular  = ['Nuevos cargos y abonos dePERSONA TITULAR FICTICIA', 'Número de Cuenta' . self::CUENTA_TITULAR];
        $columnasMsi = ['Resumen de Planes de Pagos Diferidos con Intereses y Meses sin Intereses', 'Fecha', 'Original', 'Monto', 'Original',
            'Tasa de', 'interés', 'anual', 'Saldo', 'pendiente', 'Número', 'de', 'Mensualidad', 'Monto', 'total a', 'pagar'];

        $lineas = array_merge(
            $encabezado(1),
            [
                'americanexpress.com.mx',
                'American Express',
                'The Platinum Card',
                "Número de Cuenta:" . self::CUENTA_TITULAR . "\tPERSONA TITULAR FICTICIA",
                'Saldo a Pagar: $28,939.50',
                'Total de Comisiones del periodo: $ 1,000.00',
                'Saldo Anterior', 'Pagos y', 'Créditos', 'Nuevos ', 'Cargos', 'Saldo ', 'al corte',
                '5,050.00- 5,050.00+ 28,939.50= 28,939.50 28,939.50 4,340.93',
                'Período de FacturaciónDel 23 deDiciembre al22 deEnero de2026 Días del periodo:31 días',
            ],
            $encabezado(2),
            [
                $columnas,
                "10 deEneroGRACIAS POR SU PAGO EN LINEA\t3,000.00",
                'CR',
                "10 deEneroGRACIAS POR SU PAGO EN LINEA\t2,000.00",
                'CR',
            ],
            $titular,
            [
                // Fecha partida en dos renglones
                '24 de', 'Diciembre',
                'TIENDA FICTICIA UNO     MONTERREY',
                'RFCAAA010101AA1 /REFABC001',
                '1,000.00',
                // Mensualidades de compras a meses
                '23 de', 'Diciembre',
                'LIBRERIA INVENTADA      MONTERREY',
                'CARGO 05 DE 12',
                'RFCBBB010101BB2 /REFMSI005',
                '250.00',
                '23 de', 'Diciembre',
                'ELECTRONICA FALSA       MEXICO',
                'CARGO 01 DE 03',
                'RFCCCC010101CC3 /REFMSI001',
                '300.00',
                // Último movimiento de la página: monto pegado, su fecha y RFC salen en la página siguiente
                "OXXO PRUEBA             MX\t99.50  ",
            ],
            $encabezado(3),
            [
                $columnas,
                '28 de', 'Diciembre',
                'RFCDDD010101DD4 /REFOXXO01',
                '2 deEneroREST LA PRUEBA         SAN PEDRO GARZA',
                'RFCEEE010101EE5 /REFREST01',
                '450.00',
                "Total de las transacciones en $ dePERSONA TITULAR FICTICIA\t2,099.50",
                'Nuevos cargos y abonos dePERSONA ADICIONAL FICTICIA',
                'Número de Cuenta' . self::CUENTA_ADICIONAL,
                '5 deEneroFARMACIA FICTICIA       MONTERREY',
                'RFCFFF010101FF6 /REFFARM01',
                '200.00',
                "6 deEneroDEVOLUCION TIENDA FALSA MONTERREY\t50.00",
                'CR',
                "Total de las transacciones en $ dePERSONA ADICIONAL FICTICIA\t150.00",
            ],
            $titular,
            [
                '30 de', 'Diciembre',
                'SERVICIO NUBE FALSO     SAN FRANCISCO',
                'Dólar U.S.A.         10.00',
                '180.00',
                '3 deEneroHOTEL FICTICIO MADRID  MADRID',
                'Euro      1.234,56',
                'RFCA00000000    /REF00009',
                '25,000.00',
                '4 deEneroTIENDA JAPON FALSA     TOKIO',
                'Yen Japonés         2,500',
                '300.00',
                "Total de Cargos en Moneda Extranjera dePERSONA TITULAR FICTICIA\t25,480.00",
                'Total de Cargos paraPERSONA TITULAR FICTICIA',
                "22 deEneroCUOTA ANUAL MENSUALIDAD 01 DE 03\t1,000.00",
                "22 deEneroIVA APLICABLE\t160.00",
                "Total de las transacciones y comisiones\t1,000.00",
                "Total de IVA sobre transacciones y comisiones\t160.00  ",
            ],
            $encabezado(4),
            $columnasMsi,
            [
                'LIBRERIA INVENTADA      MONTERREY',
                'Mensualidad=(Pago a capital + Interés + IVA)',
                '250.00=250.00+0.00+0.00',
                '10 deAgo 3,000.000.00%1,750.00 5 de12 250.00',
                'ELECTRONICA FALSA       MEXICO',
                'Mensualidad=(Pago a capital + Interés + IVA)',
                '300.00=300.00+0.00+0.00',
                '20 deDic 900.000.00% 600.00 1 de3 300.00',
                'Totales de Planes de Pagos Diferidos con Intereses y',
                'Meses sin Intereses',
                "2,350.00\t550.00",
                "Totales de Planes de Pagos Diferidos y Meses sin Intereses\t2,350.00\t550.00",
            ],
            $encabezado(5),
            [
                'Su Estado de Cuenta puede contener alguna de las siguientes abreviaciones:',
                "CR\tCrédito",
            ]
        );

        return strtr(implode("\n", $lineas), $reemplazos);
    }

    /**
     * Igual que texto(), más un plan de pagos diferidos con comisión: su sección
     * de cargos (capital 1,000.00 "CARGO 02 DE06" + comisión 30.00 + IVA 4.80) y
     * su tabla de planes. Cuadra: 5,050.00 − 5,050.00 + 29,974.30 = 29,974.30;
     * planes: 2,350.00 + 4,000.00 = 6,350.00 y 550.00 + 1,034.80 = 1,584.80.
     */
    public static function conPlanComision(array $reemplazos = []): string
    {
        $seccionPlan = implode("\n", [
            'Descripción de cargos de Planes de Pagos Diferidos con Comisión',
            '22 deEneroPLAN DE PAGOS DIFERIDOS',
            'CARGO 02 DE06',
            '1,000.00',
            "22 deEneroCOMISIÓN POR PLAN DE PAGOS DIFERIDOS\t30.00",
            "22 deEneroIVA APLICABLE\t4.80",
            "Total de cargos de Planes de Pagos Diferidos con Comisión\t1,034.80  ",
        ]);
        $tablaPlan = implode("\n", [
            'Resumen de Planes de Pagos Diferidos con Comisión',
            'Fecha', 'Original', 'Monto', 'Diferido', 'Comisión', 'Mensual*', 'Saldo', 'Pendiente', 'Número', 'de', 'Mensualidad', 'Monto', 'total a', 'pagar',
            'PLAN DE PAGOS DIFERIDOS',
            'Mensualidad=(Monto Diferido + Comisión +',
            'IVA)',
            '1,034.80=1,000.00+30.00+4.80',
            '10 deNov 6,000.00 0.50%4,000.00 2 de61,034.80',
            "Totales de Planes de Pagos Diferidos con Comisión\t4,000.00\t1,034.80",
            '*Comisión equivalente al importe que resulte de aplicar el porcentaje señalado sobre el Monto Diferido.',
        ]);

        $texto = strtr(self::texto(), [
            '5,050.00- 5,050.00+ 28,939.50= 28,939.50 28,939.50' => '5,050.00- 5,050.00+ 29,974.30= 29,974.30 29,974.30',
            "Total de IVA sobre transacciones y comisiones\t160.00  " => "Total de IVA sobre transacciones y comisiones\t160.00  \n" . $seccionPlan,
            "Totales de Planes de Pagos Diferidos y Meses sin Intereses\t2,350.00\t550.00" => $tablaPlan . "\nTotales de Planes de Pagos Diferidos y Meses sin Intereses\t6,350.00\t1,584.80",
        ]);

        return strtr($texto, $reemplazos);
    }
}
