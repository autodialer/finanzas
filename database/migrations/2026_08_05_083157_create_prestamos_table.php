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
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->enum('tipo', ['auto', 'equipo', 'otro'])->default('otro');
            $table->string('concepto');
            $table->decimal('monto_original', 12, 2);
            $table->decimal('tasa_interes', 5, 2)->nullable();
            $table->unsignedInteger('plazo_meses')->nullable();
            $table->date('fecha_inicio');
            $table->text('notas')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
