<?php

namespace App\Services;

class CalculoNominaService
{
    // UMA mensual 2025
    private const UMA_MENSUAL = 3_300.53;

    // Tarifa ISR mensual 2025 (Art. 96 LISR)
    private const TARIFA_ISR = [
        ['li' =>      0.01, 'ls' =>     746.04, 'cf' =>       0.00, 'tasa' => 0.0192],
        ['li' =>    746.05, 'ls' =>   6_332.05, 'cf' =>      14.32, 'tasa' => 0.0640],
        ['li' =>  6_332.06, 'ls' =>  11_128.01, 'cf' =>     371.83, 'tasa' => 0.1088],
        ['li' => 11_128.02, 'ls' =>  12_935.82, 'cf' =>     893.63, 'tasa' => 0.1600],
        ['li' => 12_935.83, 'ls' =>  15_487.71, 'cf' =>   1_182.88, 'tasa' => 0.1792],
        ['li' => 15_487.72, 'ls' =>  31_236.49, 'cf' =>   1_640.18, 'tasa' => 0.2136],
        ['li' => 31_236.50, 'ls' =>  49_233.00, 'cf' =>   5_004.12, 'tasa' => 0.2352],
        ['li' => 49_233.01, 'ls' =>  93_993.90, 'cf' =>   9_236.89, 'tasa' => 0.3000],
        ['li' => 93_993.91, 'ls' => 125_325.20, 'cf' =>  22_665.17, 'tasa' => 0.3200],
        ['li' => 125_325.21, 'ls' => 375_975.61, 'cf' => 32_691.18, 'tasa' => 0.3400],
        ['li' => 375_975.62, 'ls' => PHP_FLOAT_MAX, 'cf' => 117_912.32, 'tasa' => 0.3500],
    ];

    // Subsidio al empleo mensual 2025
    private const SUBSIDIO_EMPLEO = [
        ['hasta' =>  1_768.96, 'subsidio' => 407.02],
        ['hasta' =>  2_653.38, 'subsidio' => 406.83],
        ['hasta' =>  3_472.84, 'subsidio' => 406.62],
        ['hasta' =>  3_537.87, 'subsidio' => 392.77],
        ['hasta' =>  4_446.15, 'subsidio' => 382.46],
        ['hasta' =>  4_717.18, 'subsidio' => 354.23],
        ['hasta' =>  5_335.42, 'subsidio' => 324.87],
        ['hasta' =>  6_224.67, 'subsidio' => 294.63],
        ['hasta' =>  7_113.90, 'subsidio' => 253.54],
        ['hasta' =>  7_382.33, 'subsidio' => 217.61],
        ['hasta' => PHP_FLOAT_MAX, 'subsidio' =>   0.00],
    ];

    public function calcularISR(float $salarioBruto): float
    {
        $isrPrevio = 0;
        foreach (self::TARIFA_ISR as $renglon) {
            if ($salarioBruto >= $renglon['li'] && $salarioBruto <= $renglon['ls']) {
                $isrPrevio = $renglon['cf'] + ($salarioBruto - $renglon['li']) * $renglon['tasa'];
                break;
            }
        }

        $subsidio = 0;
        foreach (self::SUBSIDIO_EMPLEO as $renglon) {
            if ($salarioBruto <= $renglon['hasta']) {
                $subsidio = $renglon['subsidio'];
                break;
            }
        }

        return round(max(0, $isrPrevio - $subsidio), 2);
    }

    public function calcularIMSSEmpleado(float $salarioBruto): float
    {
        // Cuotas obreras fijas sobre salario completo
        $cuotaFija = $salarioBruto * (0.00375 + 0.00625 + 0.01125); // 2.125%

        // 2.25% sobre el excedente de 3 UMAs mensuales
        $excedente = max(0, $salarioBruto - (self::UMA_MENSUAL * 3));
        $cuotaExcedente = $excedente * 0.0225;

        return round($cuotaFija + $cuotaExcedente, 2);
    }

    public function calcular(float $salarioBruto): array
    {
        $isr  = $this->calcularISR($salarioBruto);
        $imss = $this->calcularIMSSEmpleado($salarioBruto);

        return [
            'salario_bruto'  => round($salarioBruto, 2),
            'isr'            => $isr,
            'imss_empleado'  => $imss,
            'salario_neto'   => round($salarioBruto - $isr - $imss, 2),
        ];
    }
}
