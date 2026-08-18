<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de especialidades odontologicas de la clinica (Odontologia
 * General, Ortodoncia, Endodoncia, etc.). Aislado por tenant, igual que
 * treatments/professionals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidades');
    }
};
