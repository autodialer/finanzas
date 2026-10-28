<?php

namespace Tests\Unit;

use App\Services\AfirmeEstadoCuentaParser;
use App\Services\AfirmeTarjetaParser;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\AfirmeFixture;
use Tests\Fixtures\AfirmeTarjetaFixture;

class AfirmeTarjetaParserTest extends TestCase
{
    private function parsear(array $reemplazos = []): array
    {
        return (new AfirmeTarjetaParser())->parsearTexto(AfirmeTarjetaFixture::texto($reemplazos));
    }

    public function test_reconoce_cada_tipo_de_estado_de_cuenta(): void
    {
        $this->assertTrue(AfirmeTarjetaParser::reconoce(AfirmeTarjetaFixture::texto()));
        $this->assertFalse(AfirmeEstadoCuentaParser::reconoce(AfirmeTarjetaFixture::texto()));
        $this->assertTrue(AfirmeEstadoCuentaParser::reconoce(AfirmeFixture::texto()));
        $this->assertFalse(AfirmeTarjetaParser::reconoce(AfirmeFixture::texto()));
    }

    public function test_lee_resumen_y_solo_conserva_los_ultimos_4_digitos(): void
    {
        $estado = $this->parsear();

        $this->assertSame([], $estado['errores']);
        $this->assertSame(['2026-02-10', '2026-03-10'], [$estado['periodo_inicio'], $estado['periodo_fin']]);
        $this->assertSame(1720.25, $estado['resumen']['cargos_regulares']);
        $this->assertSame(1000.00, $estado['resumen']['pagos_abonos']);
        $this->assertSame(1500.00, $estado['resumen']['cargos_meses']);
        $this->assertSame('9999', $estado['ultimos4']);
        $this->assertStringNotContainsString(AfirmeTarjetaFixture::NUMERO_TARJETA, json_encode($estado));
    }

    public function test_lee_cargos_y_abonos_de_varias_paginas(): void
    {
        $movs = $this->parsear()['movimientos'];

        $this->assertSame(
            [
                ['2026-02-11', '2026-02-12', 'cargo', 50.00, 'OXXO CENTRO FICTICIO'],
                ['2026-02-12', '2026-02-13', 'cargo', 450.00, 'REST LA PRUEBA'],
                ['2026-02-15', '2026-02-15', 'abono', 1000.00, 'PAGO ONLINE'],          // fechas pegadas al texto
                ['2026-02-20', '2026-02-21', 'cargo', 800.00, 'GASOL FICTICIA NORTE'],  // fechas en líneas distintas
                ['2026-02-22', '2026-02-23', 'cargo', 120.25, 'FARMACIA INVENTADA'],
                ['2026-03-01', '2026-03-02', 'cargo', 300.00, 'TIENDA SIN CLAVE'],
            ],
            array_map(fn ($m) => [$m['fecha'], $m['fecha_cargo'], $m['naturaleza'], $m['monto'], $m['comercio']], $movs)
        );
        $this->assertSame('COMPRA NACIONAL', $movs[0]['operacion']);
    }

    public function test_lee_meses_sin_intereses(): void
    {
        $msi = $this->parsear()['msi'];

        $this->assertCount(2, $msi);
        $this->assertSame(['2026-02-11', 'TIENDA ELECTRONICA FICTICIA', 12000.00, 1], [$msi[0]['fecha'], $msi[0]['descripcion'], $msi[0]['monto_original'], $msi[0]['numero_pago']]);
        $this->assertSame(['LIBRERIA FALSA', 500.00, 3], [$msi[1]['descripcion'], $msi[1]['pago_requerido'], $msi[1]['numero_pago']]);
    }

    public function test_error_si_los_cargos_no_cuadran(): void
    {
        $estado = $this->parsear(["Cargos regulares (no a meses)\t+ $1,720.25" => "Cargos regulares (no a meses)\t+ $1,800.00"]);

        $this->assertStringContainsString('suma de cargos', implode(' ', $estado['errores']));
    }

    public function test_error_si_los_abonos_no_cuadran(): void
    {
        $estado = $this->parsear(["Pagos y abonos\t- $1,000.00" => "Pagos y abonos\t- $900.00"]);

        $this->assertStringContainsString('suma de abonos', implode(' ', $estado['errores']));
    }

    public function test_error_si_falta_el_resumen(): void
    {
        $estado = $this->parsear(["Pagos y abonos\t- $1,000.00" => '']);

        $this->assertStringContainsString('Pagos y abonos', implode(' ', $estado['errores']));
    }
}
