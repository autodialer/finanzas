<?php

namespace Tests\Fixtures;

/**
 * Texto ficticio con el mismo formato que smalot/pdfparser extrae de un estado
 * de cuenta de la tarjeta de crédito Afirme. Ningún dato corresponde a una
 * tarjeta real.
 */
class AfirmeTarjetaFixture
{
    public const TARJETA = '4000000000009999';

    /** Compras a meses del periodo: [fecha, comercio, original, saldo, pago, núm. de pago] */
    public static function renglones(): array
    {
        return [
            ['15-MAR-2026', 'COMERCIO FICTICIO UNO', '12,000.00', '10,000.00', '1,000.00', 2],
            ['15-MAR-2026', 'TIENDA FICTICIA DOS', '6,000.00', '5,000.00', '1,000.00', 1],
            ['15-MAR-2026', 'SERVICIO FICTICIO TRES', '3,600.00', '1,500.00', '300.00', 7],
        ];
    }

    public static function texto(?array $renglones = null, bool $partirPagina = false): string
    {
        $renglones ??= self::renglones();
        $total = array_sum(array_map(fn ($r) => (float) str_replace(',', '', $r[4]), $renglones));

        $encabezadoPagina = fn (int $n) => [
            '000-000',
            'Número de Tarjeta: ' . self::TARJETA,
            'Notas: Ver notas en las sección "NOTAS ACLARATORIAS" en este estado de cuenta.',
            "Página   {$n}   de   3",
        ];

        $lineas = [
            '=== PÁGINA 1 ===',
            'CLIENTE FICTICIO DE PRUEBA',
            'TARJETA DE CREDITO FICTICIA AFIRME',
            'Número de Tarjeta: ' . self::TARJETA,
            "Período:\t10-MAR-2026 al 10-ABR-2026",
            "Fecha de corte:\t10-ABR-2026",
            'RESUMEN DE CARGOS Y ABONOS DEL PERIODO',
            "Cargos regulares (no a meses)\t+ $1,234.00",
            'Cargos y compras a meses (capital)',
            '7',
            '+ $' . number_format($total, 2),
            ...$encabezadoPagina(2),
            'DESGLOSE DE MOVIMIENTOS',
            'COMPRAS Y CARGOS DIFERIDOS A MESES SIN INTERESES',
            'Tarjeta titular ' . self::TARJETA,
            'Fecha de', 'la operación', 'Descripción', 'Monto', 'original', 'Saldo', ' pendiente',
            'Pago', 'requerido', 'Núm. de', 'pago', 'Tasa de', 'interés', 'aplicable',
        ];

        foreach ($renglones as $n => [$fecha, $comercio, $original, $saldo, $pago, $numero]) {
            if ($partirPagina && $n === 1) {
                // La tabla continúa en otra página con sus encabezados repetidos
                array_push($lineas, ...[...$encabezadoPagina(3), 'COMPRAS Y CARGOS DIFERIDOS A MESES SIN INTERESES', 'Tarjeta titular ' . self::TARJETA]);
            }
            array_push($lineas, $fecha, 'MESES SIN INTERESES', $comercio, "\${$original}\t\${$saldo}\t\${$pago}\t{$numero} 0.00 %");
        }

        array_push($lineas,
            'CARGOS, ABONOS Y COMPRAS REGULARES (NO A MESES)',
            'Tarjeta titular ' . self::TARJETA,
            'COMPRA NACIONAL',
            'RESTAURANTE FICTICIO',
            "12-MAR-2026\t13-MAR-2026\t+ $1,234.00",
        );

        return implode("\n", $lineas) . "\n";
    }
}
