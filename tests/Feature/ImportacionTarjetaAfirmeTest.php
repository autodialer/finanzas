<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\CompraMeses;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\User;
use App\Services\AfirmeEstadoCuentaParser;
use App\Services\ConciliadorComprasMeses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\AfirmeTarjetaFixture;
use Tests\TestCase;

// Todos los datos son ficticios.
class ImportacionTarjetaAfirmeTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tarjeta;
    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $negocio = Negocio::create(['nombre' => 'Persona Ficticia']);
        $afirme  = Banco::create(['nombre' => 'AFIRME']);
        $this->tarjeta   = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $afirme->id, 'tipo' => 'banco', 'nombre' => 'TC Afirme Ficticia', 'numero' => '9999']);
        $this->categoria = Categoria::create(['nombre' => 'Compras ficticias', 'tipo' => 'gasto']);

        // El parser lee el texto ficticio en lugar de un PDF real
        $this->app->instance(AfirmeEstadoCuentaParser::class, new class extends AfirmeEstadoCuentaParser {
            public function extraerTexto(string $ruta): string
            {
                return AfirmeTarjetaFixture::texto();
            }
        });

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function subirPdf()
    {
        return $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => $this->tarjeta->id,
            'archivo'   => UploadedFile::fake()->create('tarjeta.pdf', 10, 'application/pdf'),
        ]);
    }

    private function planExistente(string $primerCargo = '2026-02-15'): CompraMeses
    {
        return CompraMeses::registrar($this->tarjeta, [
            'cuenta_id' => $this->tarjeta->id, 'descripcion' => 'Comercio Ficticio Uno', 'monto_original' => 12000,
            'meses' => 12, 'mensualidad' => 1000, 'fecha_compra' => '2026-02-01', 'fecha_primer_cargo' => $primerCargo,
        ], $this->categoria->id, false, auth()->id());
    }

    private function acciones(): array
    {
        return array_column(session('importacion_afirme_tarjeta.filas'), 'accion');
    }

    public function test_concilia_las_compras_a_meses_del_estado_de_cuenta(): void
    {
        $plan = $this->planExistente();

        $this->subirPdf()->assertRedirect(route('importacion-afirme.tarjeta'));
        $this->assertSame([ConciliadorComprasMeses::VERIFICADA, ConciliadorComprasMeses::CREAR, ConciliadorComprasMeses::SIN_PLAN], $this->acciones());

        $filas = session('importacion_afirme_tarjeta.filas');
        $this->assertSame($plan->id, $filas[0]['compra_id']);
        $this->assertSame('2025-09-15', $filas[2]['sugerencia']['fecha_compra']);
        $this->assertSame(12, $filas[2]['sugerencia']['meses']);

        $this->get(route('importacion-afirme.tarjeta'))
            ->assertOk()
            ->assertSee('Coincide con el plan')
            ->assertSee('Nueva (pago 1)')
            ->assertSee('Sin plan registrado')
            ->assertDontSee('no termina en los mismos dígitos');
    }

    public function test_avisa_cuando_el_plan_no_coincide(): void
    {
        $this->planExistente('2026-01-15'); // según el plan, en este periodo toca el pago 3

        $this->subirPdf();
        $fila = session('importacion_afirme_tarjeta.filas')[0];

        $this->assertSame(ConciliadorComprasMeses::DIFERENCIA, $fila['accion']);
        $this->assertSame([
            'El banco indica el pago 2; según el plan en este periodo toca el pago 3.',
        ], $fila['avisos']);
    }

    public function test_confirmar_da_de_alta_gasto_y_plan(): void
    {
        $this->planExistente();
        $this->subirPdf();

        $alta = fn (array $extra) => ['accion' => 'alta', 'categoria_id' => $this->categoria->id] + $extra;
        $this->post(route('importacion-afirme.tarjeta.confirmar'), ['filas' => [
            0 => ['accion' => 'alta'], // ya tiene plan: se ignora
            1 => $alta(['descripcion' => 'Tienda Ficticia Dos', 'meses' => 6, 'mensualidad' => 1000, 'fecha_compra' => '2026-03-15', 'fecha_primer_cargo' => '2026-03-15']),
            2 => $alta(['descripcion' => 'Servicio Ficticio Tres', 'meses' => 12, 'mensualidad' => 300, 'fecha_compra' => '2025-09-15', 'fecha_primer_cargo' => '2025-09-15']),
        ]])->assertRedirect(route('compras-meses.index'));

        $this->assertSame(3, CompraMeses::count());
        $this->assertSame(3, Gasto::count());

        $servicio = CompraMeses::where('descripcion', 'Servicio Ficticio Tres')->firstOrFail();
        $this->assertEquals(3600, $servicio->gasto->monto);
        $this->assertSame('2025-09-15', $servicio->gasto->fecha);
        $this->assertSame('Importado de tarjeta.pdf', $servicio->gasto->notas);

        // Al volver a subir el mismo estado de cuenta todo coincide y no se duplica nada
        $this->subirPdf();
        $this->assertSame(array_fill(0, 3, ConciliadorComprasMeses::VERIFICADA), $this->acciones());
    }

    public function test_no_guarda_nada_si_una_fila_tiene_errores(): void
    {
        $this->subirPdf();

        $this->post(route('importacion-afirme.tarjeta.confirmar'), ['filas' => [
            0 => ['accion' => 'alta', 'categoria_id' => $this->categoria->id, 'descripcion' => 'Uno', 'meses' => 12, 'mensualidad' => 1000, 'fecha_compra' => '2026-02-15', 'fecha_primer_cargo' => '2026-02-15'],
            1 => ['accion' => 'alta', 'descripcion' => 'Sin categoría', 'meses' => 6, 'mensualidad' => 1000, 'fecha_compra' => '2026-03-15', 'fecha_primer_cargo' => '2026-03-15'],
        ]])->assertRedirect(route('importacion-afirme.tarjeta'))->assertSessionHasErrors('filas.1');

        $this->assertSame(0, CompraMeses::count());
        $this->assertSame(0, Gasto::count());
    }

    public function test_capturista_no_importa_a_tarjeta_de_otro_negocio(): void
    {
        $otro = Negocio::create(['nombre' => 'Otro Negocio Ficticio']);
        $this->actingAs(User::factory()->create(['role' => 'capturista', 'negocio_id' => $otro->id]));

        $this->subirPdf()->assertForbidden();
        $this->assertNull(session('importacion_afirme_tarjeta'));
    }
}
