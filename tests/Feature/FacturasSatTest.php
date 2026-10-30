<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cfdi;
use App\Models\Contribuyente;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Sat\CfdiLector;
use App\Services\Sat\ConciliadorFacturas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\CfdiFixture;
use Tests\TestCase;
use ZipArchive;

class FacturasSatTest extends TestCase
{
    use RefreshDatabase;

    private Contribuyente $moral;
    private Negocio $negocio;
    private Cuenta $cuenta;
    private Categoria $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->moral = Contribuyente::create(['rfc' => 'MAS010101XY2', 'nombre' => 'Empresa Ficticia', 'tipo' => 'moral', 'regimen' => '601']);
        $this->negocio = Negocio::create(['nombre' => 'Negocio Ficticio']);
        $this->negocio->forceFill(['contribuyente_id' => $this->moral->id])->save();
        $this->cuenta = Cuenta::create(['negocio_id' => $this->negocio->id, 'tipo' => 'banco', 'nombre' => 'Tarjeta Ficticia']);
        $this->categoria = Categoria::create(['nombre' => 'Gasolina', 'tipo' => 'gasto']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function gasto(float $monto, string $fecha, array $extra = []): Gasto
    {
        return Gasto::create(array_merge([
            'negocio_id' => $this->negocio->id, 'categoria_id' => $this->categoria->id, 'cuenta_id' => $this->cuenta->id,
            'monto' => $monto, 'fecha' => $fecha, 'concepto' => 'Gasto de prueba', 'forma_pago' => 'tarjeta',
        ], $extra));
    }

    private function subirZip(array $xmls): \Illuminate\Testing\TestResponse
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cfdi') . '.zip';
        $zip = new ZipArchive();
        $zip->open($ruta, ZipArchive::CREATE);
        foreach ($xmls as $i => $xml) {
            $zip->addFromString("factura{$i}.xml", $xml);
        }
        $zip->close();

        return $this->post(route('facturas.subir'), ['archivos' => [new UploadedFile($ruta, 'facturas.zip', 'application/zip', null, true)]]);
    }

    public function test_lee_los_datos_fiscales_del_xml(): void
    {
        $datos = (new CfdiLector())->leer(CfdiFixture::xml(['isr_retenido' => '100.00']));

        $this->assertSame('AAAAAAAA-1111-4111-8111-000000000001', $datos['uuid']);
        $this->assertSame('GAS010101AB1', $datos['rfc_emisor']);
        $this->assertSame(1160.0, $datos['total']);
        $this->assertSame(160.0, $datos['iva_trasladado']);
        $this->assertSame(100.0, $datos['isr_retenido']);
        $this->assertSame('2026-09-05 10:00:00', $datos['fecha']);
        $this->assertSame('Combustible', $datos['conceptos']);
    }

    public function test_rechaza_un_xml_que_no_es_cfdi(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CfdiLector())->leer('<?xml version="1.0"?><Otro/>');
    }

    public function test_sube_un_zip_y_vincula_la_factura_con_el_gasto(): void
    {
        $gasto = $this->gasto(1160, '2026-09-03');
        $otro  = $this->gasto(500, '2026-09-04');

        $this->subirZip([
            CfdiFixture::xml(),
            CfdiFixture::xml(['uuid' => 'AAAAAAAA-1111-4111-8111-000000000002', 'rfc_emisor' => 'MAS010101XY2', 'rfc_receptor' => 'CLI010101AA1']),
            CfdiFixture::xml(['uuid' => 'AAAAAAAA-1111-4111-8111-000000000003', 'rfc_emisor' => 'AAA010101AA1', 'rfc_receptor' => 'BBB010101BB1']),
        ])->assertSessionHasErrors('archivos'); // la tercera no es de ningún RFC propio

        $recibida = Cfdi::where('uuid', 'AAAAAAAA-1111-4111-8111-000000000001')->first();
        $this->assertSame('recibido', $recibida->direccion);
        $this->assertSame($gasto->id, $recibida->gasto_id);
        $this->assertSame('auto', $recibida->vinculo);
        $this->assertSame('emitido', Cfdi::where('uuid', 'AAAAAAAA-1111-4111-8111-000000000002')->value('direccion'));
        $this->assertSame(2, Cfdi::count());
        Storage::disk('local')->assertExists('cfdi/MAS010101XY2/AAAAAAAA-1111-4111-8111-000000000001.xml');

        // El gasto de $500 sigue apareciendo como pendiente
        $this->get(route('facturas.faltantes', ['contribuyente_id' => $this->moral->id, 'mes' => '2026-09']))
            ->assertOk()
            ->assertSee('$500.00')
            ->assertDontSee('$1,160.00');
        $this->assertNotNull($otro);
    }

    public function test_volver_a_subir_la_misma_factura_no_la_duplica_ni_pierde_el_vinculo(): void
    {
        $gasto = $this->gasto(1160, '2026-09-03');
        $this->subirZip([CfdiFixture::xml()]);
        $this->subirZip([CfdiFixture::xml()]);

        $this->assertSame(1, Cfdi::count());
        $this->assertSame($gasto->id, Cfdi::first()->gasto_id);
    }

    public function test_no_vincula_solo_cuando_hay_dos_gastos_iguales(): void
    {
        $this->gasto(1160, '2026-09-03');
        $this->gasto(1160, '2026-09-04');
        $this->subirZip([CfdiFixture::xml()]);

        $this->assertNull(Cfdi::first()->gasto_id);

        // Pero se propone como candidata para los dos
        $conciliador = app(ConciliadorFacturas::class);
        Gasto::all()->each(fn ($g) => $this->assertCount(1, $conciliador->candidatas($g, $this->moral)));
    }

    public function test_factura_en_dolares_se_compara_en_pesos_y_respeta_la_ventana_de_fechas(): void
    {
        $gasto = $this->gasto(2000, '2026-09-03');
        $this->gasto(1160, '2026-06-01'); // fuera de la ventana de 45 días

        $this->subirZip([
            CfdiFixture::xml(['uuid' => 'AAAAAAAA-1111-4111-8111-000000000010', 'moneda' => 'USD', 'tipo_cambio' => '20.00', 'subtotal' => '86.21', 'iva' => '13.79', 'total' => '100.00']),
            CfdiFixture::xml(['uuid' => 'AAAAAAAA-1111-4111-8111-000000000011']),
        ]);

        $this->assertSame($gasto->id, Cfdi::where('uuid', 'AAAAAAAA-1111-4111-8111-000000000010')->value('gasto_id'));
        $this->assertNull(Cfdi::where('uuid', 'AAAAAAAA-1111-4111-8111-000000000011')->value('gasto_id'));
    }

    public function test_vincular_a_mano_y_marcar_gasto_sin_factura(): void
    {
        $gasto = $this->gasto(1160, '2026-09-03');
        $propina = $this->gasto(50, '2026-09-03');
        $this->gasto(1160, '2026-09-04');
        $this->subirZip([CfdiFixture::xml()]);
        $cfdi = Cfdi::first();

        $this->post(route('facturas.vincular'), ['gasto_id' => $gasto->id, 'cfdi_id' => $cfdi->id])->assertSessionHasNoErrors();
        $this->assertSame('manual', $cfdi->fresh()->vinculo);

        $this->post(route('facturas.sin-factura', $propina), ['motivo' => 'Propina'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $propina->fresh()->requiere_factura);

        $pendientes = app(ConciliadorFacturas::class)->gastosSinFactura($this->moral)->pluck('monto')->map(fn ($m) => (float) $m)->all();
        $this->assertSame([1160.0], $pendientes);
    }

    public function test_la_fecha_limite_es_fin_de_mes_y_la_declaracion_el_17(): void
    {
        $gasto = $this->gasto(100, '2026-09-10');

        $limite = ConciliadorFacturas::fechaLimite($gasto, Carbon::parse('2026-09-28'));
        $this->assertSame('2026-09-30', $limite['fin_mes']->toDateString());
        $this->assertSame('2026-10-17', $limite['declaracion']->toDateString());
        $this->assertSame('urgente', $limite['urgencia']);

        $this->assertSame('vencida', ConciliadorFacturas::fechaLimite($gasto, Carbon::parse('2026-10-05'))['urgencia']);
        $this->assertSame('tarde', ConciliadorFacturas::fechaLimite($gasto, Carbon::parse('2026-10-20'))['urgencia']);
    }

    public function test_la_efirma_se_guarda_cifrada_y_no_se_expone(): void
    {
        $this->moral->forceFill(['efirma_password' => 'secreta', 'efirma_cer' => base64_encode('cer'), 'efirma_key' => base64_encode('key')])->save();

        $crudo = DB::table('contribuyentes')->where('id', $this->moral->id)->value('efirma_password');
        $this->assertNotSame('secreta', $crudo);
        $this->assertSame('secreta', $this->moral->fresh()->efirma_password);
        $this->assertArrayNotHasKey('efirma_password', $this->moral->fresh()->toArray());
    }

    public function test_solo_administradores_entran_al_modulo(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'capturista']));
        $this->get(route('facturas.faltantes'))->assertForbidden();
        $this->get(route('contribuyentes.index'))->assertForbidden();
    }

    public function test_pantallas_cargan(): void
    {
        $this->gasto(1160, now()->toDateString());
        $this->get(route('contribuyentes.index'))->assertOk()->assertSee('MAS010101XY2');
        $this->get(route('contribuyentes.show', $this->moral))->assertOk();
        $this->get(route('contribuyentes.create'))->assertOk();
        $this->get(route('contribuyentes.edit', $this->moral))->assertOk();
        $this->get(route('facturas.index'))->assertOk();
        $this->get(route('facturas.faltantes'))->assertOk()->assertSee('$1,160.00');
    }
}
