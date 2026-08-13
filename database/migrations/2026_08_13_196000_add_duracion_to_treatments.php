<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la duracion (en minutos) al tratamiento. Es el insumo con el que el
 * paso 5 calcula la longitud de cada slot de disponibilidad: los slots libres
 * de un profesional se generan avanzando en tramos de esta duracion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->unsignedSmallInteger('duracion_minutos')->default(30)->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropColumn('duracion_minutos');
        });
    }
};
