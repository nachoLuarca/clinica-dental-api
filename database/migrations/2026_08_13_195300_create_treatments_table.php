<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de tratamientos/servicios de la clinica (limpieza, extraccion, etc.)
 * con su valor. Aislado por tenant.
 *
 * es_diferencial marca la "atencion diferencial / tratamiento no listado": un
 * item de nombre y precio libres que no forma parte del catalogo publico fijo.
 * Este catalogo es lo que el sitio de pacientes consumira publicamente (paso
 * posterior); por eso 'activo' controla su visibilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 12, 2)->default(0);
            $table->boolean('es_diferencial')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
