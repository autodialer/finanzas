<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Referencia bancaria del movimiento importado; junto con fecha y monto sirve para detectar duplicados.
    public function up(): void
    {
        foreach (['gastos', 'ingresos', 'traspasos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('referencia', 50)->nullable()->after('monto');
                $table->index(['fecha', 'referencia']);
            });
        }
    }

    public function down(): void
    {
        foreach (['gastos', 'ingresos', 'traspasos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropIndex(['fecha', 'referencia']);
                $table->dropColumn('referencia');
            });
        }
    }
};
