<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Lee el estado de cuenta en PDF de Banca Afirme (Cuenta Visión).
 *
 * Al extraer el texto del PDF se pierden las columnas Depósitos/Retiros: cada
 * movimiento trae solo su monto y el saldo resultante. La naturaleza (depósito
 * o retiro) se deduce comparando el saldo con el del movimiento anterior, lo
 * que de paso valida la cadena completa de saldos.
 */
class AfirmeEstadoCuentaParser
{
    private const MESES = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];

    // Ancho fijo de las líneas de descripción en el PDF
    private const ANCHO_LINEA = 30;

    public function parsearPdf(string $ruta): array
    {
        return $this->parsearTexto($this->extraerTexto($ruta));
    }

    public function extraerTexto(string $ruta): string
    {
        $pdf = (new Parser())->parseFile($ruta);

        $texto = '';
        foreach ($pdf->getPages() as $pagina) {
            $texto .= $pagina->getText() . "\n";
        }
        return $texto;
    }

    /**
     * Regresa el resumen del estado de cuenta, los movimientos y la lista de
     * errores de validación (vacía si todo cuadra).
     */
    public function parsearTexto(string $texto): array
    {
        $errores = [];

        $periodo = $this->leerPeriodo($texto);
        if (!$periodo) {
            $errores[] = 'No se encontró el período del estado de cuenta ("Período de ... AL ...").';
        }

        $resumen = [
            'saldo_inicial' => $this->leerImporte('/Saldo inicial\s+\$\s*([\d,]+\.\d{2})/u', $texto),
            'depositos'     => $this->leerImporte('/^Dep[óo]sitos\s+\$\s*([\d,]+\.\d{2})/mu', $texto),
            'retiros'       => $this->leerImporte('/^Retiros\s+\$\s*([\d,]+\.\d{2})/mu', $texto),
            'saldo_corte'   => $this->leerImporte('/Saldo al corte\s+\$\s*([\d,]+\.\d{2})/u', $texto),
        ];
        $etiquetas = ['saldo_inicial' => 'Saldo inicial', 'depositos' => 'Depósitos', 'retiros' => 'Retiros', 'saldo_corte' => 'Saldo al corte'];
        foreach ($resumen as $clave => $valor) {
            if ($valor === null) {
                $errores[] = "No se encontró \"{$etiquetas[$clave]}\" en el resumen del estado de cuenta.";
            }
        }

        preg_match('/\(CLABE\):\s*(\d{18})/u', $texto, $m);
        $clabe = $m[1] ?? null;
        preg_match('/N[úu]mero de cuenta\s+(\d+)/u', $texto, $m);
        $numeroCuenta = $m[1] ?? null;

        [$movimientos, $erroresDetalle] = $this->leerMovimientos($texto, $periodo);
        $errores = array_merge($errores, $erroresDetalle);

        if (!$errores) {
            $errores = $this->validar($resumen, $movimientos);
        }

        return [
            'periodo_inicio' => $periodo ? $periodo['inicio']->format('Y-m-d') : null,
            'periodo_fin'    => $periodo ? $periodo['fin']->format('Y-m-d') : null,
            'clabe'          => $clabe,
            'numero_cuenta'  => $numeroCuenta,
            'resumen'        => $resumen,
            'movimientos'    => $movimientos,
            'errores'        => $errores,
        ];
    }

    private function leerPeriodo(string $texto): ?array
    {
        $patron = '/Per[ií]odo de\s+(\d{2})\s+([A-Z]{3})\s+(\d{4})\s+AL\s+(\d{2})\s+([A-Z]{3})\s+(\d{4})/u';
        if (!preg_match($patron, $texto, $m) || !isset(self::MESES[$m[2]], self::MESES[$m[5]])) {
            return null;
        }

        return [
            'inicio' => new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $m[3], self::MESES[$m[2]], $m[1])),
            'fin'    => new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $m[6], self::MESES[$m[5]], $m[4])),
        ];
    }

    private function leerImporte(string $patron, string $texto): ?float
    {
        return preg_match($patron, $texto, $m) ? $this->aNumero($m[1]) : null;
    }

    private function aNumero(string $importe): float
    {
        return round((float) str_replace(',', '', $importe), 2);
    }

    private function leerMovimientos(string $texto, ?array $periodo): array
    {
        $movimientos = [];
        $errores     = [];
        $enDetalle   = false;
        $enEncabezado = false;
        $actual      = null;

        $cerrar = function () use (&$actual, &$movimientos, &$errores, $periodo) {
            if ($actual === null) {
                return;
            }
            if ($actual['monto'] === null) {
                $errores[] = "No se encontró el monto del movimiento del día {$actual['dia']} ({$this->primeraLinea($actual)}).";
            } else {
                $movimientos[] = $this->terminarMovimiento($actual, $periodo, $errores);
            }
            $actual = null;
        };

        foreach (preg_split('/\r\n|\n|\r/', $texto) as $linea) {
            // La sección informativa repite los pagos a créditos: no se toma en cuenta
            if (preg_match('/SECCI[ÓO]N ESPECIAL/u', $linea)) {
                break;
            }

            $esEncabezadoTabla = preg_match('/^D[ií]a\s+Descripci[óo]n/u', $linea);

            if (!$enDetalle) {
                $enDetalle = (bool) $esEncabezadoTabla;
                continue;
            }

            // Encabezado repetido en cada página: se salta hasta los títulos de la tabla
            if (preg_match('/^P[áa]gina\s+\d+\s+de\s+\d+/u', $linea)) {
                $enEncabezado = true;
                continue;
            }
            if ($enEncabezado) {
                $enEncabezado = !$esEncabezadoTabla;
                continue;
            }

            // Inicio de movimiento: "DD<tab>$ <saldo><descripción><tab>[referencia]<tab>[$ monto]"
            if (preg_match('/^(\d{2})\s*\$\s*(-?[\d,]+\.\d{2})(.*)$/u', $linea, $m)) {
                $cerrar();

                $partes = explode("\t", $m[3]);
                $monto  = null;
                $referencia = null;

                if (count($partes) > 1 && preg_match('/^\$\s*([\d,]+\.\d{2})$/', trim(end($partes)), $mm)) {
                    $monto = $this->aNumero($mm[1]);
                    array_pop($partes);
                }
                if (count($partes) > 1) {
                    $referencia = trim(array_pop($partes)) ?: null;
                }

                $actual = [
                    'dia'        => (int) $m[1],
                    'saldo'      => $this->aNumero($m[2]),
                    'lineas'     => [rtrim(implode(' ', $partes))],
                    'referencia' => $referencia,
                    'monto'      => $monto,
                ];
                if ($monto !== null) {
                    $cerrar();
                }
                continue;
            }

            if ($actual === null) {
                continue;
            }

            // El monto de los movimientos de varias líneas viene al final, solo en su línea
            if (preg_match('/^\$\s*([\d,]+\.\d{2})\s*$/', $linea, $m)) {
                $actual['monto'] = $this->aNumero($m[1]);
                $cerrar();
                continue;
            }

            $actual['lineas'][] = $linea;
        }

        $cerrar();

        if (!$enDetalle) {
            $errores[] = 'No se encontró la tabla "DETALLE DE OPERACIONES".';
        } elseif (!$movimientos && !$errores) {
            $errores[] = 'El estado de cuenta no tiene movimientos.';
        }

        return [$movimientos, $errores];
    }

    private function primeraLinea(array $movimiento): string
    {
        return trim($movimiento['lineas'][0]);
    }

    private function terminarMovimiento(array $actual, ?array $periodo, array &$errores): array
    {
        // Las líneas de continuación miden 30 caracteres y cortan palabras a la mitad,
        // así que se pegan sin separador. La primera solo se pega si llegó al ancho completo.
        $primera = $actual['lineas'][0];
        $resto   = implode('', array_slice($actual['lineas'], 1));
        $separador = ($resto !== '' && mb_strlen($primera) !== self::ANCHO_LINEA) ? ' ' : '';
        $descripcion = trim(preg_replace('/\s+/u', ' ', $primera . $separador . $resto));

        $fecha = null;
        if ($periodo) {
            // Si el período abarca dos meses, los días menores al de inicio son del mes final
            $base = $actual['dia'] >= (int) $periodo['inicio']->format('d') ? $periodo['inicio'] : $periodo['fin'];
            if (checkdate((int) $base->format('m'), $actual['dia'], (int) $base->format('Y'))) {
                $fecha = $base->format('Y-m-') . sprintf('%02d', $actual['dia']);
            } else {
                $errores[] = "Día inválido ({$actual['dia']}) en el movimiento \"{$this->primeraLinea($actual)}\".";
            }
        }

        return [
            'fecha'       => $fecha,
            'descripcion' => $descripcion,
            'referencia'  => $actual['referencia'],
            'monto'       => $actual['monto'],
            'saldo'       => $actual['saldo'],
            'naturaleza'  => null, // se define al validar la cadena de saldos
        ];
    }

    private function validar(array $resumen, array &$movimientos): array
    {
        $errores = [];
        $centavos = fn (float $v) => (int) round($v * 100);

        $saldoAnterior = $centavos($resumen['saldo_inicial']);
        $depositos = 0;
        $retiros   = 0;

        foreach ($movimientos as $i => &$mov) {
            $saldo = $centavos($mov['saldo']);
            $monto = $centavos($mov['monto']);
            $diferencia = $saldo - $saldoAnterior;

            if (abs($diferencia) !== $monto) {
                $errores[] = sprintf(
                    'El movimiento #%d (%s, "%s", $%s) no cuadra: el saldo pasa de $%s a $%s.',
                    $i + 1, $mov['fecha'], mb_strimwidth($mov['descripcion'], 0, 40, '…'),
                    number_format($mov['monto'], 2), number_format($saldoAnterior / 100, 2), number_format($mov['saldo'], 2)
                );
            }

            $mov['naturaleza'] = $diferencia > 0 ? 'deposito' : 'retiro';
            $diferencia > 0 ? $depositos += $monto : $retiros += $monto;
            $saldoAnterior = $saldo;
        }
        unset($mov);

        if ($errores) {
            return $errores;
        }

        $formato = fn (int $c) => '$' . number_format($c / 100, 2);

        if ($depositos !== $centavos($resumen['depositos'])) {
            $errores[] = "La suma de depósitos ({$formato($depositos)}) no coincide con el total del estado de cuenta ({$formato($centavos($resumen['depositos']))}).";
        }
        if ($retiros !== $centavos($resumen['retiros'])) {
            $errores[] = "La suma de retiros ({$formato($retiros)}) no coincide con el total del estado de cuenta ({$formato($centavos($resumen['retiros']))}).";
        }

        $calculado = $centavos($resumen['saldo_inicial']) + $depositos - $retiros;
        if ($calculado !== $centavos($resumen['saldo_corte'])) {
            $errores[] = "Saldo inicial + depósitos − retiros = {$formato($calculado)}, pero el saldo al corte es {$formato($centavos($resumen['saldo_corte']))}.";
        }

        return $errores;
    }
}
