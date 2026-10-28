<?php

namespace App\Services;

/**
 * Lee la sección "Compras y cargos diferidos a meses sin intereses" del estado
 * de cuenta de la tarjeta de crédito Afirme (el texto ya extraído del PDF).
 *
 * Cada renglón trae fecha del cargo del periodo, descripción, monto original,
 * saldo pendiente (después del pago del periodo), pago requerido y número de
 * pago. El total de meses no viene: se deduce de monto original / pago.
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

        return [
            'periodo_inicio'  => $periodo[0],
            'periodo_fin'     => $periodo[1],
            'ultimos_digitos' => $tarjeta ? substr($tarjeta, -4) : null,
            'cargos_meses'    => $cargosMeses,
            'renglones'       => $renglones,
            'errores'         => array_merge($errores, $erroresSeccion),
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
