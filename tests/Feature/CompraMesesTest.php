<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\CompraMeses;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\User;
use App\Services\ConciliadorComprasMeses;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Todos los datos son ficticios.
class CompraMesesTest extends TestCase
{
    use RefreshDatabase;

    private Negocio $negocio;
    private Negocio $otroNegocio;
    private Cuenta $tarjeta;
    private Cuenta $tarjetaOtro;
    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-28 12:00:00');

        $this->negocio     = Negocio::create(['nombre' => 'Negocio Ficticio']);
        $this->otroNegocio = Negocio::create(['nombre' => 'Otro Negocio Ficticio']);
        $banco = Banco::create(['nombre' => 'Banco Ficticio']);
        $this->tarjeta     = Cuenta::create(['negocio_id' => $this->negocio->id, 'banco_id' => $banco->id, 'tipo' => 'banco', 'nombre' => 'TC Ficticia']);
        $this->tarjetaOtro = Cuenta::create(['negocio_id' => $this->otroNegocio->id, 'banco_id' => $banco->id, 'tipo' => 'banco', 'nombre' => 'TC Otro Ficticia']);
        $this->categoria   = Categoria::create(['nombre' => 'Equipo ficticio', 'tipo' => 'gasto']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'cuenta_id'          => $this->tarjeta->id,
            'categoria_id'       => $this->categoria->id,
            'descripcion'        => 'Tienda Ficticia - Laptop',
            'monto_original'     => 10000,
            'meses'              => 3,
            'mensualidad'        => 3333.33,
            'fecha_compra'       => '2026-09-02',
            'fecha_primer_cargo' => '2026-09-15',
        ], $cambios);
    }

    private function crearCompra(array $cambios = []): CompraMeses
    {
        $this->post(route('compras-meses.store'), $this->datos($cambios))->assertSessionHasNoErrors();
        return CompraMeses::latest('id')->firstOrFail();
    }

    public function test_calcula_calendario_pagos_y_saldo(): void
    {
        $compra = $this->crearCompra();

        $this->assertSame([3333.33, 3333.33, 3333.34], $compra->calendario->pluck('monto')->all());
        $this->assertSame(['2026-09-15', '2026-10-15', '2026-11-15'], $compra->calendario->map(fn ($c) => $c['fecha']->toDateString())->all());
        $this->assertSame(2, $compra->pagos_realizados);
        $this->assertSame(1, $compra->pagos_restantes);
        $this->assertSame(3333.34, $compra->saldo_pendiente);
        $this->assertSame('2026-11-15', $compra->fecha_ultimo_pago->toDateString());
        $this->assertFalse($compra->terminada);
        $this->assertTrue($compra->pagosRestantes(Carbon::parse('2026-11-15')) === 0);
    }

    public function test_fin_de_mes_no_se_desborda(): void
    {
        $calendario = CompraMeses::calendario(300, 3, 100, Carbon::parse('2027-01-31'));
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], $calendario->map(fn ($c) => $c['fecha']->toDateString())->all());
    }

    public function test_alta_crea_un_solo_gasto_por_el_monto_original(): void
    {
        $compra = $this->crearCompra();

        $this->assertSame(1, Gasto::count());
        $gasto = $compra->gasto;
        $this->assertEquals(10000, $gasto->monto);
        $this->assertSame('2026-09-02', $gasto->fecha);
        $this->assertSame($this->tarjeta->id, $gasto->cuenta_id);
        $this->assertSame($this->negocio->id, $gasto->negocio_id);
        $this->assertSame('tarjeta', $gasto->forma_pago);
        $this->assertSame('Tienda Ficticia - Laptop (3 MSI)', $gasto->concepto);
    }

    public function test_edicion_y_borrado_mantienen_el_gasto_sincronizado(): void
    {
        $compra = $this->crearCompra();

        $this->put(route('compras-meses.update', $compra), $this->datos([
            'monto_original' => 12000, 'meses' => 6, 'mensualidad' => 2000, 'fecha_compra' => '2026-09-05', 'tiene_iva' => 1,
        ]))->assertSessionHasNoErrors();

        $gasto = $compra->gasto->fresh();
        $this->assertEquals(12000, $gasto->monto);
        $this->assertSame('2026-09-05', $gasto->fecha);
        $this->assertEquals(1655.17, $gasto->monto_iva);
        $this->assertSame(1, Gasto::count());

        $this->delete(route('compras-meses.destroy', $compra));
        $this->assertSame(0, Gasto::count());
        $this->assertSame(0, CompraMeses::count());
    }

    public function test_detalle_y_edicion_se_muestran(): void
    {
        $compra = $this->crearCompra();

        $this->get(route('compras-meses.show', $compra))->assertOk()->assertSee('Calendario de mensualidades')->assertSee('Próximo');
        $this->get(route('compras-meses.edit', $compra))->assertOk()->assertSee('Tienda Ficticia - Laptop');
        $this->get(route('gastos.index'))->assertOk()->assertSee('MSI');
    }

    public function test_rechaza_mensualidad_que_no_cuadra(): void
    {
        $this->post(route('compras-meses.store'), $this->datos(['mensualidad' => 2500]))
            ->assertSessionHasErrors('mensualidad');
        $this->assertSame(0, Gasto::count());
    }

    public function test_el_gasto_ligado_se_edita_desde_la_compra(): void
    {
        $compra = $this->crearCompra();

        $this->get(route('gastos.edit', $compra->gasto))->assertRedirect(route('compras-meses.edit', $compra));
        $this->delete(route('gastos.destroy', $compra->gasto))->assertRedirect(route('compras-meses.edit', $compra));
        $this->assertSame(1, Gasto::count());
    }

    public function test_listado_separa_activas_y_terminadas(): void
    {
        $this->crearCompra();
        $this->crearCompra(['descripcion' => 'Compra ficticia terminada', 'fecha_compra' => '2026-01-10', 'fecha_primer_cargo' => '2026-02-10']);

        $this->get(route('compras-meses.index'))
            ->assertOk()
            ->assertViewHas('activas', fn ($a) => $a->pluck('descripcion')->all() === ['Tienda Ficticia - Laptop'])
            ->assertViewHas('terminadas', fn ($t) => $t->pluck('descripcion')->all() === ['Compra ficticia terminada']);
    }

    public function test_capturista_solo_ve_y_usa_su_negocio(): void
    {
        $ajena = $this->crearCompra(['cuenta_id' => $this->tarjetaOtro->id, 'descripcion' => 'Compra ajena ficticia']);

        $this->actingAs(User::factory()->create(['role' => 'capturista', 'negocio_id' => $this->negocio->id]));
        $propia = $this->crearCompra(['descripcion' => 'Compra propia ficticia']);

        $this->get(route('compras-meses.index'))
            ->assertViewHas('activas', fn ($a) => $a->pluck('id')->all() === [$propia->id]);
        $this->get(route('compras-meses.show', $ajena))->assertForbidden();
        $this->delete(route('compras-meses.destroy', $ajena))->assertForbidden();
        $this->post(route('compras-meses.store'), $this->datos(['cuenta_id' => $this->tarjetaOtro->id]))->assertForbidden();
        $this->assertSame(2, CompraMeses::count());
    }

    public function test_dashboard_muestra_compromisos(): void
    {
        $this->crearCompra();
        $this->crearCompra(['descripcion' => 'Otra ficticia', 'monto_original' => 1200, 'meses' => 12, 'mensualidad' => 100, 'fecha_primer_cargo' => '2026-10-20']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('compromisoMesActual', 3433.33)
            ->assertViewHas('saldoPendienteMeses', 3333.34 + 1100)
            ->assertViewHas('compromisosMeses', fn ($m) => array_keys($m) === ['2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04']
                && $m['2026-11'] === 3433.34 && $m['2026-12'] === 100.0)
            ->assertSee('Compromisos a meses');
    }

    public function test_simulador_recibe_compromisos_actuales(): void
    {
        $this->crearCompra();

        $this->get(route('compras-meses.create'))
            ->assertOk()
            ->assertViewHas('compromisos', fn ($c) => count($c) === 12 && $c['2026-10'] === 3333.33 && $c['2026-11'] === 3333.34);
    }

    public function test_reportes_prorrateados_usan_la_mensualidad(): void
    {
        $this->crearCompra();
        Gasto::create([
            'negocio_id' => $this->negocio->id, 'categoria_id' => $this->categoria->id, 'cuenta_id' => $this->tarjeta->id,
            'monto' => 500, 'fecha' => '2026-10-05', 'concepto' => 'Gasto normal ficticio', 'forma_pago' => 'tarjeta',
        ]);
        $octubre = ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31'];
        $septiembre = ['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30'];

        $this->get(route('reportes.index', $septiembre))->assertViewHas('totalGastos', 10000);
        $this->get(route('reportes.index', $septiembre + ['prorrateado' => 1]))->assertViewHas('totalGastos', 3333.33);
        $this->get(route('reportes.index', $octubre + ['prorrateado' => 1]))
            ->assertViewHas('totalGastos', 3833.33)
            ->assertSee('mensualidad 2/3');

        $this->get(route('reportes.mensual', ['mes' => 10, 'anio' => 2026]))->assertViewHas('totalGastos', 500.0);
        $this->get(route('reportes.mensual', ['mes' => 10, 'anio' => 2026, 'prorrateado' => 1]))->assertViewHas('totalGastos', 3833.33);
    }

    public function test_conciliador_de_meses_del_estado_de_cuenta(): void
    {
        $compra = $this->crearCompra();
        $conciliador = new ConciliadorComprasMeses($this->tarjeta);
        $renglon = ['descripcion' => 'TIENDA FICTICIA LAPTOP', 'monto_original' => 10000, 'total_pagos' => 3, 'fecha' => '2026-10-15'];

        $nueva = $conciliador->conciliar(['descripcion' => 'COMERCIO NUEVO', 'monto_original' => 600, 'numero_pago' => 1, 'total_pagos' => 6, 'fecha' => '2026-10-15']);
        $this->assertSame(ConciliadorComprasMeses::CREAR, $nueva['accion']);
        $this->assertSame(100.0, $nueva['sugerencia']['mensualidad']);
        $this->assertSame('2026-10-15', $nueva['sugerencia']['fecha_primer_cargo']);

        $ok = $conciliador->conciliar($renglon + ['numero_pago' => 2, 'saldo_pendiente' => 3333.34]);
        $this->assertSame(ConciliadorComprasMeses::VERIFICADA, $ok['accion']);
        $this->assertSame($compra->id, $ok['compra_id']);

        $mal = $conciliador->conciliar($renglon + ['numero_pago' => 3, 'saldo_pendiente' => 3333.34]);
        $this->assertSame(ConciliadorComprasMeses::DIFERENCIA, $mal['accion']);
        $this->assertCount(2, $mal['avisos']);

        $sinPlan = $conciliador->conciliar(['descripcion' => 'OTRO COMERCIO', 'monto_original' => 1200, 'numero_pago' => 4, 'total_pagos' => 12, 'fecha' => '2026-10-15']);
        $this->assertSame(ConciliadorComprasMeses::SIN_PLAN, $sinPlan['accion']);
        $this->assertSame('2026-07-15', $sinPlan['sugerencia']['fecha_primer_cargo']);
        $this->assertSame('2026-07-15', $sinPlan['sugerencia']['fecha_compra']);
    }
}
