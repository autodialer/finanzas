<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\ReglaCategoria;
use App\Models\Traspaso;
use App\Models\User;
use App\Services\PdfTexto;
use App\Services\SugeridorCategoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\AfirmeFixture;
use Tests\Fixtures\AfirmeTarjetaFixture;
use Tests\TestCase;

class ImportacionAfirmeTarjetaTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tarjeta;
    private Cuenta $debito;
    private Categoria $otros;
    private $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        $negocio = Negocio::create(['nombre' => 'Persona Ficticia']);
        $afirme  = Banco::create(['nombre' => 'AFIRME']);

        $this->debito  = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $afirme->id, 'tipo' => 'banco', 'nombre' => 'Débito Ficticia', 'numero' => '062580000111222333']);
        $this->tarjeta = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $afirme->id, 'tipo' => 'banco', 'nombre' => 'Tarjeta Ficticia', 'numero' => AfirmeTarjetaFixture::NUMERO_TARJETA]);
        $this->otros   = Categoria::create(['nombre' => 'Otros gastos', 'tipo' => 'gasto']);
        Categoria::create(['nombre' => 'Otros ingresos', 'tipo' => 'ingreso']);

        $this->pdf = new class extends PdfTexto {
            public string $texto = '';
            public function extraer(string $ruta): string
            {
                return $this->texto ?: AfirmeTarjetaFixture::texto();
            }
        };
        $this->app->instance(PdfTexto::class, $this->pdf);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function subir(?Cuenta $cuenta = null): array
    {
        $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => ($cuenta ?? $this->tarjeta)->id,
            'archivo'   => UploadedFile::fake()->create('estado.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('importacion-afirme.preview'));

        return session('importacion_afirme');
    }

    private function confirmarSugerencias(array $filas, array $cambios = []): void
    {
        $entrada = [];
        foreach ($filas as $i => $fila) {
            $entrada[$i] = array_merge([
                'tipo'              => $fila['tipo'],
                'concepto'          => $fila['concepto'],
                'categoria_id'      => $fila['categoria_id'] ?? $this->otros->id,
                'cuenta_destino_id' => $fila['cuenta_destino_id'],
            ], $cambios[$i] ?? []);
        }

        $this->post(route('importacion-afirme.confirmar'), ['filas' => $entrada])
            ->assertRedirect(route('gastos.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_la_cuenta_nunca_guarda_el_numero_completo_de_la_tarjeta(): void
    {
        $this->assertSame('9999', $this->tarjeta->fresh()->numero);
        $this->assertSame('062580000111222333', $this->debito->fresh()->numero); // las CLABE no se tocan
    }

    public function test_detecta_la_tarjeta_y_sugiere_cada_tipo(): void
    {
        Traspaso::create(['fecha' => '2026-01-15', 'cuenta_origen_id' => $this->debito->id, 'cuenta_destino_id' => $this->tarjeta->id, 'monto' => 500]);

        $importacion = $this->subir();
        $filas = $importacion['filas'];

        $this->assertSame('tarjeta', $importacion['estado']['tipo']);
        $this->assertSame(
            [
                ['2026-02-11', 'gasto', 50.0, 'nueva:Tienda de conveniencia'],
                ['2026-02-12', 'gasto', 450.0, 'nueva:Restaurantes y comida'],
                ['2026-02-15', 'traspaso', 1000.0, null],
                ['2026-02-20', 'gasto', 800.0, 'nueva:Gasolina'],
                ['2026-02-22', 'gasto', 120.25, 'nueva:Salud'],
                ['2026-03-01', 'gasto', 300.0, null],
            ],
            array_map(fn ($f) => [$f['fecha'], $f['tipo'], $f['monto'], $f['categoria_id']], $filas)
        );

        // El pago sale de la cuenta con la que se pagó la tarjeta antes
        $this->assertSame($this->debito->id, $filas[2]['cuenta_destino_id']);

        // Los meses sin intereses no son movimientos: se concilian con las compras a meses
        $this->assertSame(['TIENDA ELECTRONICA FICTICIA', 'LIBRERIA FALSA'], array_column($importacion['meses'], 'descripcion'));

        $this->get(route('importacion-afirme.preview'))
            ->assertOk()
            ->assertSee('Tarjeta de crédito')
            ->assertSee('terminación 9999')
            ->assertSee('LIBRERIA FALSA')
            ->assertDontSee(AfirmeTarjetaFixture::NUMERO_TARJETA);
    }

    public function test_pago_ya_registrado_en_5_dias_es_duplicado(): void
    {
        Traspaso::create(['fecha' => '2026-02-19', 'cuenta_origen_id' => $this->debito->id, 'cuenta_destino_id' => $this->tarjeta->id, 'monto' => 1000]);

        $pago = collect($this->subir()['filas'])->firstWhere('monto', 1000.0);

        $this->assertTrue($pago['duplicado']);
        $this->assertSame('ignorar', $pago['tipo']);
    }

    public function test_pago_registrado_fuera_de_5_dias_no_es_duplicado(): void
    {
        Traspaso::create(['fecha' => '2026-02-21', 'cuenta_origen_id' => $this->debito->id, 'cuenta_destino_id' => $this->tarjeta->id, 'monto' => 1000]);

        $pago = collect($this->subir()['filas'])->firstWhere('monto', 1000.0);

        $this->assertFalse($pago['duplicado']);
        $this->assertSame('traspaso', $pago['tipo']);
    }

    public function test_compra_capturada_a_mano_con_la_fecha_de_cargo_es_duplicado(): void
    {
        Gasto::create([
            'negocio_id' => $this->tarjeta->negocio_id, 'categoria_id' => $this->otros->id, 'cuenta_id' => $this->tarjeta->id,
            'monto' => 450, 'fecha' => '2026-02-13', 'concepto' => 'Comida', 'forma_pago' => 'tarjeta',
        ]);

        $filas = collect($this->subir()['filas']);

        $this->assertTrue($filas->firstWhere('monto', 450.0)['duplicado']);
        $this->assertSame(1, $filas->where('duplicado', true)->count());
    }

    public function test_no_permite_importar_si_no_cuadra(): void
    {
        $this->pdf->texto = AfirmeTarjetaFixture::texto(["Pagos y abonos\t- $1,000.00" => "Pagos y abonos\t- $2,000.00"]);

        $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => $this->tarjeta->id,
            'archivo'   => UploadedFile::fake()->create('estado.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('archivo');

        $this->assertNull(session('importacion_afirme'));
    }

    public function test_rechaza_un_pdf_que_no_es_de_afirme(): void
    {
        $this->pdf->texto = "Factura cualquiera\nTotal $ 100.00";

        $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => $this->tarjeta->id,
            'archivo'   => UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('archivo');
    }

    public function test_confirmar_crea_categorias_nuevas_y_aprende_reglas(): void
    {
        $filas = $this->subir()['filas'];
        $especial = Categoria::create(['nombre' => 'Tecnología personal', 'tipo' => 'gasto']);

        // La compra "TIENDA SIN CLAVE" (fila 5) se cambia a una categoría distinta a la sugerida
        $this->confirmarSugerencias($filas, [5 => ['categoria_id' => $especial->id]]);

        $this->assertSame(5, Gasto::where('cuenta_id', $this->tarjeta->id)->where('forma_pago', 'tarjeta')->count());
        $this->assertSame(1, Traspaso::where('cuenta_destino_id', $this->tarjeta->id)->count());
        $this->assertTrue(Categoria::where('nombre', 'Gasolina')->exists());
        $this->assertSame('Gasolina', Gasto::where('monto', 800)->first()->categoria->nombre);

        $this->assertSame($especial->id, ReglaCategoria::where('comercio', 'TIENDA SIN CLAVE')->value('categoria_id'));
        $this->assertSame(
            Categoria::where('nombre', 'Restaurantes y comida')->value('id'),
            ReglaCategoria::where('comercio', 'REST LA PRUEBA')->value('categoria_id')
        );
    }

    public function test_las_reglas_aprendidas_se_usan_en_la_siguiente_importacion(): void
    {
        $tecnologia = Categoria::create(['nombre' => 'Tecnología personal', 'tipo' => 'gasto']);
        ReglaCategoria::create(['comercio' => 'TIENDA SIN CLAVE', 'tipo' => 'gasto', 'categoria_id' => $tecnologia->id]);
        // Una regla gana sobre la palabra clave
        ReglaCategoria::create(['comercio' => 'OXXO CENTRO FICTICIO', 'tipo' => 'gasto', 'categoria_id' => $this->otros->id]);

        $filas = collect($this->subir()['filas']);

        $this->assertSame($tecnologia->id, $filas->firstWhere('monto', 300.0)['categoria_id']);
        $this->assertSame($this->otros->id, $filas->firstWhere('monto', 50.0)['categoria_id']);
    }

    public function test_el_gasto_se_guarda_en_el_negocio_elegido_y_se_recuerda(): void
    {
        $avion = Negocio::create(['nombre' => 'Avión Ficticio']);
        $filas = $this->subir()['filas'];

        // Por defecto se propone el negocio de la tarjeta
        $this->assertSame($this->tarjeta->negocio_id, $filas[5]['negocio_id']);

        // "TIENDA SIN CLAVE" (fila 5) se asigna a otro negocio
        $this->confirmarSugerencias($filas, [5 => ['negocio_id' => $avion->id]]);

        $this->assertSame($avion->id, Gasto::where('monto', 300)->value('negocio_id'));
        $this->assertSame($this->tarjeta->negocio_id, Gasto::where('monto', 800)->value('negocio_id'));
        $this->assertSame($avion->id, ReglaCategoria::where('comercio', 'TIENDA SIN CLAVE')->value('negocio_id'));
    }

    public function test_el_negocio_aprendido_se_sugiere_en_la_siguiente_importacion(): void
    {
        $avion = Negocio::create(['nombre' => 'Avión Ficticio']);
        ReglaCategoria::create(['comercio' => 'TIENDA SIN CLAVE', 'tipo' => 'gasto', 'categoria_id' => $this->otros->id, 'negocio_id' => $avion->id]);

        $filas = collect($this->subir()['filas']);

        $this->assertSame($avion->id, $filas->firstWhere('monto', 300.0)['negocio_id']);
        $this->assertSame($this->tarjeta->negocio_id, $filas->firstWhere('monto', 800.0)['negocio_id']);
    }

    public function test_las_reglas_tambien_aplican_a_la_cuenta_vision(): void
    {
        $proveedores = Categoria::create(['nombre' => 'Proveedores', 'tipo' => 'gasto']);
        ReglaCategoria::create(['comercio' => 'PROVEEDOR FICTICIO', 'tipo' => 'gasto', 'categoria_id' => $proveedores->id]);
        $this->pdf->texto = AfirmeFixture::texto();

        $filas = collect($this->subir($this->debito)['filas']);
        $spei = $filas->firstWhere('monto', 1500.0);

        $this->assertSame('vision', session('importacion_afirme')['estado']['tipo']);
        $this->assertSame($proveedores->id, $spei['categoria_id']);

        // Al confirmar, la cuenta Visión también enseña reglas (emisor del SPEI recibido → categoría de ingreso)
        $otrosIngresos = Categoria::where('tipo', 'ingreso')->value('id');
        // Préstamos y pago Amex no existen en esta prueba: se ignoran
        $this->confirmarSugerencias($filas->all(), [
            0 => ['tipo' => 'ignorar'], 1 => ['tipo' => 'ignorar'], 3 => ['tipo' => 'ignorar'],
            2 => ['categoria_id' => $otrosIngresos], 10 => ['categoria_id' => $proveedores->id],
        ]);
        $this->assertSame($otrosIngresos, ReglaCategoria::where('tipo', 'ingreso')->where('comercio', 'EMPRESA FICTICIA S A D')->value('categoria_id'));
    }

    public function test_palabras_clave(): void
    {
        $sugeridor = new SugeridorCategoria();

        $this->assertSame('Estacionamiento', $sugeridor->porPalabraClave('MITSPUG*EST FPPA 1 ARB'));
        $this->assertSame('Tienda de conveniencia', $sugeridor->porPalabraClave('OXXOTORRE LUZ'));
        $this->assertSame('Restaurantes y comida', $sugeridor->porPalabraClave('RESTAURANTS SR LATINO'));
        $this->assertSame('Salud', $sugeridor->porPalabraClave('OPE HOSP ANGELES'));
        $this->assertSame('Supermercado', $sugeridor->porPalabraClave('COSTCO MONTERREY'));
        $this->assertNull($sugeridor->porPalabraClave('ESTUDIO FOTOGRAFICO')); // "EST" solo como palabra completa
        $this->assertNull($sugeridor->porPalabraClave('SIGNATURE ROOM'));
    }
}
