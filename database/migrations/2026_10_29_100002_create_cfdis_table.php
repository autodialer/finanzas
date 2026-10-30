<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Facturas (CFDI) emitidas y recibidas por cada contribuyente. El XML completo queda en storage.
    public function up(): void
    {
        Schema::create('cfdis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribuyente_id')->constrained('contribuyentes')->cascadeOnDelete();
            $table->string('uuid', 36);
            $table->enum('direccion', ['emitido', 'recibido']);
            $table->char('tipo', 1); // I ingreso, E egreso, P pago, N nómina, T traslado
            $table->dateTime('fecha');
            $table->string('serie_folio', 60)->nullable();
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor')->nullable();
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor')->nullable();
            $table->string('uso_cfdi', 4)->nullable();
            $table->string('metodo_pago', 3)->nullable();
            $table->string('forma_pago', 2)->nullable();
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 12, 6)->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('iva_trasladado', 14, 2)->default(0);
            $table->decimal('iva_retenido', 14, 2)->default(0);
            $table->decimal('isr_retenido', 14, 2)->default(0);
            $table->decimal('ieps', 14, 2)->default(0);
            $table->string('conceptos', 500)->nullable();
            $table->enum('estado', ['vigente', 'cancelado'])->default('vigente');
            $table->string('archivo')->nullable();
            $table->foreignId('gasto_id')->nullable()->constrained('gastos')->nullOnDelete();
            $table->enum('vinculo', ['auto', 'manual'])->nullable();
            $table->timestamps();

            // Una factura entre dos RFC propios aparece como emitida para uno y recibida para el otro
            $table->unique(['contribuyente_id', 'uuid']);
            $table->index(['contribuyente_id', 'direccion', 'fecha']);
        });

        Schema::table('gastos', function (Blueprint $table) {
            $table->boolean('requiere_factura')->default(true)->after('monto_iva');
            $table->string('motivo_sin_factura')->nullable()->after('requiere_factura');
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['requiere_factura', 'motivo_sin_factura']);
        });
        Schema::dropIfExists('cfdis');
    }
};
