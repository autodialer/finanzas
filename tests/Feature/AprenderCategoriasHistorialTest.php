<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\ReglaCategoria;
use App\Services\SugeridorCategoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Reglas de categoría aprendidas de gastos históricos. Todos los datos son ficticios.
class AprenderCategoriasHistorialTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tarjeta;
    private Cuenta $otraTarjeta;
    private Categoria $transporte;
    private Categoria $comida;
    private Categoria $software;

    protected function setUp(): void
    {
        parent::setUp();

        $negocio = Negocio::create(['nombre' => 'Persona Ficticia']);
        $banco   = Banco::create(['nombre' => 'American Express']);
        $this->tarjeta     = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $banco->id, 'tipo' => 'banco', 'nombre' => 'Amex Ficticia']);
        $this->otraTarjeta = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => $banco->id, 'tipo' => 'banco', 'nombre' => 'Amex Empresa Ficticia']);
        $this->transporte = Categoria::create(['nombre' => 'Transporte', 'tipo' => 'gasto']);
        $this->comida     = Categoria::create(['nombre' => 'Comida', 'tipo' => 'gasto']);
        $this->software   = Categoria::create(['nombre' => 'Software', 'tipo' => 'gasto']);
    }

    private function gasto(Cuenta $cuenta, string $concepto, Categoria $categoria, string $fecha = '2026-06-01'): void
    {
        Gasto::create(['negocio_id' => $cuenta->negocio_id, 'categoria_id' => $categoria->id, 'cuenta_id' => $cuenta->id,
            'monto' => 100, 'fecha' => $fecha, 'concepto' => $concepto, 'forma_pago' => 'tarjeta']);
    }

    private function sugerida(string $comercio): mixed
    {
        return (new SugeridorCategoria())->sugerir([['naturaleza' => 'retiro', 'comercio' => $comercio, 'categoria_id' => null, 'motivo' => '']])[0]['categoria_id'];
    }

    public function test_crea_una_regla_por_comercio_con_la_categoria_mas_usada(): void
    {
        $this->gasto($this->tarjeta, 'UBER TRIP HTTPS://HELP.UB', $this->transporte);
        $this->gasto($this->tarjeta, 'UBER TRIP HTTPS://HELP.UB', $this->transporte);
        $this->gasto($this->tarjeta, 'UBER TRIP HTTPS://HELP.UB', $this->comida, '2026-07-01');
        // El sufijo que agrega el importador no forma parte del comercio
        $this->gasto($this->tarjeta, 'SERVICIO NUBE FALSO SAN FRANCISCO (USD 10.00)', $this->software);

        $this->artisan('categorias:aprender-historial', ['cuentas' => [$this->tarjeta->id]])->assertSuccessful();

        $this->assertSame([
            'SERVICIO NUBE FALSO SAN FRANCISCO' => $this->software->id,
            'UBER TRIP HTTPS HELP UB'           => $this->transporte->id,
        ], ReglaCategoria::orderBy('comercio')->pluck('categoria_id', 'comercio')->all());
    }

    public function test_no_cambia_reglas_existentes_y_la_primera_cuenta_tiene_prioridad(): void
    {
        ReglaCategoria::create(['comercio' => 'UBER TRIP HTTPS HELP UB', 'tipo' => 'gasto', 'categoria_id' => $this->comida->id]);
        $this->gasto($this->tarjeta, 'UBER TRIP HTTPS://HELP.UB', $this->transporte);
        $this->gasto($this->tarjeta, 'TIENDA FICTICIA MONTERREY', $this->comida);
        $this->gasto($this->otraTarjeta, 'TIENDA FICTICIA MONTERREY', $this->software);
        $this->gasto($this->otraTarjeta, 'SOLO EMPRESA FICTICIA', $this->software);

        $this->artisan('categorias:aprender-historial', ['cuentas' => [$this->tarjeta->id, $this->otraTarjeta->id]])->assertSuccessful();

        $reglas = ReglaCategoria::pluck('categoria_id', 'comercio');
        $this->assertSame($this->comida->id, $reglas['UBER TRIP HTTPS HELP UB']);
        $this->assertSame($this->comida->id, $reglas['TIENDA FICTICIA MONTERREY']);
        $this->assertSame($this->software->id, $reglas['SOLO EMPRESA FICTICIA']);
    }

    public function test_ignora_gastos_importados_en_lote_que_no_se_editaron(): void
    {
        // Lote del importador viejo: 6 gastos en el mismo segundo con la categoría por omisión
        foreach (['OXXO FICTICIO', 'CAFE INVENTADO', 'UBER TRIP HTTPS://HELP.UB', 'TIENDA UNO', 'TIENDA DOS', 'TIENDA TRES'] as $concepto) {
            $this->gasto($this->tarjeta, $concepto, $this->software);
        }
        DB::table('gastos')->update(['created_at' => '2026-06-01 10:00:00', 'updated_at' => '2026-06-01 10:00:00']);
        // Uno se recategorizó después a mano: ese sí cuenta
        DB::table('gastos')->where('concepto', 'like', 'UBER%')->update(['categoria_id' => $this->transporte->id, 'updated_at' => '2026-06-03 09:00:00']);

        $this->artisan('categorias:aprender-historial', ['cuentas' => [$this->tarjeta->id]])
            ->expectsOutputToContain('se usan 1 (los demás se crearon en lote y no se editaron)')
            ->assertSuccessful();
        $this->assertSame(['UBER TRIP HTTPS HELP UB' => $this->transporte->id], ReglaCategoria::pluck('categoria_id', 'comercio')->all());

        $this->artisan('categorias:aprender-historial', ['cuentas' => [$this->tarjeta->id], '--incluir-lotes' => true])->assertSuccessful();
        $this->assertSame(6, ReglaCategoria::count());
    }

    public function test_simular_no_crea_nada(): void
    {
        $this->gasto($this->tarjeta, 'UBER TRIP HTTPS://HELP.UB', $this->transporte);

        $this->artisan('categorias:aprender-historial', ['cuentas' => [$this->tarjeta->id], '--simular' => true])
            ->expectsOutputToContain('Se crearían 1')
            ->assertSuccessful();
        $this->assertSame(0, ReglaCategoria::count());
    }

    public function test_tabla_inexistente_falla(): void
    {
        $this->artisan('categorias:aprender-historial', ['cuentas' => [$this->tarjeta->id], '--tabla' => 'no_existe'])->assertFailed();
    }

    public function test_sugiere_por_regla_que_empieza_con_el_comercio_del_estado_de_cuenta(): void
    {
        ReglaCategoria::create(['comercio' => 'UBER TRIP HTTPS HELP UB', 'tipo' => 'gasto', 'categoria_id' => $this->transporte->id]);
        ReglaCategoria::create(['comercio' => 'UBER EATS HTTPS HELP UB', 'tipo' => 'gasto', 'categoria_id' => $this->comida->id]);

        $this->assertSame($this->transporte->id, $this->sugerida('UBER TRIP'));
        $this->assertSame($this->comida->id, $this->sugerida('UBER EATS'));
        // Solo palabras completas y comercios de al menos 4 caracteres
        $this->assertNull($this->sugerida('UBER TR'));
        $this->assertNull($this->sugerida('UBE'));
    }

    public function test_la_regla_exacta_gana_sobre_el_prefijo(): void
    {
        ReglaCategoria::create(['comercio' => 'TIENDA FICTICIA', 'tipo' => 'gasto', 'categoria_id' => $this->software->id]);
        ReglaCategoria::create(['comercio' => 'TIENDA FICTICIA MONTERREY', 'tipo' => 'gasto', 'categoria_id' => $this->comida->id]);

        $this->assertSame($this->software->id, $this->sugerida('TIENDA FICTICIA'));
    }
}
