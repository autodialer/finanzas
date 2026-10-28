<?php

namespace Tests\Unit;

use App\Services\AfirmeTarjetaParser;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\AfirmeFixture;
use Tests\Fixtures\AfirmeTarjetaFixture;

class AfirmeTarjetaParserTest extends TestCase
{
    public function test_reconoce_el_estado_de_cuenta_de_tarjeta(): void
    {
        $this->assertTrue(AfirmeTarjetaParser::esEstadoTarjeta(AfirmeTarjetaFixture::texto()));
        $this->assertFalse(AfirmeTarjetaParser::esEstadoTarjeta(AfirmeFixture::texto()));
    }

    public function test_lee_las_compras_a_meses(): void
    {
        $estado = (new AfirmeTarjetaParser())->parsearTexto(AfirmeTarjetaFixture::texto());

        $this->assertSame([], $estado['errores']);
        $this->assertSame('2026-03-10', $estado['periodo_inicio']);
        $this->assertSame('2026-04-10', $estado['periodo_fin']);
        $this->assertSame('9999', $estado['ultimos_digitos']);
        $this->assertSame(2300.0, $estado['cargos_meses']);
        $this->assertCount(3, $estado['renglones']);

        $primero = $estado['renglones'][0];
        $this->assertSame('2026-03-15', $primero['fecha']);
        $this->assertSame('COMERCIO FICTICIO UNO', $primero['descripcion']);
        $this->assertSame(12000.0, $primero['monto_original']);
        $this->assertSame(10000.0, $primero['saldo_pendiente']);
        $this->assertSame(1000.0, $primero['mensualidad']);
        $this->assertSame(2, $primero['numero_pago']);
        $this->assertSame(12, $primero['total_pagos']);
        $this->assertSame(12, $estado['renglones'][2]['total_pagos']);
    }

    public function test_la_tabla_puede_seguir_en_otra_pagina(): void
    {
        $estado = (new AfirmeTarjetaParser())->parsearTexto(AfirmeTarjetaFixture::texto(null, true));

        $this->assertSame([], $estado['errores']);
        $this->assertSame(
            ['COMERCIO FICTICIO UNO', 'TIENDA FICTICIA DOS', 'SERVICIO FICTICIO TRES'],
            array_column($estado['renglones'], 'descripcion')
        );
    }

    public function test_sin_seccion_de_meses_no_hay_renglones(): void
    {
        $estado = (new AfirmeTarjetaParser())->parsearTexto(AfirmeTarjetaFixture::texto([]));
        $this->assertSame([], $estado['renglones']);

        $texto = str_replace("COMPRAS Y CARGOS DIFERIDOS A MESES SIN INTERESES\n", '', AfirmeTarjetaFixture::texto());
        $this->assertSame([], (new AfirmeTarjetaParser())->parsearTexto($texto)['renglones']);
    }

    public function test_reporta_renglon_incompleto(): void
    {
        $texto = str_replace("\$6,000.00\t\$5,000.00\t\$1,000.00\t1 0.00 %", '', AfirmeTarjetaFixture::texto());
        $estado = (new AfirmeTarjetaParser())->parsearTexto($texto);
        $this->assertNotEmpty($estado['errores']);
    }
}
