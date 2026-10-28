<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Categoría que se eligió por última vez para cada comercio al importar estados de cuenta.
    public function up(): void
    {
        Schema::create('reglas_categoria', function (Blueprint $table) {
            $table->id();
            $table->string('comercio', 150);
            $table->enum('tipo', ['gasto', 'ingreso']);
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['comercio', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_categoria');
    }
};
