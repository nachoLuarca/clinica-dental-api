<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot N:N profesional <-> especialidad. Un profesional puede tener mas de
 * una especialidad (ej. Odontologia General + Ortodoncia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_especialidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')
                ->constrained('professionals')
                ->cascadeOnDelete();
            $table->foreignId('especialidad_id')
                ->constrained('especialidades')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['professional_id', 'especialidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_especialidad');
    }
};
