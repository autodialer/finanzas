<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pagos_prestamo', function (Blueprint $table) {
            $table->boolean('tiene_iva')->default(false)->after('tipo');
            $table->decimal('monto_iva', 12, 2)->default(0)->after('tiene_iva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos_prestamo', function (Blueprint $table) {
            $table->dropColumn(['tiene_iva', 'monto_iva']);
        });
    }
};
