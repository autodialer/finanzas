<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->decimal('isr', 12, 2)->default(0)->after('monto');
            $table->decimal('imss_empleado', 12, 2)->default(0)->after('isr');
            $table->decimal('salario_neto', 12, 2)->default(0)->after('imss_empleado');
        });

        // Para registros existentes, el neto es igual al bruto (sin impuestos previos)
        DB::statement('UPDATE nominas SET salario_neto = monto WHERE salario_neto = 0');
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropColumn(['isr', 'imss_empleado', 'salario_neto']);
        });
    }
};
