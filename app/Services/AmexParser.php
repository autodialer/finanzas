<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Lee el estado de cuenta en PDF de American Express.
 *
 * Toma los pagos, los cargos de cada tarjeta (titular y adicionales, en pesos y
 * en moneda extranjera), las comisiones con su IVA y la tabla de meses sin
 * intereses. Las mensualidades ("CARGO 06 DE 12") vienen mezcladas con los
 * cargos: se marcan para conciliarlas con su renglón de la tabla. De cada
 * número de tarjeta solo se conservan los últimos 4 dígitos.
 *
 * Las fechas no traen año; se deduce del periodo de facturación.
 */
class AmexParser
{
    private const MESES = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7,
        'AGOSTO' => 8, 'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
    ];
    private const MESES_CORTOS = [
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];
    private const MONEDAS = ['DÓLAR U.S.A.' => 'USD', 'DOLAR U.S.A.' => 'USD', 'EURO' => 'EUR', 'YEN JAPONÉS' => 'JPY', 'YEN JAPONES' => 'JPY',
        'LIBRA ESTERLINA' => 'GBP', 'DÓLAR CANADIENSE' => 'CAD', 'DOLAR CANADIENSE' => 'CAD', 'FRANCO SUIZO' => 'CHF'];

    private const IMPORTE = '([\d,]+\.\d{2})';
    private const MES = '(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Setiembre|Octubre|Noviembre|Diciembre)';
    // Los nombres de comercio vienen a 23 caracteres, seguidos de la ciudad
    private const ANCHO_COMERCIO = 23;

    private const RUIDO = [
        '/^Estado de Cuenta$/u', '/^P[áa]gina\s+\d+$/u', '/^N[úu]mero de Cuenta FechaSiguiente Fecha/u', '/^Tarjetahabiente\s/u',
        '/\d{2}-[A-Za-z]{3}-\d{4}\s+\d{2}-[A-Za-z]{3}-\d{4}\s*$/u', '/^Fecha y Detalle de las operaciones/u',
    ];
    private const RUIDO_MSI = '/^(Fecha|Original|Monto|Tasa de|inter[ée]s|anual|Saldo|pendiente|N[úu]mero|de|Mensualidad|total a|pagar|Resumen de Planes de Pagos Diferidos.*)$/u';

    private int $anioCorte;
    private int $mesCorte;

    public function parsearPdf(string $ruta): array
    {
        return $this->parsearTexto((new PdfTexto())->extraer($ruta));
    }

    // ¿El texto es de un estado de cuenta American Express?
    public static function reconoce(string $texto): bool
    {
        return str_contains($texto, 'American Express')
            && (bool) preg_match('/^Nuevos cargos y abonos de/mu', $texto);
    }

    public function parsearTexto(string $texto): array
    {
        $errores = [];

        $periodo = $this->periodo($texto);
        if (!$periodo) {
            return $this->resultado(null, null, [], [], [], ['No se encontró el período de facturación ("Del ... al ... de ...").']);
        }
        [$this->anioCorte, $this->mesCorte] = [(int) substr($periodo[1], 0, 4), (int) substr($periodo[1], 5, 2)];

        $resumen = ['saldo_anterior' => null, 'pagos_abonos' => null, 'nuevos_cargos' => null, 'saldo_corte' => null];
        $patron = '/' . self::IMPORTE . '\s*-\s*' . self::IMPORTE . '\s*\+\s*' . self::IMPORTE . '\s*=\s*' . self::IMPORTE . '/u';
        if (preg_match($patron, $texto, $m)) {
            $resumen = array_combine(array_keys($resumen), array_map(fn ($v) => $this->aNumero($v), array_slice($m, 1, 4)));
        } else {
            $errores[] = 'No se encontró el resumen del estado de cuenta (saldo anterior, pagos y créditos, nuevos cargos y saldo al corte).';
        }
        $resumen['comisiones'] = $this->importe('/Total de Comisiones del periodo:\s*\$\s*' . self::IMPORTE . '/u', $texto);
        if (preg_match('/^Totales de Planes de Pagos Diferidos y Meses sin Intereses\s+' . self::IMPORTE . '\s+' . self::IMPORTE . '/mu', $texto, $m)) {
            $resumen['msi_saldo']        = $this->aNumero($m[1]);
            $resumen['msi_mensualidades'] = $this->aNumero($m[2]);
        } else {
            $resumen['msi_saldo'] = $resumen['msi_mensualidades'] = null;
        }

        // Nunca se conserva el número completo de la tarjeta
        $ultimos4 = preg_match('/N[úu]mero de Cuenta:\s*([\d-]{15,})/u', $texto, $m) ? substr(preg_replace('/\D/', '', $m[1]), -4) : null;

        [$movimientos, $errores2] = $this->leerMovimientos($texto, $ultimos4);
        [$msi, $errores3] = $this->leerMesesSinIntereses($texto);
        $errores = array_merge($errores, $errores2, $errores3);

        if (!$errores) {
            $errores = $this->validar($resumen, $movimientos, $msi);
        }
        if (!$errores) {
            [$msi, $errores] = $this->emparejarMensualidades($movimientos, $msi);
        }

        return $this->resultado($periodo, $ultimos4, $resumen, $movimientos, $msi, $errores);
    }

    private function resultado(?array $periodo, ?string $ultimos4, array $resumen, array $movimientos, array $msi, array $errores): array
    {
        return [
            'tipo'           => 'tarjeta',
            'banco'          => 'amex',
            'periodo_inicio' => $periodo[0] ?? null,
            'periodo_fin'    => $periodo[1] ?? null,
            'ultimos4'       => $ultimos4,
            'tarjetas'       => array_values(array_unique(array_filter(array_column($movimientos, 'tarjeta')))),
            'resumen'        => $resumen,
            'movimientos'    => $movimientos,
            'msi'            => $msi,
            'errores'        => $errores,
        ];
    }

    private function periodo(string $texto): ?array
    {
        $patron = '/Del\s*(\d{1,2})\s*de\s*' . self::MES . '\s*al\s*(\d{1,2})\s*de\s*' . self::MES . '\s*de\s*(\d{4})/iu';
        if (!preg_match($patron, $texto, $m)) {
            return null;
        }
        $mesInicio = self::MESES[mb_strtoupper($m[2])];
        $mesFin    = self::MESES[mb_strtoupper($m[4])];
        $anioFin   = (int) $m[5];
        $anioInicio = $mesInicio > $mesFin ? $anioFin - 1 : $anioFin;

        if (!checkdate($mesInicio, (int) $m[1], $anioInicio) || !checkdate($mesFin, (int) $m[3], $anioFin)) {
            return null;
        }
        return [sprintf('%04d-%02d-%02d', $anioInicio, $mesInicio, $m[1]), sprintf('%04d-%02d-%02d', $anioFin, $mesFin, $m[3])];
    }

    // Un día y mes sin año: es del año del corte, salvo que el mes sea posterior al del corte
    private function fecha(int $dia, int $mes): ?string
    {
        $anio = $mes > $this->mesCorte ? $this->anioCorte - 1 : $this->anioCorte;
        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    private function importe(string $patron, string $texto): ?float
    {
        return preg_match($patron, $texto, $m) ? $this->aNumero($m[1]) : null;
    }

    private function aNumero(string $importe): float
    {
        return round((float) str_replace(',', '', $importe), 2);
    }

    // Monto en moneda extranjera: cada moneda usa su formato ("2,000.00", "1.669,72", "100,15", "1,821")
    public static function montoMoneda(string $texto): float
    {
        if (preg_match('/^(.*)[.,](\d{2})$/', $texto, $m)) {
            return (float) (preg_replace('/\D/', '', $m[1]) . '.' . $m[2]);
        }
        return (float) preg_replace('/\D/', '', $texto);
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
     * Recorre las secciones de movimientos: pagos (antes de la primera tarjeta),
     * "Nuevos cargos y abonos de ..." de cada tarjeta y "Total de Cargos para ..."
     * (comisiones e IVA). Un movimiento acumula fecha, descripción, mensualidad,
     * moneda y referencia hasta su monto. En los saltos de página la fecha o la
     * referencia del último movimiento pueden salir al inicio de la página
     * siguiente: si un bloque no tiene descripción se completa con él el
     * movimiento anterior.
     */
    private function leerMovimientos(string $texto, ?string $titular): array
    {
        $movimientos = [];
        $errores     = [];
        $seccion     = null; // pagos, tarjeta, comisiones
        $tarjeta     = $titular;
        $inicioSeccion = 0;
        $actual = $this->nuevoBloque();
        $lineas = preg_split('/\r\n|\n|\r/', $texto);

        $completarAnterior = function () use (&$actual, &$movimientos) {
            $ultimo = array_key_last($movimientos);
            if ($ultimo !== null && !$actual['descripcion']) {
                $movimientos[$ultimo]['fecha'] ??= $actual['fecha'];
                $movimientos[$ultimo]['fecha_cargo'] ??= $actual['fecha'];
                $movimientos[$ultimo]['referencia'] ??= $actual['referencia'];
                if ($actual['rfc'] && !str_contains($movimientos[$ultimo]['detalle'], $actual['rfc'])) {
                    $movimientos[$ultimo]['detalle'] .= " {$actual['rfc']}";
                }
                // "CARGO 03 DE 06" o la moneda extranjera también pueden quedar al inicio de la página siguiente
                if ($actual['mensualidad'] && $movimientos[$ultimo]['clase'] === 'compra') {
                    $movimientos[$ultimo]['mensualidad'] = $actual['mensualidad'];
                    $movimientos[$ultimo]['clase'] = 'mensualidad';
                    $movimientos[$ultimo]['detalle'] .= sprintf(' · CARGO %02d DE %02d', $actual['mensualidad']['numero'], $actual['mensualidad']['total']);
                }
                if ($actual['moneda'] && $movimientos[$ultimo]['moneda'] === null) {
                    $movimientos[$ultimo]['moneda'] = $actual['moneda'];
                    $movimientos[$ultimo]['monto_moneda'] = $actual['monto_moneda'];
                    $movimientos[$ultimo]['detalle'] .= " · {$actual['moneda_texto']}";
                }
            }
            $actual = $this->nuevoBloque();
        };

        $saltar = false;
        foreach ($lineas as $n => $linea) {
            $linea = trim($linea);
            if ($saltar) {
                $saltar = false;
                continue;
            }

            if ($seccion === null && preg_match('/^Fecha y Detalle de las operaciones/u', $linea)) {
                $seccion = 'pagos';
                continue;
            }
            if (preg_match('/^Resumen de Planes de Pagos Diferidos/u', $linea)) {
                break;
            }
            if ($seccion === null || $linea === '' || $this->esRuido($linea)) {
                continue;
            }

            if (preg_match('/^Nuevos cargos y abonos de/u', $linea)) {
                $completarAnterior();
                $seccion = 'tarjeta';
                $inicioSeccion = count($movimientos);
                $tarjeta = null;
                continue;
            }
            if (preg_match('/^Total de Cargos para/u', $linea)) {
                $completarAnterior();
                $seccion = 'comisiones';
                $inicioSeccion = count($movimientos);
                $tarjeta = $titular;
                continue;
            }
            if (preg_match('/^N[úu]mero de Cuenta:?\s*([\d-]{15,})/u', $linea, $m)) {
                $tarjeta = substr(preg_replace('/\D/', '', $m[1]), -4);
                continue;
            }

            // Totales que cierran cada sección: se comparan con sus movimientos
            if (preg_match('/^(Total de las transacciones en \$|Total de Cargos en Moneda Extranjera|Total de las transacciones y comisiones|Total de IVA sobre transacciones y comisiones)\D*?' . self::IMPORTE . '\s*$/u', $linea, $m)) {
                $completarAnterior();
                $esIva = str_starts_with($m[1], 'Total de IVA');
                $deSeccion = array_filter(array_slice($movimientos, $inicioSeccion), fn ($mov) => $seccion !== 'comisiones' || ($mov['clase'] === 'iva') === $esIva);
                $suma = array_sum(array_map(fn ($mov) => (int) round($mov['monto'] * 100) * ($mov['naturaleza'] === 'cargo' ? 1 : -1), $deSeccion));
                if ($suma !== (int) round($this->aNumero($m[2]) * 100)) {
                    $errores[] = "La suma de los movimientos ({$this->formato($suma)}) no coincide con \"{$m[1]}\""
                        . ($tarjeta ? " de la tarjeta ···{$tarjeta}" : '') . " del estado de cuenta (\${$m[2]}).";
                }
                if ($seccion === 'tarjeta') {
                    $seccion = 'fuera';
                }
                continue;
            }
            if ($seccion === 'fuera') {
                continue;
            }

            if ($linea === 'CR') {
                $ultimo = array_key_last($movimientos);
                if ($ultimo !== null) {
                    $movimientos[$ultimo]['naturaleza'] = 'abono';
                    $movimientos[$ultimo]['clase'] = str_contains($movimientos[$ultimo]['descripcion'], 'GRACIAS POR SU PAGO') ? 'pago' : 'abono';
                }
                continue;
            }

            // Fecha partida en dos renglones ("23 de" / "Septiembre") o pegada a la descripción ("1 deOctubreOXXO...")
            $resto = $linea;
            $fecha = null;
            if (preg_match('/^(\d{1,2}) de$/u', $linea, $m) && preg_match('/^' . self::MES . '$/iu', trim($lineas[$n + 1] ?? ''), $m2)) {
                $fecha = $this->fecha((int) $m[1], self::MESES[mb_strtoupper($m2[1])]);
                $saltar = true;
                $resto = '';
            } elseif (preg_match('/^(\d{1,2}) de\s*' . self::MES . '(.*)$/iu', $linea, $m)) {
                $fecha = $this->fecha((int) $m[1], self::MESES[mb_strtoupper($m[2])]);
                $resto = trim($m[3]);
            }
            if ($resto !== $linea) {
                $completarAnterior();
                $actual['fecha'] = $fecha;
                if ($fecha === null) {
                    $errores[] = "Fecha inválida: \"{$linea}\".";
                }
                if ($resto === '') {
                    continue;
                }
            }

            if (preg_match('/^RFC/u', $resto)) {
                $actual['rfc'] = $resto;
                $actual['referencia'] = preg_match('/\/REF(\S+)/u', $resto, $m) ? mb_substr($m[1], 0, 50) : null;
                continue;
            }
            if (preg_match('/^CARGO (\d{1,3}) DE (\d{1,3})$/u', $resto, $m)) {
                $actual['mensualidad'] = ['numero' => (int) $m[1], 'total' => (int) $m[2]];
                continue;
            }
            // Renglón de moneda extranjera; sin descripción previa (salto de página) solo si es una moneda conocida
            if (preg_match('/^([A-Za-zÁÉÍÓÚáéíóúÑñ .]+?)\s{2,}(\d[\d.,]*)$/u', $resto, $m)
                && ($actual['descripcion'] || isset(self::MONEDAS[mb_strtoupper(trim($m[1]))]))) {
                $nombre = mb_strtoupper(trim($m[1]));
                $actual['moneda'] = self::MONEDAS[$nombre] ?? trim($m[1]);
                $actual['monto_moneda'] = self::montoMoneda($m[2]);
                $actual['moneda_texto'] = trim($m[1]) . ' ' . $m[2];
                continue;
            }

            // El monto cierra el movimiento: solo en su renglón o después de un tabulador
            if (preg_match('/^' . self::IMPORTE . '$/u', $resto, $m) || preg_match('/^(.*?)\t\s*' . self::IMPORTE . '$/u', $resto, $m2)) {
                if (isset($m2[0])) {
                    $actual['descripcion'][] = trim($m2[1]);
                    $monto = $m2[2];
                } else {
                    $monto = $m[1];
                }
                $m = $m2 = [];

                if (!$actual['descripcion']) {
                    $errores[] = "Monto sin descripción: {$monto}.";
                    $actual = $this->nuevoBloque();
                    continue;
                }
                $movimientos[] = $this->movimiento($actual, $monto, $seccion, $tarjeta);
                $actual = $this->nuevoBloque();
                continue;
            }

            $actual['descripcion'][] = $resto;
        }
        $completarAnterior();

        foreach ($movimientos as $mov) {
            if ($mov['fecha'] === null) {
                $errores[] = "No se encontró la fecha del movimiento \"{$mov['descripcion']}\" por \${$mov['monto']}.";
            }
        }

        return [$movimientos, $errores];
    }

    private function nuevoBloque(): array
    {
        return ['fecha' => null, 'descripcion' => [], 'rfc' => null, 'referencia' => null, 'mensualidad' => null, 'moneda' => null, 'monto_moneda' => null, 'moneda_texto' => null];
    }

    private function movimiento(array $bloque, string $monto, string $seccion, ?string $tarjeta): array
    {
        $descripcion = preg_replace('/\s+/u', ' ', trim(implode(' ', $bloque['descripcion'])));
        $crudo = implode(' ', $bloque['descripcion']);
        $comercio = mb_strlen($crudo) > self::ANCHO_COMERCIO ? trim(mb_substr($crudo, 0, self::ANCHO_COMERCIO)) : $descripcion;

        $clase = match (true) {
            $seccion === 'comisiones' => preg_match('/^IVA\b/u', $descripcion) ? 'iva' : 'comision',
            $bloque['mensualidad'] !== null => 'mensualidad',
            default => 'compra',
        };

        $detalle = trim(implode(' · ', array_filter([
            $descripcion,
            $bloque['mensualidad'] ? sprintf('CARGO %02d DE %02d', $bloque['mensualidad']['numero'], $bloque['mensualidad']['total']) : null,
            $bloque['moneda_texto'],
            $bloque['rfc'],
            $tarjeta ? "Tarjeta ···{$tarjeta}" : null,
        ])));

        return [
            'fecha'        => $bloque['fecha'],
            'fecha_cargo'  => $bloque['fecha'],
            'operacion'    => null,
            'comercio'     => preg_replace('/\s+/u', ' ', $comercio),
            'descripcion'  => $descripcion,
            'detalle'      => $detalle,
            'naturaleza'   => 'cargo',
            'clase'        => $clase,
            'monto'        => $this->aNumero($monto),
            'referencia'   => $bloque['referencia'],
            'tarjeta'      => $tarjeta,
            'moneda'       => $bloque['moneda'],
            'monto_moneda' => $bloque['monto_moneda'],
            'mensualidad'  => $bloque['mensualidad'],
        ];
    }

    /**
     * Tabla "Resumen de Planes de Pagos Diferidos ... y Meses sin Intereses". Cada
     * compra: descripción (1 o 2 renglones), "Mensualidad=(...)", el desglose
     * "615.51=615.51+0.00+0.00" y el renglón de datos, donde los números salen
     * pegados ("1 deFeb 7,386.100.00%1,846.51 9 de12 615.51"). La mensualidad del
     * desglose permite separar "8 de121,728.67".
     */
    private function leerMesesSinIntereses(string $texto): array
    {
        $msi = [];
        $errores = [];
        $dentro = false;
        $descripcion = [];
        $mensualidad = null;

        foreach (preg_split('/\r\n|\n|\r/', $texto) as $linea) {
            $linea = trim($linea);

            if (preg_match('/^Resumen de Planes de Pagos Diferidos/u', $linea)) {
                $dentro = true;
                continue;
            }
            if (!$dentro) {
                continue;
            }
            if (preg_match('/^Totales de Planes/u', $linea)) {
                break;
            }
            if ($linea === '' || $this->esRuido($linea) || preg_match(self::RUIDO_MSI, $linea) || str_starts_with($linea, 'Mensualidad=')) {
                continue;
            }

            if (preg_match('/^' . self::IMPORTE . '=' . self::IMPORTE . '\+' . self::IMPORTE . '\+' . self::IMPORTE . '$/u', $linea, $m)) {
                $mensualidad = $m;
                continue;
            }

            if ($mensualidad && str_ends_with($linea, $mensualidad[1])) {
                $datos = trim(substr($linea, 0, -strlen($mensualidad[1])));
                $patron = '/^(\d{1,2})\s*de\s*([A-Za-z]{3})[a-z]*\s+' . self::IMPORTE . '([\d.]+)%\s*' . self::IMPORTE . '\s+(\d+)\s*de\s*(\d+)$/u';
                $nombre = preg_replace('/\s+/u', ' ', implode(' ', $descripcion));
                if (!preg_match($patron, $datos, $m)) {
                    $errores[] = "No se pudo leer el renglón de meses sin intereses de \"{$nombre}\".";
                } else {
                    $numero = (int) $m[6];
                    $msi[] = [
                        'descripcion'     => $nombre,
                        'fecha_compra'    => $this->fechaCompra((int) $m[1], self::MESES_CORTOS[mb_strtoupper($m[2])] ?? 0, $numero),
                        'monto_original'  => $this->aNumero($m[3]),
                        'tasa'            => (float) $m[4],
                        'saldo_pendiente' => $this->aNumero($m[5]),
                        'numero_pago'     => $numero,
                        'total_pagos'     => (int) $m[7],
                        'pago_requerido'  => $this->aNumero($mensualidad[1]),
                        'interes'         => $this->aNumero($mensualidad[3]) + $this->aNumero($mensualidad[4]),
                    ];
                    if (end($msi)['fecha_compra'] === null) {
                        $errores[] = "Fecha de compra inválida en la compra a meses \"{$nombre}\".";
                    }
                }
                $descripcion = [];
                $mensualidad = null;
                continue;
            }

            $descripcion[] = $linea;
        }

        return [$msi, $errores];
    }

    /**
     * La fecha original de la compra no trae año: es la más reciente con ese día
     * y mes que no sea posterior al corte menos (número de pago − 1) meses.
     */
    private function fechaCompra(int $dia, int $mes, int $numero): ?string
    {
        if (!$mes) {
            return null;
        }
        $limite = Carbon::create($this->anioCorte, $this->mesCorte, 1)->endOfMonth()->subMonthsNoOverflow(max($numero - 1, 0))->endOfMonth();
        for ($anio = $limite->year; $anio >= $limite->year - 5; $anio--) {
            if (checkdate($mes, $dia, $anio) && Carbon::create($anio, $mes, $dia)->lte($limite)) {
                return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            }
        }
        return null;
    }

    private function formato(int $centavos): string
    {
        return ($centavos < 0 ? '-' : '') . '$' . number_format(abs($centavos) / 100, 2);
    }

    private function validar(array $resumen, array $movimientos, array $msi): array
    {
        $errores  = [];
        $centavos = fn (?float $v) => (int) round(($v ?? 0) * 100);

        $cargos = 0;
        $abonos = 0;
        $mensualidades = 0;
        foreach ($movimientos as $mov) {
            $mov['naturaleza'] === 'cargo' ? $cargos += $centavos($mov['monto']) : $abonos += $centavos($mov['monto']);
            if ($mov['clase'] === 'mensualidad') {
                $mensualidades += $centavos($mov['monto']);
            }
        }

        if ($resumen['pagos_abonos'] !== null && $abonos !== $centavos($resumen['pagos_abonos'])) {
            $errores[] = "La suma de pagos y abonos ({$this->formato($abonos)}) no coincide con \"Pagos y Créditos\" del estado de cuenta ({$this->formato($centavos($resumen['pagos_abonos']))}).";
        }
        if ($resumen['nuevos_cargos'] !== null && $cargos !== $centavos($resumen['nuevos_cargos'])) {
            $errores[] = "La suma de cargos ({$this->formato($cargos)}) no coincide con \"Nuevos Cargos\" del estado de cuenta ({$this->formato($centavos($resumen['nuevos_cargos']))}).";
        }
        if ($resumen['saldo_anterior'] !== null
            && $centavos($resumen['saldo_anterior']) - $centavos($resumen['pagos_abonos']) + $centavos($resumen['nuevos_cargos']) !== $centavos($resumen['saldo_corte'])) {
            $errores[] = 'Saldo anterior − pagos + cargos no da el saldo al corte del estado de cuenta.';
        }

        if ($msi || $mensualidades) {
            $tabla = array_sum(array_map(fn ($r) => $centavos($r['pago_requerido']), $msi));
            $saldos = array_sum(array_map(fn ($r) => $centavos($r['saldo_pendiente']), $msi));
            if ($resumen['msi_mensualidades'] === null) {
                $errores[] = 'No se encontraron los totales de la tabla de meses sin intereses.';
            } else {
                if ($tabla !== $centavos($resumen['msi_mensualidades'])) {
                    $errores[] = "La suma de mensualidades de la tabla de meses sin intereses ({$this->formato($tabla)}) no coincide con su total ({$this->formato($centavos($resumen['msi_mensualidades']))}).";
                }
                if ($saldos !== $centavos($resumen['msi_saldo'])) {
                    $errores[] = "La suma de saldos pendientes de meses sin intereses ({$this->formato($saldos)}) no coincide con su total ({$this->formato($centavos($resumen['msi_saldo']))}).";
                }
            }
            if ($mensualidades !== $tabla) {
                $errores[] = "Las mensualidades cobradas en los movimientos ({$this->formato($mensualidades)}) no coinciden con la tabla de meses sin intereses ({$this->formato($tabla)}).";
            }
        }

        return $errores;
    }

    /**
     * Cada mensualidad cobrada ("CARGO 09 DE 12") se empareja con su renglón de la
     * tabla por número de pago, total de pagos, monto y descripción. El renglón
     * toma la fecha, tarjeta y referencia del cargo.
     */
    private function emparejarMensualidades(array $movimientos, array $msi): array
    {
        $errores = [];
        $libres  = array_keys($msi);

        foreach ($movimientos as $mov) {
            if ($mov['clase'] !== 'mensualidad') {
                continue;
            }
            $candidatos = array_filter($libres, fn ($i) => $msi[$i]['numero_pago'] === $mov['mensualidad']['numero']
                && $msi[$i]['total_pagos'] === $mov['mensualidad']['total']
                && abs($msi[$i]['pago_requerido'] - $mov['monto']) < 0.005);
            $propia = ConciliadorComprasMeses::normalizar($mov['descripcion']);
            $elegido = collect($candidatos)->first(function ($i) use ($msi, $propia) {
                $tabla = ConciliadorComprasMeses::normalizar($msi[$i]['descripcion']);
                return str_contains($tabla, $propia) || str_contains($propia, $tabla);
            }) ?? reset($candidatos);

            if ($elegido === false || $elegido === null) {
                $errores[] = "La mensualidad \"{$mov['descripcion']}\" ({$mov['mensualidad']['numero']} de {$mov['mensualidad']['total']}, \${$mov['monto']}) no aparece en la tabla de meses sin intereses.";
                continue;
            }
            $libres = array_values(array_diff($libres, [$elegido]));
            $msi[$elegido] += [
                'fecha'      => $mov['fecha'],
                'tarjeta'    => $mov['tarjeta'],
                'referencia' => $mov['referencia'],
                'comercio'   => $mov['comercio'],
                'detalle'    => $mov['detalle'],
            ];
        }

        foreach ($libres as $i) {
            $errores[] = "La compra a meses \"{$msi[$i]['descripcion']}\" de la tabla no tiene su mensualidad en los movimientos.";
        }

        return [$msi, $errores];
    }
}
