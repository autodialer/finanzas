<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Prestamo;
use App\Models\Traspaso;

/**
 * Convierte los movimientos de un estado de cuenta Afirme en filas para la
 * vista previa, con un tipo sugerido (gasto, ingreso, traspaso, prestamo o
 * ignorar) y marcando las que ya se importaron antes.
 */
class AfirmeClasificador
{
    public const TIPOS = ['gasto', 'ingreso', 'traspaso', 'prestamo', 'ignorar'];

    private const PATRON_COMISION     = '/^(TRANSFERENCIA - ENVIO|COMISION|ADMINISTRACION DE ESQUEMA DE SEGURIDAD)/u';
    private const PATRON_IVA          = '/^IVA\b/u';
    private const PATRON_DEVOLUCION_COMISION = '/^(COMISION|IVA COMISION)/u';

    public function __construct(private Cuenta $cuenta)
    {
    }

    public function clasificar(array $movimientos): array
    {
        $usados = [];
        $filas  = [];

        foreach ($this->speiDevueltos($movimientos) as $i) {
            $usados[$i] = true;
            $filas[$i]  = $this->fila([$movimientos[$i]], 'ignorar', 'SPEI devuelto el mismo día');
        }

        foreach ($movimientos as $i => $mov) {
            if (isset($usados[$i])) {
                continue;
            }
            $usados[$i] = true;
            $esRetiro = $mov['naturaleza'] === 'retiro';
            $desc     = $mov['descripcion'];

            // Comisión + su IVA (siguiente renglón del mismo día) en un solo gasto
            if ($esRetiro && (preg_match(self::PATRON_COMISION, $desc) || preg_match(self::PATRON_IVA, $desc))) {
                $grupo = [$mov];
                $siguiente = $movimientos[$i + 1] ?? null;
                if (!preg_match(self::PATRON_IVA, $desc) && $siguiente && !isset($usados[$i + 1])
                    && $siguiente['naturaleza'] === 'retiro' && $siguiente['fecha'] === $mov['fecha']
                    && preg_match(self::PATRON_IVA, $siguiente['descripcion'])) {
                    $grupo[] = $siguiente;
                    $usados[$i + 1] = true;
                }
                $filas[$i] = $this->filaComision($grupo);
                continue;
            }

            if ($esRetiro && str_starts_with($desc, 'DOMI-AMERICAN EXPRESS')) {
                $filas[$i] = array_merge($this->fila([$mov], 'traspaso', 'Pago de tarjeta American Express'), [
                    'cuenta_destino_id' => $this->cuentaAmexId(),
                ]);
                continue;
            }

            if ($esRetiro && preg_match('/^C\/Online (Inter[eé]s|Capital)/iu', $desc, $m)) {
                $tipoPago = stripos($m[1], 'capital') === 0 ? 'capital' : 'interes';
                $filas[$i] = array_merge($this->fila([$mov], 'prestamo', 'Pago de crédito Afirme'), [
                    'prestamo_id'   => $this->prestamoId(),
                    'prestamo_tipo' => $tipoPago,
                ]);
                continue;
            }

            $filas[$i] = $esRetiro
                ? $this->fila([$mov], 'gasto', 'Retiro')
                : $this->fila([$mov], 'ingreso', 'Depósito');
        }

        ksort($filas);
        $filas = array_values($filas);

        return $this->marcarDuplicados($filas);
    }

    /**
     * Índices de los movimientos que forman un SPEI enviado y devuelto el mismo
     * día: el envío, su comisión e IVA, el depósito "AFIRMENET" por el mismo
     * monto y la devolución de la comisión y el IVA.
     */
    private function speiDevueltos(array $movimientos): array
    {
        $indices = [];

        foreach ($movimientos as $i => $envio) {
            if ($envio['naturaleza'] !== 'retiro' || !str_starts_with($envio['descripcion'], 'ENVIO SPEI')) {
                continue;
            }

            foreach ($movimientos as $j => $regreso) {
                if (isset($indices[$j]) || $regreso['naturaleza'] !== 'deposito'
                    || $regreso['fecha'] !== $envio['fecha'] || abs($regreso['monto'] - $envio['monto']) > 0.004
                    || !str_contains($regreso['descripcion'], 'AFIRMENET')) {
                    continue;
                }

                $indices[$i] = true;
                $indices[$j] = true;
                $this->tomarSiguientes($movimientos, $i, 'retiro', [self::PATRON_COMISION, self::PATRON_IVA], $indices);
                $this->tomarSiguientes($movimientos, $j, 'deposito', [self::PATRON_DEVOLUCION_COMISION], $indices);
                break;
            }
        }

        return array_keys($indices);
    }

    // Marca hasta dos renglones consecutivos (comisión e IVA) que siguen al movimiento $desde
    private function tomarSiguientes(array $movimientos, int $desde, string $naturaleza, array $patrones, array &$indices): void
    {
        for ($k = $desde + 1; $k <= $desde + 2 && isset($movimientos[$k]); $k++) {
            $mov = $movimientos[$k];
            $coincide = collect($patrones)->contains(fn ($p) => preg_match($p, $mov['descripcion']));
            if (isset($indices[$k]) || $mov['naturaleza'] !== $naturaleza || $mov['fecha'] !== $movimientos[$desde]['fecha'] || !$coincide) {
                return;
            }
            $indices[$k] = true;
        }
    }

    /** Campos de una fila de la vista previa (comunes a Visión y tarjeta de crédito). */
    public static function filaBase(array $datos): array
    {
        return array_merge([
            'fecha'             => null,
            'naturaleza'        => null, // retiro (sale dinero) o deposito (entra)
            'referencia'        => null,
            'monto'             => 0,
            'monto_iva'         => 0,
            'concepto'          => '',
            'comercio'          => '',
            'detalle'           => '',
            'tipo'              => 'ignorar',
            'motivo'            => '',
            'categoria_id'      => null,
            'cuenta_destino_id' => null,
            'prestamo_id'       => null,
            'prestamo_tipo'     => null,
            'duplicado'         => false,
        ], $datos);
    }

    private function fila(array $movs, string $tipo, string $motivo): array
    {
        $mov = $movs[0];

        return self::filaBase([
            'fecha'      => $mov['fecha'],
            'naturaleza' => $mov['naturaleza'],
            'referencia' => $mov['referencia'],
            'monto'      => round(array_sum(array_column($movs, 'monto')), 2),
            'concepto'   => $this->resumir($mov['descripcion']),
            'comercio'   => $this->contraparte($mov['descripcion']),
            'detalle'    => implode(' | ', array_column($movs, 'descripcion')),
            'tipo'       => $tipo,
            'motivo'     => $motivo,
        ]);
    }

    private function filaComision(array $grupo): array
    {
        $fila = $this->fila($grupo, 'gasto', 'Comisión bancaria');
        $fila['concepto']     = 'Comisiones bancarias';
        $fila['comercio']     = 'COMISIONES BANCARIAS';
        $fila['categoria_id'] = $this->categoriaComisionesId();
        $fila['monto_iva']    = round(array_sum(array_map(
            fn ($m) => preg_match(self::PATRON_IVA, $m['descripcion']) ? $m['monto'] : 0,
            $grupo
        )), 2);
        return $fila;
    }

    /** Descripción corta para mostrar y usar como concepto. */
    public function resumir(string $descripcion): string
    {
        $desc = $this->limpiar($descripcion);

        if (str_starts_with($desc, 'SPEI RECIBIDO') || str_starts_with($desc, 'ENVIO SPEI')) {
            $concepto = $this->campo($desc, 'CONCEPTO', 'HORA:');
            $prefijo  = str_starts_with($desc, 'SPEI RECIBIDO') ? 'SPEI de ' : 'SPEI a ';
            return trim($prefijo . ($this->contraparte($descripcion) ?: 'desconocido') . ($concepto ? " — {$concepto}" : ''));
        }

        if (str_starts_with($desc, 'DOMI-AMERICAN EXPRESS')) {
            return 'Pago American Express (domiciliación)';
        }

        return mb_strimwidth($desc, 0, 80, '…');
    }

    /** Quién envía o recibe el dinero; es la llave para aprender la categoría. */
    public function contraparte(string $descripcion): string
    {
        $desc = $this->limpiar($descripcion);

        if (str_starts_with($desc, 'SPEI RECIBIDO')) {
            return (string) $this->campo($desc, 'EMISOR', 'RFC EMISOR:|CVE RASTREO:|CONCEPTO:|HORA:');
        }
        if (str_starts_with($desc, 'ENVIO SPEI')) {
            return (string) $this->campo($desc, 'DESTINATARIO', 'RFC DESTINATARIO:|CVE RASTREO:|CONCEPTO:');
        }
        if (str_starts_with($desc, 'DOMI-AMERICAN EXPRESS')) {
            return 'AMERICAN EXPRESS';
        }
        return mb_substr($desc, 0, 60);
    }

    private function limpiar(string $descripcion): string
    {
        // La leyenda "(DATO NO VERIFICADO...)" puede venir partida entre líneas
        $desc = preg_replace('/\(\s*D\s*A\s*T\s*O\s+NO\s+VERIFICADO.*?\)/u', '', $descripcion);
        return trim(preg_replace('/\s+/u', ' ', $desc));
    }

    private function campo(string $desc, string $etiqueta, string $hasta): ?string
    {
        return preg_match("/(?<!RFC ){$etiqueta}:\s*(.*?)\s*(?={$hasta}|$)/u", $desc, $m) ? trim($m[1]) : null;
    }

    /**
     * Un movimiento ya importado tiene la misma fecha, referencia y monto en la
     * misma cuenta. Se cuentan las coincidencias para no perder movimientos
     * idénticos repetidos dentro del mismo estado de cuenta.
     */
    private function marcarDuplicados(array $filas): array
    {
        $disponibles = [];

        foreach ($filas as &$fila) {
            if ($fila['tipo'] === 'ignorar') {
                continue;
            }

            $clave = $fila['fecha'] . '|' . $fila['referencia'] . '|' . number_format($fila['monto'], 2, '.', '');
            $disponibles[$clave] ??= $this->contarExistentes($fila);

            if ($disponibles[$clave] > 0) {
                $disponibles[$clave]--;
                $fila['duplicado'] = true;
                $fila['tipo']      = 'ignorar';
                $fila['motivo']    = 'Ya importado (misma fecha, referencia y monto)';
            }
        }
        unset($fila);

        return $filas;
    }

    private function contarExistentes(array $fila): int
    {
        $filtro = function ($query) use ($fila) {
            $query->whereDate('fecha', $fila['fecha'])->where('monto', $fila['monto']);
            $fila['referencia'] === null
                ? $query->whereNull('referencia')
                : $query->where('referencia', $fila['referencia']);
            return $query;
        };

        $cuentaId = $this->cuenta->id;

        return $filtro(Gasto::where('cuenta_id', $cuentaId))->count()
            + $filtro(Ingreso::where('cuenta_id', $cuentaId))->count()
            + $filtro(Traspaso::where(fn ($q) => $q->where('cuenta_origen_id', $cuentaId)->orWhere('cuenta_destino_id', $cuentaId)))->count();
    }

    private function cuentaAmexId(): ?int
    {
        return once(fn () => Cuenta::whereHas('banco', fn ($q) => $q->where('nombre', 'like', '%american express%'))
            ->orderByRaw('negocio_id = ? desc', [$this->cuenta->negocio_id])
            ->orderBy('id')
            ->value('id'));
    }

    private function prestamoId(): ?int
    {
        return once(fn () => Prestamo::where('negocio_id', $this->cuenta->negocio_id)
            ->when($this->cuenta->banco_id, fn ($q) => $q->where('banco_id', $this->cuenta->banco_id))
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
