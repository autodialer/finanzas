<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->boolean('privado')->default(false)->after('descripcion');
        });

        // Carlos Campos es privado
        DB::table('negocios')->where('nombre', 'like', '%Carlos Campos%')->update(['privado' => true]);
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            $table->dropColumn('privado');
        });
    }
};
