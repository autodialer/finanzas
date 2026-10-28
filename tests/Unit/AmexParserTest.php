<?php

namespace Tests\Unit;

use App\Services\AfirmeEstadoCuentaParser;
use App\Services\AfirmeTarjetaParser;
use App\Services\AmexParser;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\AfirmeFixture;
use Tests\Fixtures\AfirmeTarjetaFixture;
use Tests\Fixtures\AmexFixture;

class AmexParserTest extends TestCase
{
    private function parsear(array $reemplazos = []): array
    {
        return (new AmexParser())->parsearTexto(AmexFixture::texto($reemplazos));
    }

    private function movimiento(array $estado, string $descripcion): array
    {
        return collect($estado['movimientos'])->first(fn ($m) => str_starts_with($m['descripcion'], $descripcion));
    }

    public function test_reconoce_solo_el_estado_de_cuenta_amex(): void
    {
        $this->assertTrue(AmexParser::reconoce(AmexFixture::texto()));
        $this->assertFalse(AmexParser::reconoce(AfirmeTarjetaFixture::texto()));
        $this->assertFalse(AmexParser::reconoce(AfirmeFixture::texto()));
        $this->assertFalse(AfirmeTarjetaParser::reconoce(AmexFixture::texto()));
        $this->assertFalse(AfirmeEstadoCuentaParser::reconoce(AmexFixture::texto()));
    }

    public function test_lee_resumen_periodo_que_cruza_de_anio_y_solo_ultimos_4_digitos(): void
    {
        $estado = $this->parsear();

        $this->assertSame([], $estado['errores']);
        $this->assertSame(['2025-12-23', '2026-01-22'], [$estado['periodo_inicio'], $estado['periodo_fin']]);
        $this->assertSame([
            'saldo_anterior' => 5050.0, 'pagos_abonos' => 5050.0, 'nuevos_cargos' => 28939.5, 'saldo_corte' => 28939.5,
            'comisiones' => 1000.0, 'msi_saldo' => 2350.0, 'msi_mensualidades' => 550.0,
        ], $estado['resumen']);
        $this->assertSame('1111', $estado['ultimos4']);
        $this->assertSame(['1111', '2222'], $estado['tarjetas']);

        $json = json_encode($estado);
        foreach ([AmexFixture::CUENTA_TITULAR, AmexFixture::CUENTA_ADICIONAL] as $cuenta) {
            $this->assertStringNotContainsString($cuenta, $json);
            $this->assertStringNotContainsString(preg_replace('/\D/', '', $cuenta), $json);
        }
    }

    public function test_clasifica_pagos_compras_mensualidades_comisiones_y_abonos(): void
    {
        $estado = $this->parsear();

        $this->assertSame(
            ['pago' => 2, 'compra' => 7, 'mensualidad' => 2, 'abono' => 1, 'comision' => 1, 'iva' => 1],
            array_count_values(array_column($estado['movimientos'], 'clase'))
        );

        $pago = $this->movimiento($estado, 'GRACIAS POR SU PAGO');
        $this->assertSame(['2026-01-10', 'abono', 3000.0], [$pago['fecha'], $pago['naturaleza'], $pago['monto']]);

        $devolucion = $this->movimiento($estado, 'DEVOLUCION');
        $this->assertSame(['abono', '2222', 50.0], [$devolucion['naturaleza'], $devolucion['tarjeta'], $devolucion['monto']]);

        $tienda = $this->movimiento($estado, 'TIENDA FICTICIA UNO');
        $this->assertSame(['2025-12-24', 'TIENDA FICTICIA UNO', 'ABC001', '1111'], [$tienda['fecha'], $tienda['comercio'], $tienda['referencia'], $tienda['tarjeta']]);

        $libreria = $this->movimiento($estado, 'LIBRERIA');
        $this->assertSame(['numero' => 5, 'total' => 12], $libreria['mensualidad']);

        $this->assertSame('2222', $this->movimiento($estado, 'FARMACIA')['tarjeta']);
    }

    public function test_salto_de_pagina_completa_fecha_y_referencia_del_ultimo_movimiento(): void
    {
        $oxxo = $this->movimiento($this->parsear(), 'OXXO PRUEBA');

        $this->assertSame(['2025-12-28', '2025-12-28', 99.5, 'OXXO01'], [$oxxo['fecha'], $oxxo['fecha_cargo'], $oxxo['monto'], $oxxo['referencia']]);
    }

    private const SALTO_DE_PAGINA = "\nEstado de Cuenta\nPágina 9\nNúmero de Cuenta FechaSiguiente Fecha\nTarjetahabiente\t3700-000000-01111 de Corte de Corte\n"
        . "PERSONA TITULAR FICTICIA\t22-Ene-2026 22-Feb-2026 \nFecha y Detalle de las operaciones\tImporte en MN.\n";

    public function test_salto_de_pagina_completa_la_mensualidad_del_ultimo_movimiento(): void
    {
        // "CARGO 05 DE 12" y su RFC quedan al inicio de la página siguiente, después del monto
        $estado = $this->parsear(["LIBRERIA INVENTADA      MONTERREY\nCARGO 05 DE 12\nRFCBBB010101BB2 /REFMSI005\n250.00"
            => "LIBRERIA INVENTADA      MONTERREY\t250.00" . self::SALTO_DE_PAGINA . "CARGO 05 DE 12\nRFCBBB010101BB2 /REFMSI005"]);

        $this->assertSame([], $estado['errores']);
        $libreria = $this->movimiento($estado, 'LIBRERIA');
        $this->assertSame(['mensualidad', ['numero' => 5, 'total' => 12], 'MSI005'], [$libreria['clase'], $libreria['mensualidad'], $libreria['referencia']]);
        $this->assertSame('MSI005', $estado['msi'][0]['referencia']);
    }

    public function test_salto_de_pagina_completa_la_moneda_del_ultimo_movimiento(): void
    {
        $estado = $this->parsear(["SERVICIO NUBE FALSO     SAN FRANCISCO\nDólar U.S.A.         10.00\n180.00"
            => "SERVICIO NUBE FALSO     SAN FRANCISCO\t180.00" . self::SALTO_DE_PAGINA . "Dólar U.S.A.         10.00"]);

        $this->assertSame([], $estado['errores']);
        $nube = $this->movimiento($estado, 'SERVICIO NUBE');
        $this->assertSame(['USD', 10.0, 180.0], [$nube['moneda'], $nube['monto_moneda'], $nube['monto']]);
    }

    public function test_moneda_extranjera_en_el_formato_de_cada_moneda(): void
    {
        $estado = $this->parsear();

        $nube = $this->movimiento($estado, 'SERVICIO NUBE');
        $this->assertSame(['USD', 10.0, 180.0], [$nube['moneda'], $nube['monto_moneda'], $nube['monto']]);
        $this->assertSame(['EUR', 1234.56], [$this->movimiento($estado, 'HOTEL FICTICIO')['moneda'], $this->movimiento($estado, 'HOTEL FICTICIO')['monto_moneda']]);
        $this->assertSame(['JPY', 2500.0], [$this->movimiento($estado, 'TIENDA JAPON')['moneda'], $this->movimiento($estado, 'TIENDA JAPON')['monto_moneda']]);
        $this->assertSame(2000.0, AmexParser::montoMoneda('2,000.00'));
        $this->assertSame(100.15, AmexParser::montoMoneda('100,15'));
        $this->assertSame(391.87, AmexParser::montoMoneda('391,87'));
    }

    public function test_lee_meses_sin_intereses_y_los_empareja_con_su_cargo(): void
    {
        $msi = $this->parsear()['msi'];

        $this->assertCount(2, $msi);
        $this->assertSame([
            'descripcion' => 'LIBRERIA INVENTADA MONTERREY', 'fecha_compra' => '2025-08-10', 'monto_original' => 3000.0,
            'saldo_pendiente' => 1750.0, 'numero_pago' => 5, 'total_pagos' => 12, 'pago_requerido' => 250.0,
            'fecha' => '2025-12-23', 'tarjeta' => '1111', 'referencia' => 'MSI005',
        ], array_intersect_key($msi[0], array_flip(['descripcion', 'fecha_compra', 'monto_original', 'saldo_pendiente', 'numero_pago', 'total_pagos', 'pago_requerido', 'fecha', 'tarjeta', 'referencia'])));
        // Compra de diciembre en un corte de enero: año anterior
        $this->assertSame(['2025-12-20', 1, 3], [$msi[1]['fecha_compra'], $msi[1]['numero_pago'], $msi[1]['total_pagos']]);
    }

    public function test_no_cuadra_si_falta_un_cargo(): void
    {
        $estado = $this->parsear(["\n1,000.00\n" => "\n"]);

        $this->assertNotEmpty($estado['errores']);
        $this->assertStringContainsString('Total de las transacciones en $', implode(' ', $estado['errores']));
    }

    public function test_no_cuadra_si_el_resumen_no_coincide_con_los_pagos(): void
    {
        $estado = $this->parsear(['5,050.00- 5,050.00+' => '5,050.00- 5,000.00+']);

        $this->assertStringContainsString('Pagos y Créditos', implode(' ', $estado['errores']));
    }

    public function test_no_cuadra_si_una_mensualidad_no_esta_en_la_tabla(): void
    {
        $estado = $this->parsear(['CARGO 05 DE 12' => 'CARGO 06 DE 12']);

        $this->assertStringContainsString('no aparece en la tabla de meses sin intereses', implode(' ', $estado['errores']));
    }
}
