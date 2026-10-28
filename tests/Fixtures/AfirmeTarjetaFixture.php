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

    /** Cargos y abonos regulares: [tipo, comercio, fecha operación, fecha cargo, signo, monto] */
    public static function movimientos(): array
    {
        return [
            ['COMPRA NACIONAL', 'RESTAURANTE FICTICIO', '12-MAR-2026', '13-MAR-2026', '+', '450.00'],
            ['COMPRA NACIONAL', 'GASOLINERA FICTICIA', '13-MAR-2026', '14-MAR-2026', '+', '800.00'],
            ['COMPRA NACIONAL', 'GASOLINERA FICTICIA', '13-MAR-2026', '14-MAR-2026', '+', '800.00'],
            ['PAGO ONLINE', null, '15-MAR-2026', '15-MAR-2026', '-', '5,000.00'],
            ['DEVOLUCION', 'TIENDA FICTICIA DOS', '20-MAR-2026', '21-MAR-2026', '-', '200.00'],
        ];
    }

    public static function texto(?array $renglones = null, bool $partirPagina = false, ?array $movimientos = null): string
    {
        $renglones   ??= self::renglones();
        $movimientos ??= self::movimientos();
        $importe = fn (string $v) => (float) str_replace(',', '', $v);
        $total   = array_sum(array_map(fn ($r) => $importe($r[4]), $renglones));
        $cargos  = array_sum(array_map(fn ($m) => $m[4] === '+' ? $importe($m[5]) : 0, $movimientos));
        $abonos  = array_sum(array_map(fn ($m) => $m[4] === '-' ? $importe($m[5]) : 0, $movimientos));

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
            'Adeudo del periodo anterior	= $5,000.00',
            "Cargos regulares (no a meses)\t+ $" . number_format($cargos, 2),
            'Cargos y compras a meses (capital)',
            '7',
            '+ $' . number_format($total, 2),
            'Monto de intereses',
            '8',
            '+ $0.00',
            "Monto de comisiones\t+ $0.00",
            "IVA de intereses y comisiones\t+ $0.00",
            "Pagos y abonos\t- $" . number_format($abonos, 2),
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

        $encabezadoRegulares = ['CARGOS, ABONOS Y COMPRAS REGULARES (NO A MESES)', 'Tarjeta titular ' . self::TARJETA,
            'Fecha', 'de la', 'operación', 'Fecha', 'de', 'cargo', "Descripción del movimiento\tMonto"];
        array_push($lineas, ...$encabezadoRegulares);

        foreach ($movimientos as $n => [$tipo, $comercio, $operacion, $cargo, $signo, $monto]) {
            if ($partirPagina && $n === 2) {
                array_push($lineas, ...[...$encabezadoPagina(3), ...$encabezadoRegulares]);
            }
            $importeLinea = "{$operacion}\t{$cargo}\t{$signo} \${$monto}";
            // Una descripción de una sola línea viene pegada a la fecha, como en el PDF
            $comercio === null
                ? $lineas[] = $tipo . $importeLinea
                : array_push($lineas, $tipo, $comercio, $importeLinea);
        }

        array_push($lineas,
            "Total de cargos:\t+\$" . number_format($cargos + $total, 2),
            "Total de abonos:\t- \$" . number_format($abonos, 2),
            'CARGOS NO RECONOCIDOS',
            'Fecha',
        );

        return implode("\n", $lineas) . "\n";
    }
}
