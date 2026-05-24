<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            // Eliminar el FK actual (sin onDelete)
            $table->dropForeign(['proveedor_id']);

            // Re-crear con ON DELETE SET NULL para poder borrar proveedores
            $table->foreign('proveedor_id')
                  ->references('id')
                  ->on('proveedores')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->foreign('proveedor_id')
                  ->references('id')
                  ->on('proveedores');
        });
    }
};
