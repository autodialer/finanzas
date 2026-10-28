<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\ReglaCategoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea reglas de categoría (comercio → categoría) a partir de gastos ya
 * capturados, para que el importador de estados de cuenta las sugiera.
 *
 * El comercio es el concepto del gasto sin el sufijo entre paréntesis que
 * agrega el importador ("(USD 19.71)", "(mensualidad 5 de 12)"). Si un comercio
 * tiene varias categorías gana la más usada; en empate, la más reciente.
 * Las reglas que ya existen (elegidas al importar) no se tocan, y las cuentas
 * se procesan en el orden dado: la primera tiene prioridad.
 *
 * Se ignoran los gastos creados en lote (5 o más en el mismo segundo) que no
 * se editaron después: el importador viejo de Excel les ponía a todos la misma
 * categoría, así que no es una elección por comercio. Los del importador de PDF
 * ya dejan su regla al confirmar. --incluir-lotes los toma en cuenta.
 *
 * --tabla permite leer de una copia restaurada de un respaldo.
 */
class AprenderCategoriasHistorial extends Command
{
    private const MINIMO_LOTE = 5;

    protected $signature = 'categorias:aprender-historial
        {cuentas* : Ids de las cuentas cuyos gastos se usan, en orden de prioridad}
        {--tabla=gastos : Tabla de donde leer los gastos}
        {--incluir-lotes : Usar también los gastos creados en lote sin editar}
        {--simular : Solo mostrar lo que se haría}';

    protected $description = 'Aprende reglas de categoría por comercio a partir de gastos históricos';

    public function handle(): int
    {
        $tabla = $this->option('tabla');
        if (!Schema::hasTable($tabla) || !Schema::hasColumns($tabla, ['cuenta_id', 'concepto', 'categoria_id', 'fecha', 'created_at', 'updated_at'])) {
            $this->error("La tabla \"{$tabla}\" no existe o no tiene las columnas de gastos.");
            return self::FAILURE;
        }

        $categoriasGasto = Categoria::whereIn('tipo', ['gasto', 'ambos'])->pluck('id')->flip();
        $existentes = ReglaCategoria::where('tipo', 'gasto')->pluck('comercio')->flip();
        $nuevas = [];
        $omitidas = 0;

        foreach ($this->argument('cuentas') as $cuentaId) {
            $todos = DB::table($tabla)->where('cuenta_id', $cuentaId)->whereNotNull('categoria_id')->get(['concepto', 'categoria_id', 'fecha', 'created_at', 'updated_at']);
            $gastos = $this->option('incluir-lotes') ? $todos : $this->sinLotesSinEditar($todos);

            foreach ($gastos->groupBy(fn ($g) => self::comercio($g->concepto)) as $comercio => $delComercio) {
                if ($comercio === '' || isset($existentes[$comercio]) || isset($nuevas[$comercio])) {
                    continue;
                }
                $categoriaId = $this->masUsada($delComercio);
                if (!isset($categoriasGasto[$categoriaId])) {
                    $omitidas++;
                    continue;
                }
                $nuevas[$comercio] = $categoriaId;
            }
            $this->line("Cuenta {$cuentaId}: {$todos->count()} gastos con categoría, se usan {$gastos->count()}"
                . ($gastos->count() < $todos->count() ? ' (los demás se crearon en lote y no se editaron)' : '') . '.');
        }

        if (!$this->option('simular')) {
            DB::transaction(function () use ($nuevas) {
                foreach ($nuevas as $comercio => $categoriaId) {
                    ReglaCategoria::create(['comercio' => $comercio, 'tipo' => 'gasto', 'categoria_id' => $categoriaId]);
                }
            });
        }

        $this->info(($this->option('simular') ? 'Se crearían ' : 'Reglas creadas: ') . count($nuevas)
            . ". Ya existían: {$existentes->count()}. Omitidas por categoría que no es de gasto: {$omitidas}.");

        return self::SUCCESS;
    }

    public static function comercio(?string $concepto): string
    {
        return ReglaCategoria::normalizar(preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $concepto));
    }

    private function sinLotesSinEditar($gastos)
    {
        $porLote = $gastos->countBy(fn ($g) => (string) $g->created_at);

        return $gastos->filter(fn ($g) => $porLote[(string) $g->created_at] < self::MINIMO_LOTE
            || ($g->updated_at && $g->created_at && strtotime($g->updated_at) > strtotime($g->created_at) + 5));
    }

    private function masUsada($gastos): int
    {
        return (int) $gastos->groupBy('categoria_id')
            ->sortByDesc(fn ($grupo) => [$grupo->count(), $grupo->max('fecha')])
            ->keys()
            ->first();
    }
}
