<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\CompraMeses;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\ReglaCategoria;
use App\Models\User;
use App\Services\ConciliadorComprasMeses as C;
use App\Services\PdfTexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\AfirmeTarjetaFixture;
use Tests\TestCase;

// Compras a meses en el importador de la tarjeta Afirme. Todos los datos son ficticios.
class ImportacionMesesAfirmeTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tarjeta;
    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $negocio = Negocio::create(['nombre' => 'Persona Ficticia']);
        $afirme  = Banco::create(['nombre' => 'AFIRME']);
        $this->tarjeta   = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $afirme->id, 'tipo' => 'banco', 'nombre' => 'Tarjeta Ficticia', 'numero' => AfirmeTarjetaFixture::NUMERO_TARJETA]);
        $this->categoria = Categoria::create(['nombre' => 'Electrónica ficticia', 'tipo' => 'gasto']);

        $this->app->instance(PdfTexto::class, new class extends PdfTexto {
            public function extraer(string $ruta): string
            {
                return AfirmeTarjetaFixture::texto();
            }
        });

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function subir(): array
    {
        $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => $this->tarjeta->id,
            'archivo'   => UploadedFile::fake()->create('tarjeta.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('importacion-afirme.preview'));

        return session('importacion_afirme.meses');
    }

    // Plan de la librería: 6 meses de $500 desde el 11/12/2025 → el pago 3 cae en el periodo feb-mar 2026
    private function planLibreria(string $primerCargo = '2025-12-11'): CompraMeses
    {
        return CompraMeses::registrar($this->tarjeta, [
            'cuenta_id' => $this->tarjeta->id, 'descripcion' => 'Librería Falsa', 'monto_original' => 3000,
            'meses' => 6, 'mensualidad' => 500, 'fecha_compra' => '2025-12-01', 'fecha_primer_cargo' => $primerCargo,
        ], $this->categoria->id, false, auth()->id());
    }

    private function altaTienda(array $cambios = []): array
    {
        return array_merge([
            'accion' => 'alta', 'descripcion' => 'Tienda Electrónica Ficticia', 'categoria_id' => $this->categoria->id,
            'meses' => 12, 'mensualidad' => 1000, 'fecha_compra' => '2026-02-11', 'fecha_primer_cargo' => '2026-02-11',
        ], $cambios);
    }

    public function test_pago_1_sugiere_alta_y_sin_plan_ofrece_alta_con_fecha_estimada(): void
    {
        $meses = $this->subir();

        $this->assertSame([C::CREAR, C::SIN_PLAN], array_column($meses, 'accion'));
        $this->assertSame([12, 6], array_column($meses, 'total_pagos'));
        $this->assertSame(['meses' => 12, 'mensualidad' => 1000.0, 'fecha_primer_cargo' => '2026-02-11'],
            array_intersect_key($meses[0]['sugerencia'], array_flip(['meses', 'mensualidad', 'fecha_primer_cargo'])));
        $this->assertSame('2025-12-11', $meses[1]['sugerencia']['fecha_compra']);

        // El pago 1 ya no se importa como gasto suelto
        $this->assertNull(collect(session('importacion_afirme.filas'))->firstWhere('monto', 12000.0));

        $this->get(route('importacion-afirme.preview'))
            ->assertOk()
            ->assertSee('Compras a meses sin intereses')
            ->assertSee('Nueva (pago 1)')
            ->assertSee('Sin plan registrado')
            ->assertSee('name="meses[0][accion]"', false);
    }

    public function test_verifica_el_plan_existente_y_avisa_si_no_coincide(): void
    {
        $plan = $this->planLibreria();
        $meses = $this->subir();
        $this->assertSame(C::VERIFICADA, $meses[1]['accion']);
        $this->assertSame($plan->id, $meses[1]['compra_id']);

        $plan->gasto->delete();
        $this->planLibreria('2026-01-11'); // según este plan en el periodo toca el pago 2
        $meses = $this->subir();
        $this->assertSame(C::DIFERENCIA, $meses[1]['accion']);
        $this->assertSame([
            'El banco indica el pago 3; según el plan en este periodo toca el pago 2.',
        ], $meses[1]['avisos']);
    }

    public function test_confirmar_da_de_alta_gasto_y_plan_y_aprende_la_categoria(): void
    {
        $this->subir();

        $this->post(route('importacion-afirme.confirmar'), [
            'filas' => [],
            'meses' => [0 => $this->altaTienda(), 1 => ['accion' => 'ignorar']],
        ])->assertRedirect(route('gastos.index'))->assertSessionHasNoErrors();

        $compra = CompraMeses::sole();
        $this->assertSame([12000.0, 12, '2026-02-11'], [(float) $compra->monto_original, $compra->meses, $compra->fecha_primer_cargo->toDateString()]);
        $this->assertEquals(12000, $compra->gasto->monto);
        $this->assertSame([$this->tarjeta->id, 'tarjeta', 'Importado de tarjeta.pdf'], [$compra->gasto->cuenta_id, $compra->gasto->forma_pago, $compra->gasto->notas]);
        $this->assertSame($this->categoria->id, ReglaCategoria::where('tipo', 'gasto')->value('categoria_id'));

        // Al volver a subirlo la compra ya está registrada y coincide
        $this->assertSame(C::VERIFICADA, $this->subir()[0]['accion']);
    }

    public function test_un_error_en_las_compras_a_meses_no_guarda_nada(): void
    {
        $this->subir();
        $filas = session('importacion_afirme.filas');

        $this->post(route('importacion-afirme.confirmar'), [
            'filas' => [0 => ['tipo' => 'gasto', 'concepto' => $filas[0]['concepto'], 'categoria_id' => $this->categoria->id]],
            'meses' => [0 => $this->altaTienda(['mensualidad' => 700])],
        ])->assertRedirect(route('importacion-afirme.preview'))->assertSessionHasErrors('meses.0');

        $this->assertSame(0, Gasto::count());
        $this->assertSame(0, CompraMeses::count());
    }
}
