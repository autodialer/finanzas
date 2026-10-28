<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\CompraMeses;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Negocio;
use App\Models\ReglaCategoria;
use App\Models\Traspaso;
use App\Models\User;
use App\Services\ConciliadorComprasMeses as C;
use App\Services\PdfTexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\AmexFixture;
use Tests\TestCase;

// Importación del estado de cuenta American Express. Todos los datos son ficticios.
class ImportacionAmexTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $amex;
    private Cuenta $debito;
    private Categoria $otros;
    private Categoria $comisiones;
    private Categoria $devoluciones;
    private $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        $negocio = Negocio::create(['nombre' => 'Persona Ficticia']);
        $amex    = Banco::create(['nombre' => 'American Express']);
        $afirme  = Banco::create(['nombre' => 'AFIRME']);

        $this->amex   = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $amex->id, 'tipo' => 'banco', 'nombre' => 'Amex Ficticia', 'numero' => '01111']);
        $this->debito = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $afirme->id, 'tipo' => 'banco', 'nombre' => 'Débito Ficticia']);
        $this->otros        = Categoria::create(['nombre' => 'Otros gastos', 'tipo' => 'gasto']);
        $this->comisiones   = Categoria::create(['nombre' => 'Comisiones bancarias', 'tipo' => 'gasto']);
        $this->devoluciones = Categoria::create(['nombre' => 'Devoluciones', 'tipo' => 'ingreso']);

        // El último pago a la tarjeta salió de la cuenta de débito: se sugiere como origen
        Traspaso::create(['fecha' => '2025-11-10', 'cuenta_origen_id' => $this->debito->id, 'cuenta_destino_id' => $this->amex->id, 'monto' => 7000, 'concepto' => 'Pago Amex']);

        $this->pdf = new class extends PdfTexto {
            public string $texto = '';
            public function extraer(string $ruta): string
            {
                return $this->texto ?: AmexFixture::texto();
            }
        };
        $this->app->instance(PdfTexto::class, $this->pdf);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function subir(?Cuenta $cuenta = null)
    {
        return $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => ($cuenta ?? $this->amex)->id,
            'archivo'   => UploadedFile::fake()->create('AE_ficticio.pdf', 10, 'application/pdf'),
        ]);
    }

    private function fila(string $concepto): array
    {
        return collect(session('importacion_afirme.filas'))->first(fn ($f) => str_starts_with($f['concepto'], $concepto));
    }

    // Mensualidades de la librería registradas como gasto cada mes antes de este importador
    // (una con centavos de diferencia) y un gasto de otro comercio por un monto parecido
    private function mensualidadesAnteriores(): void
    {
        $gastos = [['2025-08-23', 250.40, 'LIBRERIA INVENTADA MONTERREY'], ['2025-09-23', 250, 'LIBRERIA INVENTADA MONTERREY'],
            ['2025-10-23', 250, 'LIBRERIA INVENTADA MONTERREY'], ['2025-11-23', 250, 'LIBRERIA INVENTADA MONTERREY'], ['2025-11-24', 250.40, 'OTRO COMERCIO']];
        foreach ($gastos as [$fecha, $monto, $concepto]) {
            Gasto::create(['negocio_id' => $this->amex->negocio_id, 'categoria_id' => $this->otros->id, 'cuenta_id' => $this->amex->id,
                'monto' => $monto, 'fecha' => $fecha, 'concepto' => $concepto, 'forma_pago' => 'tarjeta']);
        }
    }

    public function test_detecta_amex_y_clasifica_cada_movimiento(): void
    {
        $this->subir()->assertRedirect(route('importacion-afirme.preview'));

        $filas = session('importacion_afirme.filas');
        $this->assertEquals(['gasto' => 8, 'traspaso' => 2, 'ingreso' => 1], collect($filas)->countBy('tipo')->all());
        $this->assertSame(0, collect($filas)->where('duplicado', true)->count());
        // Las mensualidades no son filas: van a la tabla de meses sin intereses
        $this->assertNull(collect($filas)->first(fn ($f) => str_contains($f['detalle'], 'CARGO 0')));

        $pago = $this->fila('Pago a tarjeta American Express');
        $this->assertSame(['traspaso', $this->debito->id], [$pago['tipo'], $pago['cuenta_destino_id']]);

        $comision = $this->fila('CUOTA ANUAL');
        $this->assertSame([1160.0, 160.0, $this->comisiones->id], [$comision['monto'], $comision['monto_iva'], $comision['categoria_id']]);

        $this->assertSame([180.0, '1111'], [$this->fila('SERVICIO NUBE FALSO SAN FRANCISCO (USD 10.00)')['monto'], $this->fila('SERVICIO NUBE')['tarjeta']]);
        $this->assertNotNull($this->fila('HOTEL FICTICIO MADRID MADRID (EUR 1,234.56)'));
        $this->assertNotNull($this->fila('TIENDA JAPON FALSA TOKIO (JPY 2,500)'));
        $this->assertSame('2222', $this->fila('FARMACIA')['tarjeta']);
        $this->assertSame('ingreso', $this->fila('DEVOLUCION')['tipo']);

        $this->get(route('importacion-afirme.preview'))
            ->assertOk()
            ->assertSee('American Express')
            ->assertSee('Tarjeta ···2222')
            ->assertSee('Cuadra')
            ->assertDontSee('no coincide con la tarjeta');
    }

    public function test_cuenta_que_no_es_amex_se_rechaza(): void
    {
        $this->subir($this->debito)->assertSessionHasErrors('archivo');
        $this->assertNull(session('importacion_afirme'));
    }

    public function test_si_no_cuadra_muestra_el_error_y_no_permite_importar(): void
    {
        $this->pdf->texto = AmexFixture::texto(["\n1,000.00\n" => "\n1,100.00\n"]);

        $this->subir()->assertSessionHasErrors('archivo');
        $this->assertStringContainsString('Total de las transacciones en $', session('errors')->first('archivo'));
        $this->assertNull(session('importacion_afirme'));
    }

    public function test_pagos_partidos_que_suman_un_traspaso_registrado_son_duplicados(): void
    {
        // Capturado a mano (o importado de "DOMI-AMERICAN EXPRESS" en Afirme Visión) como un solo traspaso
        Traspaso::create(['fecha' => '2026-01-09', 'cuenta_origen_id' => $this->debito->id, 'cuenta_destino_id' => $this->amex->id, 'monto' => 5000, 'concepto' => 'Pago American Express (domiciliación)']);

        $this->subir();

        $pagos = collect(session('importacion_afirme.filas'))->where('naturaleza', 'deposito')->where('concepto', 'Pago a tarjeta American Express');
        $this->assertCount(2, $pagos);
        $this->assertTrue($pagos->every(fn ($f) => $f['duplicado'] && $f['tipo'] === 'ignorar'));
        $this->assertStringContainsString('suman un traspaso', $pagos->first()['motivo']);
    }

    public function test_pago_registrado_por_el_mismo_monto_es_duplicado(): void
    {
        Traspaso::create(['fecha' => '2026-01-11', 'cuenta_origen_id' => $this->debito->id, 'cuenta_destino_id' => $this->amex->id, 'monto' => 3000]);

        $this->subir();

        $pagos = collect(session('importacion_afirme.filas'))->where('concepto', 'Pago a tarjeta American Express')->keyBy('monto');
        $this->assertTrue($pagos[3000.0]['duplicado']);
        $this->assertFalse($pagos[2000.0]['duplicado']);
    }

    public function test_gasto_capturado_antes_con_un_dia_de_diferencia_es_duplicado(): void
    {
        Gasto::create(['negocio_id' => $this->amex->negocio_id, 'categoria_id' => $this->otros->id, 'cuenta_id' => $this->amex->id,
            'monto' => 1000, 'fecha' => '2025-12-25', 'concepto' => 'TIENDA FICTICIA UNO', 'forma_pago' => 'tarjeta']);

        $this->subir();

        $this->assertTrue($this->fila('TIENDA FICTICIA UNO')['duplicado']);
        $this->assertSame(1, collect(session('importacion_afirme.filas'))->where('duplicado', true)->count());
    }

    public function test_meses_sin_intereses_como_en_afirme(): void
    {
        $this->subir();
        $meses = session('importacion_afirme.meses');

        $this->assertSame([C::SIN_PLAN, C::CREAR], array_column($meses, 'accion'));
        $this->assertSame(['ignorar', 'alta'], array_column($meses, 'accion_sugerida'));
        // La fecha de compra viene en el estado de cuenta
        $this->assertEquals(['fecha_compra' => '2025-12-20', 'fecha_primer_cargo' => '2025-12-23', 'meses' => 3, 'mensualidad' => 300.0],
            array_intersect_key($meses[1]['sugerencia'], array_flip(['fecha_compra', 'fecha_primer_cargo', 'meses', 'mensualidad'])));
        // Pago 5: el primer cargo estimado (23-ago) no puede ser antes de la compra (10-ago)
        $this->assertSame(['2025-08-10', '2025-08-23'], [$meses[0]['sugerencia']['fecha_compra'], $meses[0]['sugerencia']['fecha_primer_cargo']]);
    }

    public function test_mensualidades_que_ya_se_registraban_como_gasto_siguen_igual(): void
    {
        $this->mensualidadesAnteriores();

        $this->subir();
        $libreria = session('importacion_afirme.meses')[0];

        $this->assertSame([4, false, 'mensualidad'], [$libreria['mensualidades_previas'], $libreria['mensualidad_registrada'], $libreria['accion_sugerida']]);
        $this->get(route('importacion-afirme.preview'))->assertSee('Registrar solo esta mensualidad como gasto');
    }

    public function test_confirmar_guarda_todo_y_al_reimportar_todo_es_duplicado(): void
    {
        $this->mensualidadesAnteriores();
        $this->subir();

        $filas = collect(session('importacion_afirme.filas'))->map(fn ($f) => [
            'tipo'              => $f['tipo'],
            'concepto'          => $f['concepto'],
            'categoria_id'      => $f['tipo'] === 'ingreso' ? $this->devoluciones->id : ($f['categoria_id'] ?? $this->otros->id),
            'cuenta_destino_id' => $f['cuenta_destino_id'],
        ])->all();
        $meses = [
            ['accion' => 'mensualidad', 'descripcion' => 'Librería Inventada', 'categoria_id' => $this->otros->id],
            ['accion' => 'alta', 'descripcion' => 'Electrónica Falsa', 'categoria_id' => $this->otros->id,
                'meses' => 3, 'mensualidad' => 300, 'fecha_compra' => '2025-12-20', 'fecha_primer_cargo' => '2025-12-23'],
        ];

        $this->post(route('importacion-afirme.confirmar'), ['filas' => $filas, 'meses' => $meses])
            ->assertRedirect(route('gastos.index'))
            ->assertSessionHas('exito', fn ($m) => str_contains($m, 'American Express') && str_contains($m, '1 mensualidades como gasto'));

        $comision = Gasto::where('concepto', 'CUOTA ANUAL MENSUALIDAD 01 DE 03')->first();
        $this->assertSame([1160.0, 160.0, true], [(float) $comision->monto, (float) $comision->monto_iva, (bool) $comision->tiene_iva]);

        $nube = Gasto::where('concepto', 'like', 'SERVICIO NUBE%')->first();
        $this->assertSame(['SERVICIO NUBE FALSO SAN FRANCISCO (USD 10.00)', 180.0, 'tarjeta'], [$nube->concepto, (float) $nube->monto, $nube->forma_pago]);
        $this->assertStringContainsString('Tarjeta ···1111', Gasto::where('concepto', 'like', 'TIENDA FICTICIA%')->value('notas'));
        $this->assertStringContainsString('Tarjeta ···2222', Gasto::where('concepto', 'like', 'FARMACIA%')->value('notas'));

        $mensualidad = Gasto::where('concepto', 'Librería Inventada (mensualidad 5 de 12)')->first();
        $this->assertSame([250.0, '2025-12-23', 'MSI005'], [(float) $mensualidad->monto, substr((string) $mensualidad->fecha, 0, 10), $mensualidad->referencia]);

        $plan = CompraMeses::where('cuenta_id', $this->amex->id)->sole();
        $this->assertSame([900.0, 3], [(float) $plan->monto_original, (int) $plan->meses]);

        $this->assertSame(2, Traspaso::where('cuenta_destino_id', $this->amex->id)->where('cuenta_origen_id', $this->debito->id)->where('fecha', '2026-01-10')->count());
        $this->assertSame(50.0, (float) Ingreso::where('cuenta_id', $this->amex->id)->value('monto'));
        $this->assertNotNull(ReglaCategoria::where('comercio', 'FARMACIA FICTICIA')->first());

        // Mismo PDF otra vez: nada nuevo que guardar
        $this->subir();
        $this->assertTrue(collect(session('importacion_afirme.filas'))->every(fn ($f) => $f['duplicado']));
        $meses = session('importacion_afirme.meses');
        $this->assertSame([true, 'ignorar'], [$meses[0]['mensualidad_registrada'], $meses[0]['accion_sugerida']]);
        $this->assertSame(C::VERIFICADA, $meses[1]['accion']);
    }
}
