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
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios');
            $table->foreignId('area_id')->nullable()->constrained('areas');
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('concepto');
            $table->enum('forma_pago', ['efectivo', 'transferencia', 'tarjeta']);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
