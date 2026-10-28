<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Cuenta;
use App\Models\Empleado;
use App\Models\Gasto;
use App\Models\Negocio;
use App\Models\Nomina;
use App\Models\PeriodoNomina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CierreNominaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cerrar_no_crea_gastos_para_lineas_en_cero(): void
    {
        $negocio = Negocio::create(['nombre' => 'Negocio Ficticio']);
        $cuenta  = Cuenta::create(['negocio_id' => $negocio->id, 'banco_id' => Banco::create(['nombre' => 'Banco Ficticio'])->id, 'tipo' => 'banco', 'nombre' => 'Cuenta Ficticia']);
        $periodo = PeriodoNomina::create(['negocio_id' => $negocio->id, 'cuenta_id' => $cuenta->id, 'nombre' => 'Semana de prueba', 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-08']);

        foreach (['Empleado Uno' => 1500, 'Empleado Sin Pago' => 0] as $nombre => $monto) {
            $empleado = Empleado::create(['negocio_id' => $negocio->id, 'nombre' => $nombre, 'salario' => 1500]);
            Nomina::create(['periodo_id' => $periodo->id, 'empleado_id' => $empleado->id, 'monto' => $monto]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('nominas.cerrar', $periodo))
            ->assertRedirect(route('nominas.index'));

        $this->assertSame(['Nomina: Empleado Uno - Semana de prueba'], Gasto::pluck('concepto')->all());
        $this->assertSame('cerrado', $periodo->fresh()->estado);
    }
}
