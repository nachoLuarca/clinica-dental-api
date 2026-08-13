<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horarios de atencion de cada profesional (disponibilidad base).
 *
 * Estructura semanal recurrente: por cada dia de la semana (0=domingo .. 6=sabado)
 * un tramo hora_inicio-hora_fin. El calculo de slots libres reales (cruzando con
 * citas ya tomadas) es del paso 5; aqui solo se persiste la configuracion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0=domingo .. 6=sabado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('professional_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_schedules');
    }
};
