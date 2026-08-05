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
            $table->enum('tipo', ['capital', 'interes'])->default('capital')->after('monto');
        });

        // Pagos ya capturados: los que mencionan "inter" en las notas se marcan como interés.
        \DB::table('pagos_prestamo')->where('notas', 'like', '%inter%')->update(['tipo' => 'interes']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos_prestamo', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
