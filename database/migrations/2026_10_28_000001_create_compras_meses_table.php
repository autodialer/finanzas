<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Compras a meses sin intereses: cada una es un solo gasto por el monto original;
    // aquí solo se guarda el plan, el calendario de mensualidades se calcula.
    public function up(): void
    {
        Schema::create('compras_meses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_id')->unique()->constrained('gastos')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->string('descripcion');
            $table->decimal('monto_original', 12, 2);
            $table->unsignedSmallInteger('meses');
            $table->decimal('mensualidad', 12, 2);
            $table->date('fecha_compra');
            $table->date('fecha_primer_cargo');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_meses');
    }
};
