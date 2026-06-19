<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Primero expandimos el enum para incluir ambos valores (viejo y nuevo)
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'usuario', 'gerente', 'capturista') NOT NULL DEFAULT 'gerente'");
        // Migramos los datos existentes
        DB::statement("UPDATE users SET role = 'gerente' WHERE role = 'usuario'");
        // Dejamos solo los valores nuevos
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'gerente', 'capturista') NOT NULL DEFAULT 'gerente'");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('negocio_id')->nullable()->after('role')->constrained('negocios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['negocio_id']);
            $table->dropColumn('negocio_id');
        });
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'gerente', 'capturista', 'usuario') NOT NULL DEFAULT 'gerente'");
        DB::statement("UPDATE users SET role = 'usuario' WHERE role IN ('gerente', 'capturista')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario'");
    }
};
