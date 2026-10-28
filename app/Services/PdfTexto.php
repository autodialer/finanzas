<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

// Extrae el texto de un PDF, página por página (PHP puro, funciona igual en el servidor Linux).
class PdfTexto
{
    public function extraer(string $ruta): string
    {
        $pdf = (new Parser())->parseFile($ruta);

        $texto = '';
        foreach ($pdf->getPages() as $pagina) {
            $texto .= $pagina->getText() . "\n";
        }
        return $texto;
    }
}
