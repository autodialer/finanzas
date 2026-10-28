<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\ReglaCategoria;

/**
 * Sugiere la categoría de cada fila importada: primero por la regla aprendida
 * del comercio (lo que se eligió la última vez) y, si no hay, por palabra clave.
 *
 * Las categorías de las palabras clave que todavía no existen se proponen
 * como "nueva:<nombre>" y se crean solo al confirmar la importación.
 */
class SugeridorCategoria
{
    public const PREFIJO_NUEVA = 'nueva:';

    // Palabras de 3 letras o menos deben coincidir con una palabra completa; las demás, con su inicio
    public const PALABRAS_CLAVE = [
        'Gasolina'               => ['GASOL', 'GAS', 'PEMEX'],
        'Restaurantes y comida'  => ['REST', 'STARBUCKS', 'CARLS', 'CAFE', 'TACO', 'POLLO', 'PIZZ', 'BURGER'],
        'Estacionamiento'        => ['EST', 'ESTAC', 'PARKING'],
        'Salud'                  => ['HOSP', 'DR', 'FARMACIA', 'MEDIC', 'LABORAT'],
        'Supermercado'           => ['COSTCO', 'WALMART', 'HEB', 'SORIANA', 'CHEDRAUI', 'SAMS'],
        'Tienda de conveniencia' => ['OXXO', 'ELEVEN'],
    ];

    public static function tipoRegla(array $fila): string
    {
        return $fila['naturaleza'] === 'retiro' ? 'gasto' : 'ingreso';
    }

    public function sugerir(array $filas): array
    {
        $reglas = ReglaCategoria::all()->keyBy(fn ($r) => $r->tipo . '|' . $r->comercio);

        foreach ($filas as &$fila) {
            $tipo  = self::tipoRegla($fila);
            $regla = $reglas[$tipo . '|' . ReglaCategoria::normalizar($fila['comercio'] ?? '')] ?? null;

            if ($regla && ($fila['comercio'] ?? '') !== '') {
                $fila['categoria_id'] = $regla->categoria_id;
                $fila['motivo'] .= ' · categoría aprendida';
            } elseif ($fila['categoria_id'] === null && $tipo === 'gasto' && ($nombre = $this->porPalabraClave($fila['comercio'] ?? ''))) {
                $fila['categoria_id'] = $this->valorCategoria($nombre);
                $fila['motivo'] .= " · palabra clave ({$nombre})";
            }
        }
        unset($fila);

        return $filas;
    }

    public function porPalabraClave(string $comercio): ?string
    {
        $palabras = explode(' ', ReglaCategoria::normalizar($comercio));

        foreach (self::PALABRAS_CLAVE as $categoria => $claves) {
            foreach ($claves as $clave) {
                foreach ($palabras as $palabra) {
                    $coincide = strlen($clave) <= 3 ? $palabra === $clave : str_starts_with($palabra, $clave);
                    if ($coincide) {
                        return $categoria;
                    }
                }
            }
        }
        return null;
    }

    // Id de la categoría si ya existe; si no, el valor "nueva:<nombre>"
    private function valorCategoria(string $nombre): int|string
    {
        return $this->existente($nombre) ?? self::PREFIJO_NUEVA . $nombre;
    }

    private function existente(string $nombre): ?int
    {
        return Categoria::where('nombre', $nombre)->whereIn('tipo', ['gasto', 'ambos'])->value('id');
    }

    /** Categorías de palabras clave que todavía no existen (para el selector de la vista previa). */
    public function categoriasNuevas(): array
    {
        return array_values(array_filter(array_keys(self::PALABRAS_CLAVE), fn ($n) => $this->existente($n) === null));
    }

    public function esValida(mixed $valor, string $tipo): bool
    {
        if (is_string($valor) && str_starts_with($valor, self::PREFIJO_NUEVA)) {
            return $tipo === 'gasto' && array_key_exists(substr($valor, strlen(self::PREFIJO_NUEVA)), self::PALABRAS_CLAVE);
        }
        return Categoria::where('id', $valor)->whereIn('tipo', [$tipo, 'ambos'])->exists();
    }

    /** Id definitivo de la categoría; crea las nuevas. Llamar solo después de esValida(). */
    public function resolver(int|string $valor): int
    {
        if (is_string($valor) && str_starts_with($valor, self::PREFIJO_NUEVA)) {
            return Categoria::firstOrCreate(['nombre' => substr($valor, strlen(self::PREFIJO_NUEVA)), 'tipo' => 'gasto'])->id;
        }
        return (int) $valor;
    }

    public function aprender(?string $comercio, string $tipo, int $categoriaId): void
    {
        $clave = ReglaCategoria::normalizar($comercio);
        if ($clave === '') {
            return;
        }
        ReglaCategoria::updateOrCreate(['comercio' => $clave, 'tipo' => $tipo], ['categoria_id' => $categoriaId]);
    }
}
