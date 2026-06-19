<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traspasos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('cuenta_origen_id')->constrained('cuentas');
            $table->foreignId('cuenta_destino_id')->constrained('cuentas');
            $table->decimal('monto', 12, 2);
            $table->string('concepto')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traspasos');
    }
};
