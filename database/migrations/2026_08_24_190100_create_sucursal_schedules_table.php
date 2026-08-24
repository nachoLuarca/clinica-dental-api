<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horario de atencion de una sucursal (puede variar por dia, ej. lun-vie
 * 09:00-19:00, sab 09:00-14:00, dom cerrado). Mismo esquema exacto que
 * professional_schedules (tramo por dia_semana): estructura ya probada en
 * el repo para "horario que varia por dia", en vez de inventar un formato
 * nuevo (ej. JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursal_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('sucursal_id')
                ->constrained('sucursales')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0=domingo .. 6=sabado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('sucursal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_schedules');
    }
};
