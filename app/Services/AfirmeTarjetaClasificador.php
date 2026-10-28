<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Traspaso;
use Carbon\Carbon;

/**
 * Convierte los movimientos de la tarjeta de crédito Afirme en filas para la
 * vista previa: cargos → gasto, abonos (pagos) → traspaso desde la cuenta con
 * la que se paga la tarjeta. Los meses sin intereses no son movimientos: se
 * regresan aparte para conciliarlos con las compras a meses (ConciliadorComprasMeses).
 */
class AfirmeTarjetaClasificador
{
    // Tolerancia para reconocer un pago a la tarjeta que ya se registró como traspaso
    private const DIAS_TOLERANCIA_PAGO = 5;

    private array $idsUsados = ['gasto' => [], 'traspaso' => []];

    public function __construct(private Cuenta $tarjeta)
    {
    }

    /** @return array{0: array, 1: array} filas importables y renglones de meses sin intereses */
    public function clasificar(array $estado): array
    {
        $filas = [];

        foreach ($estado['movimientos'] as $mov) {
            $base = [
                'fecha'       => $mov['fecha'],
                'fecha_cargo' => $mov['fecha_cargo'],
                'monto'       => $mov['monto'],
                'comercio'    => $mov['comercio'],
                'detalle'     => $mov['descripcion'],
            ];

            $filas[] = $mov['naturaleza'] === 'cargo'
                ? AfirmeClasificador::filaBase($base + [
                    'naturaleza' => 'retiro',
                    'concepto'   => $mov['comercio'],
                    'tipo'       => 'gasto',
                    'motivo'     => 'Compra con tarjeta',
                ])
                : AfirmeClasificador::filaBase($base + [
                    'naturaleza'        => 'deposito',
                    'concepto'          => str_contains($mov['comercio'], 'PAGO') ? trim('Pago a tarjeta ' . ($this->tarjeta->banco->nombre ?? '')) : $mov['comercio'],
                    'tipo'              => 'traspaso',
                    'motivo'            => 'Abono a la tarjeta',
                    'cuenta_destino_id' => $this->cuentaOrigenPagos(),
                ]);
        }

        usort($filas, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));

        return [array_map(fn ($fila) => $this->marcarDuplicado($fila), $filas), $estado['msi']];
    }

    /**
     * Una compra ya registrada tiene el mismo monto con fecha entre la de
     * operación y la de cargo (a mano se suele capturar cualquiera de las dos).
     * Un pago ya registrado es un traspaso a la tarjeta por el mismo monto en ±5 días.
     * Cada registro existente solo puede marcar una fila.
     */
    private function marcarDuplicado(array $fila): array
    {
        if ($fila['tipo'] === 'traspaso') {
            $modelo = 'traspaso';
            $query  = Traspaso::where('cuenta_destino_id', $this->tarjeta->id)->whereBetween('fecha', [
                Carbon::parse($fila['fecha'])->subDays(self::DIAS_TOLERANCIA_PAGO)->toDateString(),
                Carbon::parse($fila['fecha'])->addDays(self::DIAS_TOLERANCIA_PAGO)->toDateString(),
            ]);
        } else {
            $modelo = 'gasto';
            $query  = Gasto::where('cuenta_id', $this->tarjeta->id)->whereBetween('fecha', [$fila['fecha'], $fila['fecha_cargo']]);
        }

        $id = $query->where('monto', $fila['monto'])
            ->whereNotIn('id', $this->idsUsados[$modelo])
            ->orderBy('fecha')
            ->value('id');

        if ($id === null) {
            return $fila;
        }

        $this->idsUsados[$modelo][] = $id;

        return array_merge($fila, [
            'duplicado' => true,
            'tipo'      => 'ignorar',
            'motivo'    => $modelo === 'traspaso'
                ? 'Ya registrado: traspaso a la tarjeta por el mismo monto (±' . self::DIAS_TOLERANCIA_PAGO . ' días)'
                : 'Ya registrado: gasto por el mismo monto entre la fecha de operación y la de cargo',
        ]);
    }

    // La cuenta desde la que se pagó la tarjeta la última vez; si no hay, otra cuenta del mismo banco y negocio
    private function cuentaOrigenPagos(): ?int
    {
        return once(fn () => Traspaso::where('cuenta_destino_id', $this->tarjeta->id)->orderByDesc('fecha')->value('cuenta_origen_id')
            ?? Cuenta::where('negocio_id', $this->tarjeta->negocio_id)
                ->where('tipo', 'banco')
                ->where('id', '!=', $this->tarjeta->id)
                ->when($this->tarjeta->banco_id, fn ($q) => $q->where('banco_id', $this->tarjeta->banco_id))
                ->orderBy('id')
                ->value('id'));
    }
}
