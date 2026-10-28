<?php

namespace Tests\Unit;

use App\Services\AfirmeEstadoCuentaParser;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\AfirmeFixture;

class AfirmeEstadoCuentaParserTest extends TestCase
{
    private function parsear(array $reemplazos = []): array
    {
        return (new AfirmeEstadoCuentaParser())->parsearTexto(AfirmeFixture::texto($reemplazos));
    }

    public function test_lee_resumen_y_periodo(): void
    {
        $estado = $this->parsear();

        $this->assertSame([], $estado['errores']);
        $this->assertSame('2026-03-01', $estado['periodo_inicio']);
        $this->assertSame('2026-03-31', $estado['periodo_fin']);
        $this->assertSame('062580000111222333', $estado['clabe']);
        $this->assertSame(50000.00, $estado['resumen']['saldo_inicial']);
        $this->assertSame(13006.96, $estado['resumen']['depositos']);
        $this->assertSame(12538.28, $estado['resumen']['retiros']);
        $this->assertSame(50468.68, $estado['resumen']['saldo_corte']);
    }

    public function test_lee_movimientos_de_varias_paginas_e_ignora_seccion_informativa(): void
    {
        $movs = $this->parsear()['movimientos'];

        // 15 del detalle; los 2 C/Online de la sección informativa no se repiten
        $this->assertCount(15, $movs);
        $this->assertSame(2, collect($movs)->filter(fn ($m) => str_starts_with($m['descripcion'], 'C/Online'))->count());

        $this->assertSame(['2026-03-02', 'retiro', 1000.00, null], [$movs[0]['fecha'], $movs[0]['naturaleza'], $movs[0]['monto'], $movs[0]['referencia']]);
        $this->assertSame(['2026-03-05', 'deposito', 10000.00, '123456'], [$movs[2]['fecha'], $movs[2]['naturaleza'], $movs[2]['monto'], $movs[2]['referencia']]);
        $this->assertSame(['deposito', 3000.00, '5000001'], [$movs[7]['naturaleza'], $movs[7]['monto'], $movs[7]['referencia']]);
        $this->assertSame('2026-03-31', $movs[14]['fecha']);
    }

    public function test_une_descripciones_de_varias_lineas(): void
    {
        $movs = $this->parsear()['movimientos'];

        $this->assertStringStartsWith('DOMI-AMERICAN EXPRESS CO MEXICO SA DE CV', $movs[3]['descripcion']);
        $this->assertStringContainsString('CONCEPTO:Honorarios marzo', $movs[2]['descripcion']);
        $this->assertStringContainsString('CONCEPTO:123', $movs[4]['descripcion']);
        $this->assertSame('ADMINISTRACION DE ESQUEMA DE SEGURIDAD; GENERADOR DE CLAVES DINAMICAS (OTP)', $movs[13]['descripcion']);
        $this->assertSame('TRANSFERENCIA - ENVIO ; (SPEI; BANCA POR INTERNET)', $movs[5]['descripcion']);
    }

    public function test_error_si_el_saldo_al_corte_no_cuadra(): void
    {
        $estado = $this->parsear(["Saldo al corte\t$ 50,468.68" => "Saldo al corte\t$ 50,500.00"]);

        $this->assertNotEmpty($estado['errores']);
        $this->assertStringContainsString('saldo al corte', implode(' ', $estado['errores']));
    }

    public function test_error_si_los_totales_no_coinciden(): void
    {
        $estado = $this->parsear(["Depósitos\t$ 13,006.96" => "Depósitos\t$ 13,100.00"]);

        $this->assertStringContainsString('suma de depósitos', implode(' ', $estado['errores']));
    }

    public function test_error_si_un_saldo_intermedio_no_cuadra(): void
    {
        // Monto alterado: el saldo ya no corresponde al movimiento
        $estado = $this->parsear(["\t$ 5,000.00\n" => "\t$ 5,000.00\n", "\n$ 5,000.00" => "\n$ 4,000.00"]);

        $this->assertStringContainsString('no cuadra', implode(' ', $estado['errores']));
    }

    public function test_error_si_no_es_un_estado_de_cuenta(): void
    {
        $estado = (new AfirmeEstadoCuentaParser())->parsearTexto("Factura cualquiera\nTotal $ 100.00");

        $this->assertNotEmpty($estado['errores']);
        $this->assertSame([], $estado['movimientos']);
    }

    public function test_periodo_que_abarca_dos_meses(): void
    {
        $texto = implode("\n", [
            "Período de\t15 DIC 2025 AL 14 ENE 2026",
            "Saldo inicial\t$ 100.00",
            "Depósitos\t$ 50.00",
            "Retiros\t$ 30.00",
            "Saldo al corte\t$ 120.00",
            "Día Descripción\tReferencia\tDepósitos\tRetiros\tSaldo",
            "20\t$ 150.00" . str_pad('SPEI RECIBIDO DE 002-BANAMEX', 50) . "\t1\t$ 50.00",
            "03\t$ 120.00" . str_pad('COMPRA FICTICIA', 50) . "\t2\t$ 30.00",
        ]);

        $estado = (new AfirmeEstadoCuentaParser())->parsearTexto($texto);

        $this->assertSame([], $estado['errores']);
        $this->assertSame('2025-12-20', $estado['movimientos'][0]['fecha']);
        $this->assertSame('2026-01-03', $estado['movimientos'][1]['fecha']);
    }
}
