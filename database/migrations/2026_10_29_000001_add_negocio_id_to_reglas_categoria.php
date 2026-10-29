<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Negocio que se eligió por última vez para cada comercio al importar estados de cuenta.
    public function up(): void
    {
        Schema::table('reglas_categoria', function (Blueprint $table) {
            $table->foreignId('negocio_id')->nullable()->after('categoria_id')->constrained('negocios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reglas_categoria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('negocio_id');
        });
    }
};
