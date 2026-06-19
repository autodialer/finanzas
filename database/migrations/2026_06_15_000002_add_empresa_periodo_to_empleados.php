<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('negocio_id')->constrained('empresas')->onDelete('set null');
            $table->enum('periodo_pago', ['semanal', 'quincenal'])->default('quincenal')->after('salario');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn(['empresa_id', 'periodo_pago']);
        });
    }
};
