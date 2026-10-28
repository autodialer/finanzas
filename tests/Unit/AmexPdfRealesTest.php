<?php

namespace Tests\Unit;

use App\Services\AmexParser;
use App\Services\PdfTexto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regresión con los estados de cuenta Amex reales, que nunca entran al
 * repositorio: se leen de AMEX_PDFS_DIR (por omisión ~/respaldos-finanzas) y la
 * prueba se salta si no están. Solo se comprueba que cada uno pase la validación.
 */
class AmexPdfRealesTest extends TestCase
{
    public static function pdfs(): array
    {
        $carpeta = getenv('AMEX_PDFS_DIR') ?: rtrim((string) getenv('HOME'), '/') . '/respaldos-finanzas';
        $archivos = glob($carpeta . '/AE_*.pdf') ?: [];

        return $archivos ? array_combine(array_map('basename', $archivos), array_map(fn ($a) => [$a], $archivos)) : ['sin PDFs' => [null]];
    }

    #[DataProvider('pdfs')]
    public function test_el_estado_de_cuenta_real_pasa_la_validacion(?string $ruta): void
    {
        if ($ruta === null) {
            $this->markTestSkipped('No hay estados de cuenta Amex reales en AMEX_PDFS_DIR.');
        }

        $estado = (new AmexParser())->parsearTexto((new PdfTexto())->extraer($ruta));

        $this->assertSame([], $estado['errores'], basename($ruta));
        $this->assertNotEmpty($estado['movimientos']);
    }
}
