<?php

namespace App\Services;

/**
 * Lee el estado de cuenta de la tarjeta de crédito Afirme (el texto ya extraído
 * del PDF): la sección "Compras y cargos diferidos a meses sin intereses" y la
 * de "Cargos, abonos y compras regulares (no a meses)".
 *
 * Meses sin intereses:
 * Cada renglón trae fecha del cargo del periodo, descripción, monto original,
 * saldo pendiente (después del pago del periodo), pago requerido y número de
 * pago. El total de meses no viene: se deduce de monto original / pago.
 *
 * Cargos regulares: cada movimiento trae su tipo ("COMPRA NACIONAL", "PAGO
 * ONLINE"...), el comercio, fecha de operación, fecha de cargo y el monto con
 * signo (+ cargo, − abono). Las sumas deben cuadrar con el resumen del periodo.
 */
class AfirmeTarjetaParser
{
    private const MESES = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];

    private const INICIO_SECCION = 'COMPRAS Y CARGOS DIFERIDOS A MESES SIN INTERESES';
    private const FIN_SECCION    = '/^(CARGOS, ABONOS Y COMPRAS REGULARES|COMPRAS Y CARGOS DIFERIDOS A MESES CON INTERESES|NOTAS ACLARATORIAS)/u';
    private const FECHA          = '/^(\d{2})-([A-Z]{3})-(\d{4})$/u';
    private const IMPORTES       = '/^\$\s*([\d,]+\.\d{2})\s+\$\s*([\d,]+\.\d{2})\s+\$\s*([\d,]+\.\d{2})\s+(\d+)\s+([\d.]+)\s*%/u';
    private const INICIO_REGULARES = 'CARGOS, ABONOS Y COMPRAS REGULARES (NO A MESES)';
    private const FIN_REGULARES    = '/^(Total de cargos:|Total de abonos:|CARGOS NO RECONOCIDOS)/u';
    private const MOVIMIENTO       = '/^(.*?)(\d{2}-[A-Z]{3}-\d{4})\s+(\d{2}-[A-Z]{3}-\d{4})\s+([+-])\s*\$\s*([\d,]+\.\d{2})\s*$/u';
    private const RUIDO_REGULARES  = '/^(Fecha|de la|operaci[óo]n|de|cargo|Descripci[óo]n del movimiento.*|CARGOS, ABONOS Y COMPRAS REGULARES.*)$/u';
    // Encabezados de página y de columnas que se repiten si la tabla sigue en otra hoja
    private const RUIDO          = '/^(\d{3}-\d{3}$|N[úu]mero de Tarjeta|Notas:|P[áa]gina\s|Tarjeta (titular|adicional)|MESES SIN INTERESES$)/u';

    public static function esEstadoTarjeta(string $texto): bool
    {
        return (bool) preg_match('/TARJETA DE CR[EÉ]DITO.*AFIRME/u', $texto);
    }

    public function parsearTexto(string $texto): array
    {
        $errores = [];

        $periodo = preg_match('/Per[íi]odo:\s*(\d{2}-[A-Z]{3}-\d{4})\s+al\s+(\d{2}-[A-Z]{3}-\d{4})/u', $texto, $m)
            ? [$this->fecha($m[1]), $this->fecha($m[2])]
            : [null, null];
        if (!$periodo[0] || !$periodo[1]) {
            $errores[] = 'No se encontró el período del estado de cuenta ("Período: ... al ...").';
        }

        preg_match('/N[úu]mero de Tarjeta:\s*(\d{12,19})/u', $texto, $m);
        $tarjeta = $m[1] ?? null;

        // Total de mensualidades del periodo según el resumen (incluye meses con intereses, si hubiera)
        $cargosMeses = preg_match('/Cargos y compras a meses \(capital\)\s*(?:\d+\s+)?\+\s*\$\s*([\d,]+\.\d{2})/u', $texto, $m)
            ? $this->importe($m[1])
            : null;

        [$renglones, $erroresSeccion] = $this->leerSeccion($texto, $periodo);

        $resumen = [
            'cargos_regulares' => $this->leerResumen('Cargos regulares \(no a meses\)', $texto),
            'intereses'        => $this->leerResumen('Monto de intereses', $texto),
            'comisiones'       => $this->leerResumen('Monto de comisiones', $texto),
            'iva'              => $this->leerResumen('IVA de intereses y comisiones', $texto),
            'pagos_abonos'     => $this->leerResumen('Pagos y abonos', $texto),
        ];
        [$movimientos, $erroresMovimientos] = $this->leerMovimientos($texto);
        if (!$erroresMovimientos) {
            $erroresMovimientos = $this->validarMovimientos($resumen, $movimientos);
        }

        return [
            'periodo_inicio'  => $periodo[0],
            'periodo_fin'     => $periodo[1],
            'ultimos_digitos' => $tarjeta ? substr($tarjeta, -4) : null,
            'cargos_meses'    => $cargosMeses,
            'renglones'       => $renglones,
            'resumen'         => $resumen,
            'movimientos'     => $movimientos,
            'errores'         => array_merge($errores, $erroresSeccion, $erroresMovimientos),
        ];
    }

    private function leerSeccion(string $texto, array $periodo): array
    {
        $inicio = mb_strpos($texto, self::INICIO_SECCION);
        if ($inicio === false) {
            return [[], []]; // el estado de cuenta no tiene compras a meses
        }

        $renglones = [];
        $errores   = [];
        $actual    = null;

        $lineas = preg_split('/\R/u', mb_substr($texto, $inicio + mb_strlen(self::INICIO_SECCION)));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (preg_match(self::FIN_SECCION, $linea)) {
                break;
            }

            if (preg_match(self::FECHA, $linea)) {
                if ($actual) {
                    $errores[] = "No se encontraron los importes del renglón del {$actual['fecha_texto']}.";
                }
                $actual = ['fecha_texto' => $linea, 'descripcion' => []];
                continue;
            }

            if (!$actual) {
                continue; // encabezados antes del primer renglón
            }

            if (preg_match(self::IMPORTES, $linea, $m)) {
                $renglones[] = $this->renglon($actual, $m, $periodo);
                $actual = null;
                continue;
            }

            if ($linea !== '' && !preg_match(self::RUIDO, $linea)) {
                $actual['descripcion'][] = $linea;
            }
        }

        if ($actual) {
            $errores[] = "No se encontraron los importes del renglón del {$actual['fecha_texto']}.";
        }

        return [$renglones, $errores];
    }

    private function leerMovimientos(string $texto): array
    {
        $inicio = mb_strpos($texto, self::INICIO_REGULARES);
        if ($inicio === false) {
            return [[], []];
        }

        $movimientos = [];
        $descripcion = [];

        $lineas = preg_split('/\R/u', mb_substr($texto, $inicio + mb_strlen(self::INICIO_REGULARES)));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (preg_match(self::FIN_REGULARES, $linea)) {
                break;
            }
            if ($linea === '' || preg_match(self::RUIDO, $linea) || preg_match(self::RUIDO_REGULARES, $linea)) {
                continue;
            }

            if (!preg_match(self::MOVIMIENTO, $linea, $m)) {
                $descripcion[] = $linea;
                continue;
            }

            // Una descripción de una sola línea viene pegada a la fecha ("PAGO ONLINE16-JUN-2026")
            if (trim($m[1]) !== '') {
                $descripcion[] = trim($m[1]);
            }
            $tipo     = $descripcion[0] ?? '';
            $comercio = count($descripcion) > 1 ? implode(' ', array_slice($descripcion, 1)) : $tipo;

            $movimientos[] = [
                'fecha'        => $this->fecha($m[2]),
                'fecha_cargo'  => $this->fecha($m[3]),
                'naturaleza'   => $m[4] === '+' ? 'cargo' : 'abono',
                'monto'        => $this->importe($m[5]),
                'tipo'         => $tipo,
                'descripcion'  => $comercio,
            ];
            $descripcion = [];
        }

        $errores = $descripcion
            ? ['Quedaron líneas sin movimiento al final de los cargos regulares: ' . implode(' ', $descripcion)]
            : [];

        return [$movimientos, $errores];
    }

    /**
     * Los cargos deben sumar "Cargos regulares" (más intereses, comisiones e IVA si
     * vienen como movimientos) y los abonos "Pagos y abonos". Si no cuadra, algo se
     * leyó mal y no se importa.
     */
    private function validarMovimientos(array $resumen, array $movimientos): array
    {
        $errores = [];
        $etiquetas = ['cargos_regulares' => 'Cargos regulares (no a meses)', 'pagos_abonos' => 'Pagos y abonos'];
        foreach ($etiquetas as $clave => $etiqueta) {
            if ($resumen[$clave] === null) {
                $errores[] = "No se encontró \"{$etiqueta}\" en el resumen del estado de cuenta.";
            }
        }
        if ($errores) {
            return $errores;
        }

        $cargos = round(array_sum(array_column(array_filter($movimientos, fn ($m) => $m['naturaleza'] === 'cargo'), 'monto')), 2);
        $abonos = round(array_sum(array_column(array_filter($movimientos, fn ($m) => $m['naturaleza'] === 'abono'), 'monto')), 2);
        $conCostos = $resumen['cargos_regulares'] + ($resumen['intereses'] ?? 0) + ($resumen['comisiones'] ?? 0) + ($resumen['iva'] ?? 0);

        if (abs($cargos - $resumen['cargos_regulares']) > 0.004 && abs($cargos - $conCostos) > 0.004) {
            $errores[] = 'Los cargos leídos suman $' . number_format($cargos, 2)
                . ' y el resumen indica $' . number_format($resumen['cargos_regulares'], 2) . ' de cargos regulares.';
        }
        if (abs($abonos - $resumen['pagos_abonos']) > 0.004) {
            $errores[] = 'Los abonos leídos suman $' . number_format($abonos, 2)
                . ' y el resumen indica $' . number_format($resumen['pagos_abonos'], 2) . ' de pagos y abonos.';
        }

        return $errores;
    }

    private function leerResumen(string $etiqueta, string $texto): ?float
    {
        // Algunas etiquetas llevan el número de nota aclaratoria antes del importe
        return preg_match('/' . $etiqueta . '\s*(?:\d+\s+)?[+-]\s*\$\s*([\d,]+\.\d{2})/u', $texto, $m)
            ? $this->importe($m[1])
            : null;
    }

    private function renglon(array $actual, array $m, array $periodo): array
    {
        $monto = $this->importe($m[1]);
        $pago  = $this->importe($m[3]);

        return [
            'fecha'           => $this->fecha($actual['fecha_texto']),
            'descripcion'     => implode(' ', $actual['descripcion']),
            'monto_original'  => $monto,
            'saldo_pendiente' => $this->importe($m[2]),
            'mensualidad'     => $pago,
            'numero_pago'     => (int) $m[4],
            'total_pagos'     => $pago > 0 ? (int) round($monto / $pago) : null,
            'tasa'            => (float) $m[5],
            'periodo_inicio'  => $periodo[0],
            'periodo_fin'     => $periodo[1],
        ];
    }

    private function fecha(string $texto): ?string
    {
        if (!preg_match(self::FECHA, $texto, $m) || !isset(self::MESES[$m[2]])) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $m[3], self::MESES[$m[2]], $m[1]);
    }

    private function importe(string $texto): float
    {
        return (float) str_replace(',', '', $texto);
    }
}
