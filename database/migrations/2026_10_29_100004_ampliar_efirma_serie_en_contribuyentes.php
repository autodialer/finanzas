<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // El número de serie del certificado puede venir en hexadecimal (hasta 40+ caracteres)
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->string('efirma_serie', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->string('efirma_serie', 40)->nullable()->change();
        });
    }
};
