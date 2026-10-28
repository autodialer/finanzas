<?php

namespace App\Services;

/**
 * Lee el estado de cuenta en PDF de la tarjeta de crédito Afirme.
 *
 * Toma dos secciones del "Desglose de movimientos": los cargos y abonos
 * regulares (no a meses) y las compras diferidas a meses sin intereses. El
 * resto del documento (tablas informativas, notas, glosario) se ignora.
 * Del número de tarjeta solo se conservan los últimos 4 dígitos.
 */
class AfirmeTarjetaParser
{
    private const MESES = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];

    private const FECHA   = '\d{2}-[A-Z]{3}-\d{4}';
    private const IMPORTE = '\$\s*([\d,]+\.\d{2})';

    private const SECCION_REGULARES = '/^CARGOS, ABONOS Y COMPRAS REGULARES/u';
    private const SECCION_MSI       = '/^COMPRAS Y CARGOS DIFERIDOS A MESES SIN INTERESES/u';
    // Cualquier otra sección o tabla que cierra las anteriores
    private const FIN_SECCION = '/^(Total de cargos|Total de abonos|CARGOS NO RECONOCIDOS|COMPRAS Y CARGOS DIFERIDOS A MESES CON INTERESES|NOTAS ACLARATORIAS|ATENCI[ÓO]N DE QUEJAS)/u';

    // Encabezados de página y de columnas que se repiten dentro de las secciones
    private const RUIDO = [
        '/^\d{3}-\d{3}$/', '/^N[úu]mero de Tarjeta/u', '/^Notas:/', '/^P[áa]gina\s+\d+/u', '/^Tarjeta (titular|adicional)/',
        '/^(Fecha|Fecha de|de la|la operación|operación|de|cargo|Descripción|Monto|original|Saldo|pendiente|Pago|requerido|Núm\. de|pago|Tasa de|interés|aplicable)$/u',
        '/^Descripci[óo]n del movimiento/u',
    ];

    public function parsearPdf(string $ruta): array
    {
        return $this->parsearTexto((new PdfTexto())->extraer($ruta));
    }

    // ¿El texto es de un estado de cuenta de tarjeta de crédito Afirme?
    public static function reconoce(string $texto): bool
    {
        return (bool) preg_match('/TARJETA DE CR[ÉE]DITO/u', $texto)
            && (bool) preg_match(self::SECCION_REGULARES . 'm', $texto);
    }

    public function parsearTexto(string $texto): array
    {
        $errores = [];

        $periodo = null;
        $patronPeriodo = '/Per[ií]odo:\s*(' . self::FECHA . ')\s+al\s+(' . self::FECHA . ')/u';
        if (preg_match($patronPeriodo, $texto, $m)) {
            $periodo = [$this->fecha($m[1]), $this->fecha($m[2])];
        }
        if (!$periodo || in_array(null, $periodo, true)) {
            $errores[] = 'No se encontró el período del estado de cuenta ("Período: ... al ...").';
        }

        $resumen = [
            'adeudo_anterior'   => $this->importe('/Adeudo del periodo anterior\s*=\s*' . self::IMPORTE . '/u', $texto),
            'cargos_regulares'  => $this->importe('/Cargos regulares \(no a meses\)\s*\+\s*' . self::IMPORTE . '/u', $texto),
            'cargos_meses'      => $this->importe('/Cargos y compras a meses \(capital\)\s*(?:\d+\s+)?\+\s*' . self::IMPORTE . '/u', $texto),
            'pagos_abonos'      => $this->importe('/Pagos y abonos\s*-\s*' . self::IMPORTE . '/u', $texto),
            'pago_no_intereses' => $this->importe('/PAGO PARA NO GENERAR INTERESES\s*=\s*' . self::IMPORTE . '/u', $texto),
        ];
        if ($resumen['cargos_regulares'] === null) {
            $errores[] = 'No se encontró "Cargos regulares (no a meses)" en el resumen del estado de cuenta.';
        }
        if ($resumen['pagos_abonos'] === null) {
            $errores[] = 'No se encontró "Pagos y abonos" en el resumen del estado de cuenta.';
        }

        // Nunca se conserva el número completo de la tarjeta
        $ultimos4 = preg_match('/N[úu]mero de Tarjeta:\s*(\d{12,19})/u', $texto, $m) ? substr($m[1], -4) : null;

        [$movimientos, $msi, $erroresSecciones] = $this->leerSecciones($texto);
        $errores = array_merge($errores, $erroresSecciones);

        if (!$errores) {
            $errores = $this->validar($resumen, $movimientos);
        }

        return [
            'tipo'           => 'tarjeta',
            'periodo_inicio' => $periodo[0] ?? null,
            'periodo_fin'    => $periodo[1] ?? null,
            'ultimos4'       => $ultimos4,
            'resumen'        => $resumen,
            'movimientos'    => $movimientos,
            'msi'            => $msi,
            'errores'        => $errores,
        ];
    }

    private function fecha(string $texto): ?string
    {
        [$dia, $mes, $anio] = explode('-', $texto);
        $mes = self::MESES[$mes] ?? null;
        return $mes && checkdate($mes, (int) $dia, (int) $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    private function importe(string $patron, string $texto): ?float
    {
        return preg_match($patron, $texto, $m) ? $this->aNumero($m[1]) : null;
    }

    private function aNumero(string $importe): float
    {
        return round((float) str_replace(',', '', $importe), 2);
    }

    private function esRuido(string $linea): bool
    {
        foreach (self::RUIDO as $patron) {
            if (preg_match($patron, $linea)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recorre el texto acumulando fechas y líneas de descripción hasta encontrar
     * el renglón que cierra cada movimiento (el monto). Así no importa si las
     * fechas salen antes, pegadas al texto o en su propia línea.
     */
    private function leerSecciones(string $texto): array
    {
        $movimientos = [];
        $msi     = [];
        $errores = [];
        $seccion = null;
        $fechas  = [];
        $descripcion = [];

        $reiniciar = function () use (&$fechas, &$descripcion) {
            $fechas = [];
            $descripcion = [];
        };

        foreach (preg_split('/\r\n|\n|\r/', $texto) as $linea) {
            $linea = trim($linea);

            if (preg_match(self::SECCION_REGULARES, $linea)) {
                $seccion = 'regulares';
                $reiniciar();
                continue;
            }
            if (preg_match(self::SECCION_MSI, $linea)) {
                $seccion = 'msi';
                $reiniciar();
                continue;
            }
            if ($seccion === null) {
                continue;
            }
            if (preg_match(self::FIN_SECCION, $linea)) {
                $seccion = null;
                continue;
            }
            if ($linea === '' || $this->esRuido($linea)) {
                continue;
            }

            if ($seccion === 'msi') {
                $patron = '/' . self::IMPORTE . '\s+' . self::IMPORTE . '\s+' . self::IMPORTE . '\s+(\d+)\s+[\d.]+\s*%/u';
                if (preg_match($patron, $linea, $m)) {
                    $msi[] = [
                        'fecha'           => $fechas ? $this->fecha($fechas[0]) : null,
                        'descripcion'     => implode(' ', array_diff($descripcion, ['MESES SIN INTERESES'])),
                        'monto_original'  => $this->aNumero($m[1]),
                        'saldo_pendiente' => $this->aNumero($m[2]),
                        'pago_requerido'  => $this->aNumero($m[3]),
                        'numero_pago'     => (int) $m[4],
                    ];
                    $reiniciar();
                    continue;
                }
            }

            preg_match_all('/' . self::FECHA . '/u', $linea, $encontradas);
            $fechas = array_merge($fechas, $encontradas[0]);
            $resto  = trim(preg_replace('/' . self::FECHA . '/u', ' ', $linea));

            if ($seccion === 'regulares' && preg_match('/([+-])\s*' . self::IMPORTE . '$/u', $resto, $m)) {
                $concepto = trim(substr($resto, 0, -strlen($m[0])));
                if ($concepto !== '') {
                    $descripcion[] = $concepto;
                }

                if (!$fechas) {
                    $errores[] = 'No se encontró la fecha del movimiento "' . implode(' ', $descripcion) . "\" por {$m[1]}\${$m[2]}.";
                } else {
                    $movimientos[] = $this->movimiento($fechas, $descripcion, $m[1], $m[2]);
                }
                $reiniciar();
                continue;
            }

            if ($resto !== '') {
                $descripcion[] = preg_replace('/\s+/u', ' ', $resto);
            }
        }

        return [$movimientos, $msi, $errores];
    }

    private function movimiento(array $fechas, array $descripcion, string $signo, string $monto): array
    {
        // "COMPRA NACIONAL" + comercio; los movimientos de una sola línea (PAGO ONLINE) son su propio comercio
        $operacion = count($descripcion) > 1 ? array_shift($descripcion) : null;
        $comercio  = implode(' ', $descripcion);

        return [
            'fecha'       => $this->fecha($fechas[0]),
            'fecha_cargo' => $this->fecha($fechas[1] ?? $fechas[0]),
            'operacion'   => $operacion,
            'comercio'    => $comercio,
            'descripcion' => trim(($operacion ? "{$operacion} " : '') . $comercio),
            'naturaleza'  => $signo === '+' ? 'cargo' : 'abono',
            'monto'       => $this->aNumero($monto),
        ];
    }

    private function validar(array $resumen, array $movimientos): array
    {
        $errores  = [];
        $centavos = fn (float $v) => (int) round($v * 100);
        $formato  = fn (int $c) => '$' . number_format($c / 100, 2);

        $cargos = 0;
        $abonos = 0;
        foreach ($movimientos as $mov) {
            if ($mov['fecha'] === null) {
                $errores[] = "Fecha inválida en el movimiento \"{$mov['descripcion']}\".";
            }
            $mov['naturaleza'] === 'cargo' ? $cargos += $centavos($mov['monto']) : $abonos += $centavos($mov['monto']);
        }

        if ($cargos !== $centavos($resumen['cargos_regulares'])) {
            $errores[] = "La suma de cargos ({$formato($cargos)}) no coincide con \"Cargos regulares (no a meses)\" del estado de cuenta ({$formato($centavos($resumen['cargos_regulares']))}).";
        }
        if ($abonos !== $centavos($resumen['pagos_abonos'])) {
            $errores[] = "La suma de abonos ({$formato($abonos)}) no coincide con \"Pagos y abonos\" del estado de cuenta ({$formato($centavos($resumen['pagos_abonos']))}).";
        }

        return $errores;
    }
}
