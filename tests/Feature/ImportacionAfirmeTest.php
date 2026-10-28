<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Ingreso;
use App\Models\Negocio;
use App\Models\PagoPrestamo;
use App\Models\Prestamo;
use App\Models\Traspaso;
use App\Models\User;
use App\Services\AfirmeClasificador;
use App\Services\AfirmeEstadoCuentaParser;
use App\Services\PdfTexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\AfirmeFixture;
use Tests\TestCase;

class ImportacionAfirmeTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $cuenta;
    private Cuenta $amex;
    private Prestamo $prestamo;
    private Categoria $comisiones;
    private Categoria $otrosGastos;
    private Categoria $otrosIngresos;

    protected function setUp(): void
    {
        parent::setUp();

        $personal = Negocio::create(['nombre' => 'Persona Ficticia']);
        $afirme   = Banco::create(['nombre' => 'AFIRME']);
        $amex     = Banco::create(['nombre' => 'American Express']);

        $this->cuenta = Cuenta::create(['negocio_id' => $personal->id, 'banco_id' => $afirme->id, 'tipo' => 'banco', 'nombre' => 'Afirme Ficticia', 'numero' => '062580000111222333']);
        $this->amex   = Cuenta::create(['negocio_id' => $personal->id, 'banco_id' => $amex->id, 'tipo' => 'banco', 'nombre' => 'Amex Ficticia']);
        $this->prestamo = Prestamo::create([
            'negocio_id' => $personal->id, 'banco_id' => $afirme->id, 'tipo' => 'auto',
            'concepto' => 'Crédito auto ficticio', 'monto_original' => 100000, 'fecha_inicio' => '2026-01-01',
        ]);
        $this->comisiones    = Categoria::create(['nombre' => 'Finanzas - Bancos, intereses, comisiones', 'tipo' => 'gasto']);
        $this->otrosGastos   = Categoria::create(['nombre' => 'Otros gastos', 'tipo' => 'gasto']);
        $this->otrosIngresos = Categoria::create(['nombre' => 'Otros ingresos', 'tipo' => 'ingreso']);

        // Se lee el texto ficticio en lugar de un PDF real
        $this->app->instance(PdfTexto::class, new class extends PdfTexto {
            public string $texto = '';
            public function extraer(string $ruta): string
            {
                return $this->texto ?: AfirmeFixture::texto();
            }
        });

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function filas(): array
    {
        $estado = (new AfirmeEstadoCuentaParser())->parsearTexto(AfirmeFixture::texto());
        return (new AfirmeClasificador($this->cuenta))->clasificar($estado['movimientos']);
    }

    private function subirPdf()
    {
        return $this->post(route('importacion-afirme.analizar'), [
            'cuenta_id' => $this->cuenta->id,
            'archivo'   => UploadedFile::fake()->create('estado.pdf', 10, 'application/pdf'),
        ]);
    }

    public function test_sugiere_el_tipo_de_cada_movimiento(): void
    {
        $filas = $this->filas();
        $resumen = array_map(fn ($f) => [$f['tipo'], $f['monto']], $filas);

        $this->assertSame([
            ['prestamo', 1000.0],
            ['prestamo', 2000.0],
            ['ingreso', 10000.0],
            ['traspaso', 5000.0],
            ['ignorar', 3000.0],   // SPEI enviado...
            ['ignorar', 6.0],      // ...su comisión
            ['ignorar', 0.96],     // ...su IVA
            ['ignorar', 3000.0],   // devolución AFIRMENET
            ['ignorar', 6.0],      // devolución de comisión
            ['ignorar', 0.96],     // devolución de IVA
            ['gasto', 1500.0],
            ['gasto', 2.32],       // comisión + IVA juntos
            ['gasto', 29.0],       // esquema de seguridad + IVA
        ], $resumen);

        $this->assertSame(['interes', 'capital'], [$filas[0]['prestamo_tipo'], $filas[1]['prestamo_tipo']]);
        $this->assertSame($this->prestamo->id, $filas[0]['prestamo_id']);
        $this->assertSame($this->amex->id, $filas[3]['cuenta_destino_id']);

        $this->assertSame('Comisiones bancarias', $filas[11]['concepto']);
        $this->assertSame(0.32, $filas[11]['monto_iva']);
        $this->assertSame(4.0, $filas[12]['monto_iva']);
        $this->assertSame($this->comisiones->id, $filas[12]['categoria_id']);

        $this->assertSame('SPEI de EMPRESA FICTICIA S.A. D — Honorarios marzo', $filas[2]['concepto']);
        $this->assertSame('SPEI a Proveedor Ficticio — Factura 42', $filas[10]['concepto']);
    }

    public function test_marca_duplicados_por_fecha_referencia_y_monto(): void
    {
        Ingreso::create([
            'negocio_id' => $this->cuenta->negocio_id, 'categoria_id' => $this->otrosIngresos->id, 'cuenta_id' => $this->cuenta->id,
            'monto' => 10000, 'referencia' => '123456', 'fecha' => '2026-03-05', 'concepto' => 'Ya capturado', 'forma_pago' => 'transferencia',
        ]);
        // Misma fecha y monto pero otra referencia: no es duplicado
        Gasto::create([
            'negocio_id' => $this->cuenta->negocio_id, 'categoria_id' => $this->otrosGastos->id, 'cuenta_id' => $this->cuenta->id,
            'monto' => 1500, 'referencia' => '999', 'fecha' => '2026-03-15', 'concepto' => 'Otro', 'forma_pago' => 'transferencia',
        ]);

        $filas = $this->filas();

        $this->assertTrue($filas[2]['duplicado']);
        $this->assertSame('ignorar', $filas[2]['tipo']);
        $this->assertFalse($filas[10]['duplicado']);
        $this->assertSame(1, collect($filas)->where('duplicado', true)->count());
    }

    public function test_pago_amex_ya_registrado_como_traspaso_sin_referencia_es_duplicado(): void
    {
        // Creado al importar el estado de cuenta Amex (sin referencia), dos días después
        Traspaso::create(['fecha' => '2026-03-08', 'cuenta_origen_id' => $this->cuenta->id, 'cuenta_destino_id' => $this->amex->id, 'monto' => 5000]);

        $filas = $this->filas();

        $this->assertTrue($filas[3]['duplicado']);
        $this->assertSame('ignorar', $filas[3]['tipo']);
        $this->assertStringContainsString('traspaso a American Express', $filas[3]['motivo']);
        $this->assertSame(1, collect($filas)->where('duplicado', true)->count());
    }

    public function test_pago_amex_no_se_confunde_con_otro_traspaso(): void
    {
        $otra = Cuenta::create(['negocio_id' => $this->cuenta->negocio_id, 'banco_id' => $this->cuenta->banco_id, 'tipo' => 'banco', 'nombre' => 'Otra Ficticia']);
        // Mismo monto, pero a otra cuenta; o a Amex fuera de los ±5 días
        Traspaso::create(['fecha' => '2026-03-06', 'cuenta_origen_id' => $this->cuenta->id, 'cuenta_destino_id' => $otra->id, 'monto' => 5000]);
        Traspaso::create(['fecha' => '2026-03-20', 'cuenta_origen_id' => $this->cuenta->id, 'cuenta_destino_id' => $this->amex->id, 'monto' => 5000]);

        $this->assertFalse($this->filas()[3]['duplicado']);
    }

    public function test_pagos_amex_del_dia_que_suman_un_traspaso_capturado_son_duplicados(): void
    {
        Traspaso::create(['fecha' => '2026-07-06', 'cuenta_origen_id' => $this->cuenta->id, 'cuenta_destino_id' => $this->amex->id, 'monto' => 123860.40, 'concepto' => 'Pago American Express']);

        $domi = fn (float $monto, string $ref) => ['fecha' => '2026-07-06', 'naturaleza' => 'retiro', 'referencia' => $ref, 'monto' => $monto,
            'descripcion' => "DOMI-AMERICAN EXPRESS CO MEXICO SA DE CV W000000000{$ref}"];
        $filas = (new AfirmeClasificador($this->cuenta))->clasificar([$domi(49900, '001'), $domi(49800, '002'), $domi(24160.40, '003')]);

        $this->assertSame([true, true, true], array_column($filas, 'duplicado'));
        $this->assertStringContainsString('los 3 pagos del día suman', $filas[0]['motivo']);
    }

    public function test_vista_previa_no_guarda_nada(): void
    {
        $this->subirPdf()->assertRedirect(route('importacion-afirme.preview'));

        $this->get(route('importacion-afirme.preview'))
            ->assertOk()
            ->assertSee('Cuadra')
            ->assertSee('Comisiones bancarias')
            ->assertSee('SPEI a Proveedor Ficticio');

        $this->assertSame(0, Gasto::count() + Ingreso::count() + Traspaso::count());
    }

    public function test_no_permite_importar_si_no_cuadra(): void
    {
        $this->app->make(PdfTexto::class)->texto = AfirmeFixture::texto(["Saldo al corte\t$ 50,468.68" => "Saldo al corte\t$ 1.00"]);

        $this->subirPdf()->assertRedirect()->assertSessionHasErrors('archivo');
        $this->assertNull(session('importacion_afirme'));
    }

    public function test_confirmar_guarda_cada_tipo_en_la_cuenta_elegida(): void
    {
        $this->subirPdf();
        $filas = session('importacion_afirme')['filas'];

        $entrada = [];
        foreach ($filas as $i => $fila) {
            $entrada[$i] = [
                'tipo'              => $fila['tipo'],
                'concepto'          => $fila['concepto'],
                'categoria_id'      => $fila['categoria_id'] ?? ($fila['tipo'] === 'ingreso' ? $this->otrosIngresos->id : $this->otrosGastos->id),
                'cuenta_destino_id' => $fila['cuenta_destino_id'],
                'prestamo_id'       => $fila['prestamo_id'],
                'prestamo_tipo'     => $fila['prestamo_tipo'],
            ];
        }

        $this->post(route('importacion-afirme.confirmar'), ['filas' => $entrada])
            ->assertRedirect(route('gastos.index'))
            ->assertSessionHasNoErrors();

        // 3 gastos directos + 2 gastos generados por los pagos del préstamo
        $this->assertSame(5, Gasto::where('cuenta_id', $this->cuenta->id)->count());
        $this->assertSame(1, Ingreso::where('cuenta_id', $this->cuenta->id)->count());
        $this->assertSame(1, Traspaso::where('cuenta_origen_id', $this->cuenta->id)->where('cuenta_destino_id', $this->amex->id)->count());
        $this->assertSame(['capital', 'interes'], collect(PagoPrestamo::orderBy('tipo')->pluck('tipo'))->all());

        $comision = Gasto::where('monto', 29)->firstOrFail();
        $this->assertTrue($comision->tiene_iva);
        $this->assertSame('4.00', $comision->monto_iva);
        $this->assertSame($this->comisiones->id, $comision->categoria_id);

        $ingreso = Ingreso::firstOrFail();
        $this->assertSame('123456', $ingreso->referencia);
        $this->assertSame($this->cuenta->negocio_id, $ingreso->negocio_id);

        $this->assertNull(session('importacion_afirme'));

        // Al subir el mismo estado otra vez, todo lo importado sale como duplicado
        $this->subirPdf();
        $filas = collect(session('importacion_afirme')['filas']);
        $this->assertSame(7, $filas->where('duplicado', true)->count());
        $this->assertSame(13, $filas->where('tipo', 'ignorar')->count());
    }

    public function test_confirmar_exige_categoria_y_no_guarda_nada_si_falta(): void
    {
        $this->subirPdf();
        $filas = session('importacion_afirme')['filas'];

        $entrada = array_map(fn ($f) => ['tipo' => 'ignorar'], $filas);
        $entrada[10] = ['tipo' => 'gasto', 'concepto' => 'Pago proveedor', 'categoria_id' => ''];
        $entrada[2]  = ['tipo' => 'ingreso', 'concepto' => 'Honorarios', 'categoria_id' => $this->otrosIngresos->id];

        $this->post(route('importacion-afirme.confirmar'), ['filas' => $entrada])
            ->assertRedirect(route('importacion-afirme.preview'))
            ->assertSessionHasErrors('filas.10');

        $this->assertSame(0, Gasto::count() + Ingreso::count());
        $this->assertNotNull(session('importacion_afirme'));
    }

    public function test_rechaza_tipo_prestamo_en_un_deposito(): void
    {
        $this->subirPdf();
        $filas = session('importacion_afirme')['filas'];

        $entrada = array_map(fn ($f) => ['tipo' => 'ignorar'], $filas);
        $entrada[2] = ['tipo' => 'prestamo', 'concepto' => 'x', 'prestamo_id' => $this->prestamo->id, 'prestamo_tipo' => 'capital'];

        $this->post(route('importacion-afirme.confirmar'), ['filas' => $entrada])
            ->assertSessionHasErrors('filas.2');
        $this->assertSame(0, PagoPrestamo::count());
    }
}
