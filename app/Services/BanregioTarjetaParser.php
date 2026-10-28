<?php

namespace App\Services;

/**
 * Lee el estado de cuenta en PDF de la tarjeta de crédito Banregio.
 *
 * Toma la sección "Cargos, abonos y compras regulares (no a meses)" del
 * desglose de movimientos y la valida contra los renglones "Total de cargos"
 * y "Total de abonos" de esa misma tabla (el resumen de la primera página sale
 * desordenado al extraer el texto, así que no se usa para validar).
 * Del número de tarjeta solo se conservan los últimos 4 dígitos, y se quita de
 * la descripción de cada movimiento.
 *
 * Regresa la misma estructura que AfirmeTarjetaParser para reutilizar el
 * clasificador, la vista previa y la confirmación.
 */
class BanregioTarjetaParser
{
    private const MESES = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    private const FECHA   = '\d{2}-[A-Za-z]{3,4}-\d{4}';
    private const MONTO   = '([\d,]+\.\d{2})';

    private const SECCION_REGULARES = '/^CARGOS, ABONOS Y COMPRAS REGULARES/u';
    private const SECCION_MSI       = '/^COMPRAS Y CARGOS DIFERIDOS A MESES (SIN|CON) INTERESES/u';
    private const FIN_SECCION       = '/^(Total de cargos|Total de abonos|ATENCI[ÓO]N DE QUEJAS|NOTAS ACLARATORIAS|CARGOS NO RECONOCIDOS|COMPRAS Y CARGOS DIFERIDOS)/u';

    // Encabezados de página y de columnas que se repiten dentro de la sección
    private const RUIDO = [
        '/P[áa]gina\s*\d+/u', '/^N[úu]mero de tarjeta/iu', '/^Tarjeta (titular|adicional)/iu', '/^DESGLOSE DE MOVIMIENTOS/u',
        '/^Notas:/', '/^Ver notas en la secci[óo]n/u', '/^Descripci[óo]n del movimiento/u',
        '/^(Fecha de la|operaci[óo]n|Fecha de|cargo|Monto)$/u',
    ];

    public function parsearPdf(string $ruta): array
    {
        return $this->parsearTexto((new PdfTexto())->extraer($ruta));
    }

    // ¿El texto es de un estado de cuenta de tarjeta de crédito Banregio?
    public static function reconoce(string $texto): bool
    {
        return (bool) preg_match('/banregio/iu', $texto)
            && (bool) preg_match(self::SECCION_REGULARES . 'm', $texto);
    }

    public function parsearTexto(string $texto): array
    {
        $errores = [];

        // "20-jul-2026 al 19-ago-2026" (a veces sale pegado: "20-jul-2026al19-ago-2026")
        $periodo = null;
        if (preg_match('/(' . self::FECHA . ')\s*al\s*(' . self::FECHA . ')/u', $texto, $m)) {
            $periodo = [$this->fecha($m[1]), $this->fecha($m[2])];
        }
        if (!$periodo || in_array(null, $periodo, true)) {
            $errores[] = 'No se encontró el período del estado de cuenta ("... al ...").';
        }

        // Nunca se conserva el número completo de la tarjeta
        $ultimos4 = preg_match('/N[ÚUúu]MERO DE (?:LA )?TARJETA\s*(\d{12,19})/iu', $texto, $m) ? substr($m[1], -4) : null;

        $totales = $this->totales($texto);
        if ($totales === null) {
            $errores[] = 'No se encontraron "Total de cargos" y "Total de abonos" en el desglose de movimientos.';
        }

        if (preg_match(self::SECCION_MSI . 'm', $texto)) {
            $errores[] = 'Este estado de cuenta trae compras a meses; ese formato de Banregio todavía no se puede importar.';
        }

        [$movimientos, $erroresSecciones] = $this->leerMovimientos($texto);
        $errores = array_merge($errores, $erroresSecciones);

        if (!$errores) {
            $errores = $this->validar($totales, $movimientos);
        }

        return [
            'tipo'           => 'tarjeta',
            'periodo_inicio' => $periodo[0] ?? null,
            'periodo_fin'    => $periodo[1] ?? null,
            'ultimos4'       => $ultimos4,
            'resumen'        => $totales ?? [],
            'movimientos'    => $movimientos,
            'msi'            => [],
            'errores'        => $errores,
        ];
    }

    private function fecha(string $texto): ?string
    {
        [$dia, $mes, $anio] = explode('-', $texto);
        $mes = self::MESES[mb_strtolower($mes)] ?? null;
        return $mes && checkdate($mes, (int) $dia, (int) $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    private function aNumero(string $importe): float
    {
        return round((float) str_replace(',', '', $importe), 2);
    }

    /**
     * Mismas llaves que el resumen de Afirme para que la vista previa lo muestre igual.
     * @return ?array{cargos_regulares: float, pagos_abonos: float, cargos_meses: null, pago_no_intereses: null}
     */
    private function totales(string $texto): ?array
    {
        $monto = '\$\s*' . self::MONTO;
        // Formato en columnas: las etiquetas, luego los signos, luego los montos
        $patrones = [
            '/Total de cargos\s*Total de abonos\s*\+\s*-\s*' . $monto . '\s*' . $monto . '/u',
            '/Total de cargos\s*\+?\s*' . $monto . '\s*Total de abonos\s*-?\s*' . $monto . '/u',
        ];
        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $m)) {
                return [
                    'cargos_regulares'  => $this->aNumero($m[1]),
                    'pagos_abonos'      => $this->aNumero($m[2]),
                    'cargos_meses'      => null,
                    'pago_no_intereses' => null,
                ];
            }
        }
        return null;
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
     * Cada movimiento termina en el signo y el monto:
     * "10-ago-2026  Titular #4364… PAGO DOMICILIADO …  -10-ago-2026  $ 513.00".
     * Se acumulan fechas y texto hasta encontrar ese cierre, por si la
     * descripción ocupa varias líneas.
     */
    private function leerMovimientos(string $texto): array
    {
        $movimientos = [];
        $errores = [];
        $enSeccion = false;
        $acumulado = [];

        foreach (preg_split('/\r\n|\n|\r/', $texto) as $linea) {
            $linea = trim($linea);

            if (preg_match(self::SECCION_REGULARES, $linea)) {
                $enSeccion = true;
                $acumulado = [];
                continue;
            }
            if (!$enSeccion) {
                continue;
            }
            if (preg_match(self::FIN_SECCION, $linea)) {
                $enSeccion = false;
                continue;
            }
            if ($linea === '' || $this->esRuido($linea)) {
                continue;
            }

            $acumulado[] = $linea;

            if (!preg_match('/([+-])\s*(?:' . self::FECHA . ')?\s*\$\s*' . self::MONTO . '\s*$/u', $linea, $cierre)) {
                continue;
            }

            $renglon = implode(' ', $acumulado);
            $acumulado = [];

            // Quitar el cierre (signo, fecha de cargo y monto) antes de buscar fechas y descripción
            $renglon = substr($renglon, 0, -strlen($cierre[0]));
            preg_match_all('/' . self::FECHA . '/u', $renglon . ' ' . $cierre[0], $fechas);
            $fechas = $fechas[0];

            $descripcion = preg_replace('/' . self::FECHA . '/u', ' ', $renglon);
            $descripcion = preg_replace('/\b(Titular|Adicional)\s*#\s*\d{12,19}\b/iu', ' ', $descripcion);
            $descripcion = trim(preg_replace('/\s+/u', ' ', $descripcion));

            if (!$fechas) {
                $errores[] = "No se encontró la fecha del movimiento \"{$descripcion}\" por {$cierre[1]}\${$cierre[2]}.";
                continue;
            }

            $movimientos[] = [
                'fecha'       => $this->fecha($fechas[0]),
                'fecha_cargo' => $this->fecha($fechas[1] ?? $fechas[0]),
                'operacion'   => null,
                'comercio'    => $descripcion,
                'descripcion' => $descripcion,
                'naturaleza'  => $cierre[1] === '+' ? 'cargo' : 'abono',
                'monto'       => $this->aNumero($cierre[2]),
            ];
        }

        if ($acumulado) {
            $errores[] = 'Quedaron renglones sin monto en el desglose: "' . implode(' ', $acumulado) . '".';
        }

        return [$movimientos, $errores];
    }

    private function validar(array $totales, array $movimientos): array
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

        if ($cargos !== $centavos($totales['cargos_regulares'])) {
            $errores[] = "La suma de cargos ({$formato($cargos)}) no coincide con \"Total de cargos\" del estado de cuenta ({$formato($centavos($totales['cargos_regulares']))}).";
        }
        if ($abonos !== $centavos($totales['pagos_abonos'])) {
            $errores[] = "La suma de abonos ({$formato($abonos)}) no coincide con \"Total de abonos\" del estado de cuenta ({$formato($centavos($totales['pagos_abonos']))}).";
        }

        return $errores;
    }
}
