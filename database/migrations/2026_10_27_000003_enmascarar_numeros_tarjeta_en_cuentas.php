<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Los números de tarjeta (15 o 16 dígitos) se dejan solo con los últimos 4.
    // Las CLABE (18) y los números de cuenta (10-11) no se tocan.
    public function up(): void
    {
        DB::table('cuentas')->whereNotNull('numero')->get(['id', 'numero'])->each(function ($cuenta) {
            $digitos = preg_replace('/\D/', '', $cuenta->numero);
            if (in_array(strlen($digitos), [15, 16], true)) {
                DB::table('cuentas')->where('id', $cuenta->id)->update(['numero' => substr($digitos, -4)]);
            }
        });
    }

    public function down(): void
    {
        // El número completo no se puede recuperar
    }
};
