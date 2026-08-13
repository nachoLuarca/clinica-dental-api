<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de recordatorio enviado (paso 6).
 *
 * Permite que el comando programado de recordatorios sea idempotente: una cita
 * ya recordada no se vuelve a notificar en la siguiente pasada. Se indexa junto
 * a fecha_hora porque el barrido filtra por ambas columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('recordatorio_enviado_at')->nullable()->after('estado');
            $table->index(['recordatorio_enviado_at', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['recordatorio_enviado_at', 'fecha_hora']);
            $table->dropColumn('recordatorio_enviado_at');
        });
    }
};
