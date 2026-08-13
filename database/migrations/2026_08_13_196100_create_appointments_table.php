<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Citas/reservas. Nucleo del paso 5.
 *
 * Modelado:
 *  - fecha_hora        = inicio de la cita (timestamp).
 *  - fecha_hora_fin    = fin, derivado de fecha_hora + duracion del tratamiento.
 *    Se persiste para poder calcular solapamientos sin volver a leer el catalogo.
 *  - duracion_minutos  = snapshot de la duracion del tratamiento al reservar
 *    (si luego cambia el catalogo, la cita mantiene su longitud original).
 *  - estado            = reservada | confirmada | cancelada | completada.
 *
 * Bloqueo optimista contra choques: indice UNICO PARCIAL sobre
 * (professional_id, fecha_hora) que solo considera citas NO canceladas. Dos
 * reservas simultaneas al mismo slot: la BD deja pasar una y rechaza la otra
 * con violacion de unicidad (que el servicio traduce a un 409 claro). Es
 * parcial para que cancelar una cita libere el slot y se pueda volver a reservar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();
            $table->foreignId('treatment_id')
                ->constrained('treatments')
                ->cascadeOnDelete();
            $table->timestamp('fecha_hora');
            $table->timestamp('fecha_hora_fin');
            $table->unsignedSmallInteger('duracion_minutos');
            $table->string('estado')->default('reservada');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('patient_id');
            // Indice compuesto que sostiene el calculo de disponibilidad
            // (citas de un profesional en un rango de fechas).
            $table->index(['professional_id', 'fecha_hora']);
        });

        // Indice UNICO PARCIAL: un profesional no puede tener dos citas ACTIVAS
        // en el mismo instante de inicio. Postgres y SQLite soportan el WHERE.
        DB::statement(
            'CREATE UNIQUE INDEX appointments_professional_fecha_hora_activa_unique '
            ."ON appointments (professional_id, fecha_hora) WHERE estado <> 'cancelada'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
