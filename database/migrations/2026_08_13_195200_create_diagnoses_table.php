<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnosticos clinicos de un paciente, a cargo de los profesionales.
 *
 * Un paciente tiene muchos diagnosticos (historial). Cada diagnostico lo emite
 * un profesional. Aislado por tenant como el resto de la ficha clinica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();
            $table->foreignId('professional_id')
                ->nullable()
                ->constrained('professionals')
                ->nullOnDelete();
            $table->date('fecha');
            $table->text('descripcion');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
