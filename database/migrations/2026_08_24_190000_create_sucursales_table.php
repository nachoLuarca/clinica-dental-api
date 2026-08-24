<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursales (sedes fisicas) de una clinica. Una clinica (tenant) puede tener
 * una o mas. Etapa 1: un profesional pertenece a UNA sola sucursal
 * (professionals.sucursal_id, FK simple) -si mas adelante una clinica
 * necesita profesionales rotando entre sedes, se agrega una tabla pivote
 * N:N sin tocar esta-.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('comuna')->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
