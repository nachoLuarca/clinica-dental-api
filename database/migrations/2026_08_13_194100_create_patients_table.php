<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de pacientes (guard 'paciente'). Tabla propia, separada de users
 * (staff), para que los dos tipos de usuario sean del todo independientes.
 *
 * Aislada por tenant: cada paciente pertenece a una clinica. El correo es unico
 * por (tenant_id, email), no global, para que un mismo correo pueda registrarse
 * como paciente en clinicas distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->string('email');
            $table->string('password');
            $table->date('fecha_nacimiento');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
