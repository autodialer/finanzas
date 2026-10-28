<?php

namespace App\Services;

use App\Models\CompraMeses;
use App\Models\Cuenta;
use Carbon\Carbon;

/**
 * Compara un renglón de la sección de meses sin intereses del estado de cuenta
 * de la tarjeta con los planes registrados:
 *  - con plan          → verificar número de pago, mensualidad y saldo pendiente
 *  - pago 1 sin plan   → sugerir dar de alta la compra (gasto + plan)
 *  - pago > 1 sin plan → ofrecer el alta con la fecha de compra estimada
 *
 * El renglón trae (ver AfirmeTarjetaParser): descripcion, monto_original,
 * numero_pago, total_pagos, mensualidad y saldo_pendiente (opcionales; el saldo
 * es el que queda después del pago del periodo), fecha del cargo y, si se
 * conocen, periodo_inicio / periodo_fin del estado de cuenta y fecha_compra.
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
        $numero = max((int) $renglon['numero_pago'], 1);
        $fecha  = Carbon::parse($renglon['fecha']);

        [$compra, $avisos] = $this->buscarPlan($renglon['descripcion'], (float) $renglon['monto_original']);
        if ($compra) {
            $avisos = array_merge($avisos, $this->verificar($compra, $renglon, $numero, $fecha));
            return $this->resultado($avisos ? self::DIFERENCIA : self::VERIFICADA, $renglon, $compra, $avisos, $compra->fecha_primer_cargo);
        }

        // Si el estado de cuenta trae la fecha de compra (Amex), el primer cargo no puede ser anterior
        $primerCargo = $fecha->copy()->subMonthsNoOverflow($numero - 1);
        if (!empty($renglon['fecha_compra']) && $primerCargo->lt(Carbon::parse($renglon['fecha_compra']))) {
            $primerCargo = Carbon::parse($renglon['fecha_compra']);
        }

        return $this->resultado($numero === 1 ? self::CREAR : self::SIN_PLAN, $renglon, null, [], $primerCargo);
    }

    private function verificar(CompraMeses $compra, array $renglon, int $numero, Carbon $fecha): array
    {
        $avisos = [];

        if (!empty($renglon['total_pagos']) && (int) $renglon['total_pagos'] !== $compra->meses) {
            $avisos[] = "El banco indica {$renglon['total_pagos']} meses y el plan tiene {$compra->meses}.";
        }

        // El cargo número $numero del plan debe caer en el periodo del estado de cuenta
        // (o, si no se conoce el periodo, en el mismo mes que el cargo del banco)
        $enPeriodo = !empty($renglon['periodo_inicio']) && !empty($renglon['periodo_fin'])
            ? fn ($c) => $c['fecha']->between(Carbon::parse($renglon['periodo_inicio'])->startOfDay(), Carbon::parse($renglon['periodo_fin'])->endOfDay())
            : fn ($c) => $c['fecha']->format('Y-m') === $fecha->format('Y-m');

        $cargo = $compra->calendario->firstWhere('numero', $numero);
        if (!$cargo) {
            $avisos[] = "El banco indica el pago {$numero} pero el plan solo tiene {$compra->meses} mensualidades.";
        } elseif (!$enPeriodo($cargo)) {
            $esperados = $compra->calendario->filter($enPeriodo)->pluck('numero');
            $avisos[] = "El banco indica el pago {$numero}; según el plan en este periodo toca el pago "
                . ($esperados->isNotEmpty() ? $esperados->implode(' y ') : 'ninguno') . '.';
        }

        if ($cargo && isset($renglon['mensualidad']) && abs($cargo['monto'] - (float) $renglon['mensualidad']) > 0.01) {
            $avisos[] = 'Mensualidad: banco $' . number_format((float) $renglon['mensualidad'], 2)
                . ', plan $' . number_format($cargo['monto'], 2) . '.';
        }

        if (isset($renglon['saldo_pendiente'])) {
            $saldoPlan = round($compra->calendario->slice($numero)->sum('monto'), 2);
            if (abs($saldoPlan - (float) $renglon['saldo_pendiente']) > 0.01) {
                $avisos[] = 'Saldo pendiente: banco $' . number_format((float) $renglon['saldo_pendiente'], 2)
                    . ', plan $' . number_format($saldoPlan, 2) . '.';
            }
        }

        return $avisos;
    }

    private function resultado(string $accion, array $renglon, ?CompraMeses $compra, array $avisos, Carbon $primerCargo): array
    {
        $meses = (int) ($renglon['total_pagos'] ?? 0);
        $monto = (float) $renglon['monto_original'];

        return [
            'accion'    => $accion,
            'compra_id' => $compra?->id,
            'avisos'    => $avisos,
            // Datos para dar de alta el plan cuando no existe. El estado de cuenta no
            // trae la fecha de compra: se estima con la del primer cargo.
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

    /** @return array{0: ?CompraMeses, 1: array} el plan encontrado y avisos de la búsqueda */
    private function buscarPlan(string $descripcion, float $monto): array
    {
        $buscada = self::normalizar($descripcion);

        $candidatas = CompraMeses::where('cuenta_id', $this->cuenta->id)
            ->whereBetween('monto_original', [$monto - 0.01, $monto + 0.01])
            ->get();

        // El banco suele recortar la descripción: basta con que una contenga a la otra
        $porDescripcion = $candidatas->first(function ($compra) use ($buscada) {
            $propia = self::normalizar($compra->descripcion);
            return $propia !== '' && $buscada !== ''
                && (str_contains($propia, $buscada) || str_contains($buscada, $propia));
        });
        if ($porDescripcion) {
            return [$porDescripcion, []];
        }

        // Si la descripción se capturó distinta pero solo hay un plan con ese monto en la tarjeta
        if ($candidatas->count() === 1) {
            $compra = $candidatas->first();
            return [$compra, ["La descripción no coincide (plan: \"{$compra->descripcion}\"); se empató por monto original."]];
        }

        return [null, []];
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $texto));
    }
}
