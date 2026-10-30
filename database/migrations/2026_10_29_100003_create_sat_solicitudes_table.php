<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Solicitudes al servicio de Descarga Masiva del SAT: se presentan, se verifican cada hora y se descargan.
    public function up(): void
    {
        Schema::create('sat_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribuyente_id')->constrained('contribuyentes')->cascadeOnDelete();
            $table->enum('direccion', ['emitido', 'recibido']);
            $table->dateTime('desde');
            $table->dateTime('hasta');
            $table->string('request_id', 60)->nullable();
            // pendiente | en_proceso | terminada | sin_datos | error | vencida
            $table->string('estado', 20)->default('pendiente');
            $table->string('mensaje')->nullable();
            $table->json('paquetes')->nullable();
            $table->unsignedInteger('cfdis_nuevos')->default(0);
            $table->dateTime('verificada_at')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_solicitudes');
    }
};
