<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RFC dueño de cada cuenta (p. ej. cuentas de Systel o Systematic dentro del negocio MassiveCaller).
        // Si está vacío, la cuenta es del RFC de su negocio.
        Schema::table('cuentas', function (Blueprint $table) {
            $table->foreignId('contribuyente_id')->nullable()->after('empresa_id')->constrained('contribuyentes')->nullOnDelete();
        });

        // Categorías cuyos gastos nunca llevan factura de proveedor (nómina, impuestos, préstamos...)
        Schema::table('categorias', function (Blueprint $table) {
            $table->boolean('requiere_factura')->default(true)->after('tipo');
        });

        foreach (['%nómin%', '%nomin%', '%impuest%', '%imss%', '%infonavit%', '%préstamo%', '%prestamo%', '%traspaso%', '%sueldo%', '%aguinaldo%'] as $patron) {
            DB::table('categorias')->where('nombre', 'like', $patron)->update(['requiere_factura' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn('requiere_factura');
        });
        Schema::table('cuentas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribuyente_id');
        });
    }
};
