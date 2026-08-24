<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para la ficha publica del equipo profesional (sitio del paciente):
 * foto (misma estrategia de almacenamiento que el logo de la clinica -disco
 * 'public', columna *_path, accessor *_url-), bio corta y numero de
 * registro/matricula (opcional, no todas las clinicas lo piden).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('email');
            $table->text('bio')->nullable()->after('foto_path');
            $table->string('matricula')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn(['foto_path', 'bio', 'matricula']);
        });
    }
};
