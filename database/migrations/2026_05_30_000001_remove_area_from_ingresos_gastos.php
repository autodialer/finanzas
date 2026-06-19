<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::dropIfExists('areas');
    }

    public function down(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios');
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('areas');
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('areas');
        });
    }
};
