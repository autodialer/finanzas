<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Cada RFC (persona moral o física) con su e.firma cifrada para descargar sus facturas del SAT.
    // Un negocio pertenece a un contribuyente: sus gastos se concilian contra las facturas de ese RFC.
    public function up(): void
    {
        Schema::create('contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->string('rfc', 13)->unique();
            $table->string('nombre');
            $table->enum('tipo', ['moral', 'fisica']);
            $table->string('regimen', 3)->nullable();
            // Contenido de la e.firma, cifrado con la APP_KEY (cast "encrypted")
            $table->text('efirma_cer')->nullable();
            $table->text('efirma_key')->nullable();
            $table->text('efirma_password')->nullable();
            $table->string('efirma_serie', 40)->nullable();
            $table->date('efirma_vence')->nullable();
            $table->boolean('descarga_automatica')->default(true);
            $table->dateTime('descargado_hasta')->nullable();
            $table->timestamps();
        });

        Schema::table('negocios', function (Blueprint $table) {
            $table->foreignId('contribuyente_id')->nullable()->after('privado')->constrained('contribuyentes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribuyente_id');
        });
        Schema::dropIfExists('contribuyentes');
    }
};
