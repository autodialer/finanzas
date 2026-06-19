<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->boolean('tiene_propina')->default(false)->after('monto_iva');
            $table->decimal('porcentaje_propina', 5, 2)->default(0)->after('tiene_propina');
            $table->decimal('monto_propina', 12, 2)->default(0)->after('porcentaje_propina');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['tiene_propina', 'porcentaje_propina', 'monto_propina']);
        });
    }
};
