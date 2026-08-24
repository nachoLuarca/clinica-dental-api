<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convenios de salud que acepta la clinica (Fonasa, isapres, cajas de
 * compensacion, aseguradoras). Catalogo por tenant, administrable desde el
 * portal admin; 'activo' oculta sin borrar (mismo criterio que treatments/
 * professionals).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo');
            $table->string('logo_path')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};
