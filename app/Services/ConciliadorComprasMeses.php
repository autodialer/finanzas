<?php

namespace App\Services;

use App\Models\CompraMeses;
use App\Models\Cuenta;
use Carbon\Carbon;

/**
 * Compara un renglón de la sección de meses sin intereses del estado de cuenta
 * de la tarjeta con los planes registrados:
 *  - pago 1           → sugerir dar de alta la compra (gasto + plan)
 *  - pago > 1 con plan → verificar número de pago y saldo pendiente
 *  - pago > 1 sin plan → sugerir el alta con la fecha de compra estimada
 *
 * El renglón trae: descripcion, monto_original, numero_pago, total_pagos,
 * mensualidad (opcional), saldo_pendiente (opcional, después del pago) y fecha
 * (del cargo o del corte).
 */
class ConciliadorComprasMeses
{
    public const CREAR      = 'crear';
    public const VERIFICADA = 'verificada';
    public const DIFERENCIA = 'diferencia';
    public const SIN_PLAN   = 'sin_plan';

    public function __construct(private Cuenta $cuenta)
    {
    }

    public function conciliar(array $renglon): array
    {
        $numero = (int) $renglon['numero_pago'];
        $fecha  = Carbon::parse($renglon['fecha']);

        if ($numero <= 1) {
            return $this->resultado(self::CREAR, $renglon, null, [], $fecha);
        }

        $compra = $this->buscarPlan($renglon['descripcion'], (float) $renglon['monto_original']);
        if (!$compra) {
            return $this->resultado(self::SIN_PLAN, $renglon, null, [], $fecha->copy()->subMonthsNoOverflow($numero - 1));
        }

        return $this->resultado(...$this->verificar($compra, $renglon, $numero, $fecha));
    }

    private function verificar(CompraMeses $compra, array $renglon, int $numero, Carbon $fecha): array
    {
        $avisos = [];

        if (!empty($renglon['total_pagos']) && (int) $renglon['total_pagos'] !== $compra->meses) {
            $avisos[] = "El banco indica {$renglon['total_pagos']} meses y el plan tiene {$compra->meses}.";
        }

        // El cargo número $numero del plan debe caer en el mes del estado de cuenta
        $cargo = $compra->calendario->firstWhere('numero', $numero);
        if (!$cargo) {
            $avisos[] = "El banco indica el pago {$numero} pero el plan solo tiene {$compra->meses} mensualidades.";
        } elseif ($cargo['fecha']->format('Y-m') !== $fecha->format('Y-m')) {
            $esperado = $compra->calendario->first(fn ($c) => $c['fecha']->format('Y-m') === $fecha->format('Y-m'));
            $avisos[] = "El banco indica el pago {$numero}; según el plan este mes toca el pago "
                . ($esperado ? $esperado['numero'] : 'ninguno') . '.';
        }

        if (isset($renglon['saldo_pendiente']) && $renglon['saldo_pendiente'] !== null) {
            $saldoPlan = round($compra->calendario->slice($numero)->sum('monto'), 2);
            if (abs($saldoPlan - (float) $renglon['saldo_pendiente']) > 0.01) {
                $avisos[] = 'Saldo pendiente: banco $' . number_format((float) $renglon['saldo_pendiente'], 2)
                    . ', plan $' . number_format($saldoPlan, 2) . '.';
            }
        }

        return [$avisos ? self::DIFERENCIA : self::VERIFICADA, $renglon, $compra, $avisos, $compra->fecha_primer_cargo];
    }

    private function resultado(string $accion, array $renglon, ?CompraMeses $compra, array $avisos, Carbon $primerCargo): array
    {
        $meses = (int) ($renglon['total_pagos'] ?? 0);
        $monto = (float) $renglon['monto_original'];

        return [
            'accion'    => $accion,
            'compra_id' => $compra?->id,
            'avisos'    => $avisos,
            // Datos para dar de alta el plan cuando no existe. Sin fecha de operación
            // en el estado de cuenta, la fecha de compra se estima con el primer cargo.
            'sugerencia' => $compra ? null : [
                'cuenta_id'          => $this->cuenta->id,
                'descripcion'        => trim($renglon['descripcion']),
                'monto_original'     => $monto,
                'meses'              => $meses ?: null,
                'mensualidad'        => $renglon['mensualidad'] ?? ($meses ? round($monto / $meses, 2) : null),
                'fecha_compra'       => ($renglon['fecha_compra'] ?? null) ?: $primerCargo->toDateString(),
                'fecha_primer_cargo' => $primerCargo->toDateString(),
            ],
        ];
    }

    private function buscarPlan(string $descripcion, float $monto): ?CompraMeses
    {
        $buscada = self::normalizar($descripcion);

        return CompraMeses::where('cuenta_id', $this->cuenta->id)
            ->whereBetween('monto_original', [$monto - 0.01, $monto + 0.01])
            ->get()
            ->first(function ($compra) use ($buscada) {
                // El banco suele recortar la descripción: basta con que una contenga a la otra
                $propia = self::normalizar($compra->descripcion);
                return $propia !== '' && $buscada !== ''
                    && (str_contains($propia, $buscada) || str_contains($buscada, $propia));
            });
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $texto));
    }
}
