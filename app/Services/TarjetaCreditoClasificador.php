<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Traspaso;
use Carbon\Carbon;

/**
 * Convierte los movimientos de una tarjeta de crédito (Afirme, Banregio o
 * American Express) en filas para la vista previa: cargos → gasto, pagos → traspaso
 * desde la cuenta con la que se paga la tarjeta. Los meses sin intereses no
 * son movimientos: se regresan aparte para conciliarlos con las compras a
 * meses (ConciliadorComprasMeses).
 *
 * De Amex además: comisión + IVA en un solo gasto, abonos que no son pagos →
 * ingreso, y las mensualidades cobradas ("CARGO 06 DE 12") se quitan de las
 * filas y se anota si ya se venían registrando como gasto cada mes.
 */
class TarjetaCreditoClasificador
{
    // Tolerancia para reconocer un pago a la tarjeta que ya se registró como traspaso
    private const DIAS_TOLERANCIA_PAGO = 5;
    // Amex solo trae la fecha de operación; a mano o desde Excel se pudo capturar un día distinto
    private const DIAS_TOLERANCIA_GASTO_AMEX = 2;

    private array $idsUsados = ['gasto' => [], 'traspaso' => [], 'ingreso' => []];
    private bool $esAmex = false;

    public function __construct(private Cuenta $tarjeta)
    {
    }

    /** @return array{0: array, 1: array} filas importables y renglones de meses sin intereses */
    public function clasificar(array $estado): array
    {
        $this->esAmex = ($estado['banco'] ?? 'afirme') === 'amex';
        $filas = [];

        foreach ($this->agruparComisiones($estado['movimientos']) as $mov) {
            $clase = $mov['clase'] ?? null;
            if ($clase === 'mensualidad') {
                continue;
            }

            $base = [
                'fecha'       => $mov['fecha'],
                'fecha_cargo' => $mov['fecha_cargo'],
                'monto'       => $mov['monto'],
                'referencia'  => $mov['referencia'] ?? null,
                'comercio'    => $mov['comercio'],
                'detalle'     => $mov['detalle'] ?? $mov['descripcion'],
                'tarjeta'     => $mov['tarjeta'] ?? null,
            ];

            if ($clase === 'comision') {
                $filas[] = AfirmeClasificador::filaBase($base + [
                    'naturaleza'   => 'retiro',
                    'concepto'     => $mov['descripcion'],
                    'comercio'     => 'COMISIONES BANCARIAS',
                    'monto_iva'    => $mov['monto_iva'] ?? 0,
                    'categoria_id' => $this->categoriaComisionesId(),
                    'tipo'         => 'gasto',
                    'motivo'       => 'Comisión de la tarjeta',
                ]);
            } elseif ($mov['naturaleza'] === 'cargo') {
                $filas[] = AfirmeClasificador::filaBase($base + [
                    'naturaleza' => 'retiro',
                    'concepto'   => $this->esAmex ? $this->conceptoAmex($mov) : $mov['comercio'],
                    'tipo'       => 'gasto',
                    'motivo'     => 'Compra con tarjeta',
                ]);
            } elseif ($clase === 'abono') {
                $filas[] = AfirmeClasificador::filaBase($base + [
                    'naturaleza' => 'deposito',
                    'concepto'   => $this->conceptoAmex($mov),
                    'tipo'       => 'ingreso',
                    'motivo'     => 'Abono a la tarjeta (devolución o bonificación)',
                ]);
            } else {
                $esPago = $clase === 'pago' || str_contains($mov['comercio'], 'PAGO');
                $filas[] = AfirmeClasificador::filaBase($base + [
                    'naturaleza'        => 'deposito',
                    'concepto'          => $esPago ? trim('Pago a tarjeta ' . ($this->tarjeta->banco->nombre ?? '')) : $mov['comercio'],
                    'tipo'              => 'traspaso',
                    'motivo'            => 'Abono a la tarjeta',
                    'cuenta_destino_id' => $this->cuentaOrigenPagos(),
                ]);
            }
        }

        usort($filas, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));
        $filas = $this->marcarPagosAgrupados(array_map(fn ($fila) => $this->marcarDuplicado($fila), $filas));

        $msi = $this->esAmex
            ? array_map(fn ($renglon) => $this->mensualidadComoGasto($renglon, $estado), $estado['msi'])
            : $estado['msi'];

        return [$filas, $msi];
    }

    // "ZOOM VIDEO COMMUNICATIO SAN JOSE (USD 19.71)": el gasto queda en pesos y se anota el monto original
    private function conceptoAmex(array $mov): string
    {
        if (!($mov['moneda'] ?? null)) {
            return $mov['descripcion'];
        }
        $decimales = floor($mov['monto_moneda']) == $mov['monto_moneda'] && $mov['moneda'] === 'JPY' ? 0 : 2;
        return "{$mov['descripcion']} ({$mov['moneda']} " . number_format($mov['monto_moneda'], $decimales) . ')';
    }

    // La comisión y el IVA que la sigue forman un solo gasto con el IVA separado
    private function agruparComisiones(array $movimientos): array
    {
        $resultado = [];

        foreach ($movimientos as $mov) {
            $clase  = $mov['clase'] ?? null;
            $ultimo = array_key_last($resultado);

            if ($clase === 'iva' && $ultimo !== null && ($resultado[$ultimo]['clase'] ?? null) === 'comision') {
                $resultado[$ultimo]['monto']     = round($resultado[$ultimo]['monto'] + $mov['monto'], 2);
                $resultado[$ultimo]['monto_iva'] = round(($resultado[$ultimo]['monto_iva'] ?? 0) + $mov['monto'], 2);
                $resultado[$ultimo]['detalle']  .= ' | ' . $mov['detalle'];
                continue;
            }
            if ($clase === 'iva') {
                $mov['clase']     = 'comision';
                $mov['monto_iva'] = $mov['monto'];
            }
            $resultado[] = $mov;
        }

        return $resultado;
    }

    /**
     * Una compra ya registrada tiene el mismo monto con fecha entre la de
     * operación y la de cargo (a mano se suele capturar cualquiera de las dos;
     * en Amex ±2 días). Un pago ya registrado es un traspaso a la tarjeta por el
     * mismo monto en ±5 días. Cada registro existente solo puede marcar una fila.
     */
    private function marcarDuplicado(array $fila): array
    {
        if ($fila['tipo'] === 'traspaso') {
            $modelo = 'traspaso';
            $query  = Traspaso::where('cuenta_destino_id', $this->tarjeta->id)->whereBetween('fecha', $this->rango($fila['fecha'], $fila['fecha'], self::DIAS_TOLERANCIA_PAGO));
        } elseif ($fila['tipo'] === 'ingreso') {
            $modelo = 'ingreso';
            $query  = Ingreso::where('cuenta_id', $this->tarjeta->id)->whereBetween('fecha', $this->rango($fila['fecha'], $fila['fecha'], self::DIAS_TOLERANCIA_GASTO_AMEX));
        } else {
            $modelo = 'gasto';
            $query  = $this->gastosPosibles($fila['fecha'], $fila['fecha_cargo']);
        }

        $id = $query->where('monto', $fila['monto'])
            ->whereNotIn('id', $this->idsUsados[$modelo])
            ->orderBy('fecha')
            ->value('id');

        if ($id === null) {
            return $fila;
        }

        $this->idsUsados[$modelo][] = $id;

        return $this->duplicado($fila, match ($modelo) {
            'traspaso' => 'Ya registrado: traspaso a la tarjeta por el mismo monto (±' . self::DIAS_TOLERANCIA_PAGO . ' días)',
            'ingreso'  => 'Ya registrado: ingreso en la tarjeta por el mismo monto',
            default    => $this->esAmex
                ? 'Ya registrado: gasto por el mismo monto (±' . self::DIAS_TOLERANCIA_GASTO_AMEX . ' días)'
                : 'Ya registrado: gasto por el mismo monto entre la fecha de operación y la de cargo',
        });
    }

    /**
     * Un pago capturado a mano como un solo traspaso puede venir partido en el
     * estado de cuenta (varios pagos el mismo día): si suman exactamente un
     * traspaso a la tarjeta que no se usó, todos son duplicados.
     */
    private function marcarPagosAgrupados(array $filas): array
    {
        $porDia = [];
        foreach ($filas as $i => $fila) {
            if ($fila['tipo'] === 'traspaso' && !$fila['duplicado']) {
                $porDia[$fila['fecha']][] = $i;
            }
        }

        foreach ($porDia as $fecha => $indices) {
            if (count($indices) < 2) {
                continue;
            }
            $suma = round(array_sum(array_map(fn ($i) => $filas[$i]['monto'], $indices)), 2);
            $id = Traspaso::where('cuenta_destino_id', $this->tarjeta->id)
                ->whereBetween('fecha', $this->rango($fecha, $fecha, self::DIAS_TOLERANCIA_PAGO))
                ->where('monto', $suma)
                ->whereNotIn('id', $this->idsUsados['traspaso'])
                ->orderBy('fecha')
                ->value('id');
            if ($id === null) {
                continue;
            }

            $this->idsUsados['traspaso'][] = $id;
            foreach ($indices as $i) {
                $filas[$i] = $this->duplicado($filas[$i], 'Ya registrado: los ' . count($indices) . ' pagos del día suman un traspaso a la tarjeta por $' . number_format($suma, 2));
            }
        }

        return $filas;
    }

    /**
     * Amex cobra cada mensualidad como un cargo. Antes de este importador esas
     * mensualidades se registraban como un gasto cada mes: se cuentan las de
     * periodos anteriores (para seguir igual en vez de dar de alta el plan, que
     * duplicaría el gasto) y se revisa si la de este periodo ya existe.
     */
    private function mensualidadComoGasto(array $renglon, array $estado): array
    {
        // La última mensualidad absorbe los centavos: las anteriores pueden diferir hasta $1 (mismo comercio)
        $pago = (float) $renglon['pago_requerido'];
        $previas = Gasto::where('cuenta_id', $this->tarjeta->id)
            ->where(fn ($q) => $q->where('monto', $pago)->orWhere(fn ($q) => $q
                ->whereBetween('monto', [$pago - 1, $pago + 1])
                ->where('concepto', 'like', ($renglon['comercio'] ?? $renglon['descripcion']) . '%')))
            ->where('fecha', '<', $estado['periodo_inicio'])
            ->where('fecha', '>=', $renglon['fecha_compra'])
            ->count();

        $registrada = $this->gastosPosibles($renglon['fecha'], $renglon['fecha'])
            ->where('monto', $renglon['pago_requerido'])
            ->whereNotIn('id', $this->idsUsados['gasto'])
            ->orderBy('fecha')
            ->value('id');
        if ($registrada !== null) {
            $this->idsUsados['gasto'][] = $registrada;
        }

        return $renglon + ['mensualidades_previas' => $previas, 'mensualidad_registrada' => $registrada !== null];
    }

    private function gastosPosibles(string $fecha, string $fechaCargo)
    {
        return Gasto::where('cuenta_id', $this->tarjeta->id)
            ->whereBetween('fecha', $this->rango($fecha, $fechaCargo, $this->esAmex ? self::DIAS_TOLERANCIA_GASTO_AMEX : 0));
    }

    private function rango(string $desde, string $hasta, int $dias): array
    {
        return [Carbon::parse($desde)->subDays($dias)->toDateString(), Carbon::parse($hasta)->addDays($dias)->toDateString()];
    }

    private function duplicado(array $fila, string $motivo): array
    {
        return array_merge($fila, ['duplicado' => true, 'tipo' => 'ignorar', 'motivo' => $motivo]);
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

    private function categoriaComisionesId(): ?int
    {
        return once(fn () => Categoria::whereIn('tipo', ['gasto', 'ambos'])
            ->where('nombre', 'like', '%comision%')
            ->orderBy('id')
            ->value('id'));
    }
}
