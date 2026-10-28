<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Traspaso;

/**
 * Convierte los cargos y abonos regulares de la tarjeta (AfirmeTarjetaParser) en
 * filas con el mismo formato que AfirmeClasificador, para reutilizar la vista
 * previa y el guardado del importador de Cuenta Visión:
 *  - cargo            → gasto (con la categoría usada antes para el mismo comercio)
 *  - pago a la tarjeta → traspaso desde otra cuenta
 *  - otro abono        → ingreso (devoluciones, bonificaciones)
 * y marcando como duplicados los que ya están registrados.
 */
class AfirmeTarjetaClasificador
{
    private const PATRON_COSTO = '/^(INTERES|COMISION|ANUALIDAD|IVA)/u';
    private const PATRON_IVA   = '/^IVA\b/u';
    private const PATRON_PAGO  = '/^PAGO/u';

    // Días de diferencia aceptados entre el pago en la tarjeta y el traspaso ya registrado
    private const DIAS_PAGO = 5;

    private array $categoriasPorComercio = [];

    public function __construct(private Cuenta $cuenta)
    {
    }

    public function clasificar(array $movimientos): array
    {
        $filas = array_map(fn ($mov) => $this->fila($mov), $movimientos);
        return $this->marcarDuplicados($filas);
    }

    private function fila(array $mov): array
    {
        $esCargo = $mov['naturaleza'] === 'cargo';
        $tipoMov = $mov['tipo'];
        $esCosto = $esCargo && preg_match(self::PATRON_COSTO, $tipoMov);
        $esPago  = !$esCargo && preg_match(self::PATRON_PAGO, $tipoMov);

        [$tipo, $motivo] = match (true) {
            $esCosto => ['gasto', 'Interés o comisión de la tarjeta'],
            $esCargo => ['gasto', 'Compra con tarjeta'],
            $esPago  => ['traspaso', 'Pago a la tarjeta'],
            default  => ['ingreso', 'Abono / devolución'],
        };

        return [
            'fecha'             => $mov['fecha'],
            'naturaleza'        => $esCargo ? 'retiro' : 'deposito',
            'referencia'        => null,
            'monto'             => $mov['monto'],
            'monto_iva'         => $esCosto && preg_match(self::PATRON_IVA, $tipoMov) ? $mov['monto'] : 0,
            'concepto'          => $esPago ? 'Pago a la tarjeta ' . $this->cuenta->nombre : mb_strimwidth($mov['descripcion'], 0, 80, '…'),
            'detalle'           => trim("{$tipoMov} {$mov['descripcion']}") . ' (cargo ' . $mov['fecha_cargo'] . ')',
            'tipo'              => $tipo,
            'motivo'            => $motivo,
            'categoria_id'      => $esCosto ? $this->categoriaComisionesId() : ($esCargo ? $this->categoriaAnterior($mov['descripcion']) : null),
            'cuenta_destino_id' => $esPago ? $this->cuentaPagoAnterior() : null,
            'prestamo_id'       => null,
            'prestamo_tipo'     => null,
            'duplicado'         => false,
        ];
    }

    /**
     * Un cargo ya registrado es un gasto de la tarjeta con la misma fecha y monto;
     * un pago, un traspaso hacia la tarjeta por el mismo monto en fechas cercanas
     * (p. ej. importado desde el estado de cuenta de débito). Se cuentan las
     * coincidencias para no perder movimientos idénticos del mismo periodo.
     */
    private function marcarDuplicados(array $filas): array
    {
        $disponibles = [];

        foreach ($filas as &$fila) {
            $clave = $fila['tipo'] . '|' . $fila['fecha'] . '|' . number_format($fila['monto'], 2, '.', '');
            $disponibles[$clave] ??= $this->existentes($fila);

            if ($disponibles[$clave]) {
                $origen = array_shift($disponibles[$clave]);
                $fila['duplicado'] = true;
                $fila['tipo']      = 'ignorar';
                $fila['motivo']    = $origen;
            }
        }
        unset($fila);

        return $filas;
    }

    /** @return string[] un motivo por cada registro existente que coincide */
    private function existentes(array $fila): array
    {
        $cuentaId = $this->cuenta->id;

        if ($fila['tipo'] === 'traspaso') {
            $desde = date('Y-m-d', strtotime("{$fila['fecha']} -" . self::DIAS_PAGO . ' days'));
            $hasta = date('Y-m-d', strtotime("{$fila['fecha']} +" . self::DIAS_PAGO . ' days'));
            return Traspaso::with('cuentaOrigen')
                ->where('cuenta_destino_id', $cuentaId)
                ->where('monto', $fila['monto'])
                ->whereBetween('fecha', [$desde, $hasta])
                ->get()
                ->map(fn ($t) => 'Ya registrado como traspaso desde ' . ($t->cuentaOrigen->nombre ?? 'otra cuenta') . ' el ' . date('d/m/Y', strtotime($t->fecha)))
                ->all();
        }

        $modelo = $fila['tipo'] === 'ingreso' ? Ingreso::class : Gasto::class;
        $total = $modelo::where('cuenta_id', $cuentaId)->whereDate('fecha', $fila['fecha'])->where('monto', $fila['monto'])->count();

        return array_fill(0, $total, 'Ya importado (misma fecha y monto en esta tarjeta)');
    }

    // Categoría del último gasto del mismo comercio en el negocio de la tarjeta
    private function categoriaAnterior(string $comercio): ?int
    {
        return $this->categoriasPorComercio[$comercio] ??= Gasto::where('negocio_id', $this->cuenta->negocio_id)
            ->where('concepto', mb_strimwidth($comercio, 0, 80, '…'))
            ->latest('fecha')
            ->value('categoria_id');
    }

    // Cuenta desde la que se pagó la tarjeta la última vez
    private function cuentaPagoAnterior(): ?int
    {
        return once(fn () => Traspaso::where('cuenta_destino_id', $this->cuenta->id)->latest('fecha')->value('cuenta_origen_id'));
    }

    private function categoriaComisionesId(): ?int
    {
        return once(fn () => Categoria::whereIn('tipo', ['gasto', 'ambos'])
            ->where('nombre', 'like', '%comision%')
            ->orderBy('id')
            ->value('id'));
    }
}
