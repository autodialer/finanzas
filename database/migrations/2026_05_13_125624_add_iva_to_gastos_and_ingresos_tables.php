<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->boolean('tiene_iva')->default(false)->after('forma_pago');
            $table->decimal('monto_iva', 12, 2)->default(0)->after('tiene_iva');
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->boolean('tiene_iva')->default(false)->after('forma_pago');
            $table->decimal('monto_iva', 12, 2)->default(0)->after('tiene_iva');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['tiene_iva', 'monto_iva']);
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropColumn(['tiene_iva', 'monto_iva']);
        });
    }
};
