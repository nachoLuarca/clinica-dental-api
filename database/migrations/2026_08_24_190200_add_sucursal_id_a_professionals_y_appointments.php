<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * professionals.sucursal_id: a que sede pertenece el profesional (nullable,
 * clinicas que no cargaron sucursales todavia siguen funcionando igual).
 *
 * appointments.sucursal_id: snapshot de la sucursal AL MOMENTO de reservar
 * (tomada de professional.sucursal_id), no una FK que se recalcule despues
 * -si el profesional cambia de sede mas adelante, las citas ya tomadas
 * conservan donde efectivamente se atendieron-. Se completa en
 * AppointmentService::create(), sin agregar un paso nuevo al wizard: el
 * paciente elige profesional y la sucursal queda determinada sola.
 *
 * OJO SQLite (tests): a diferencia de Postgres, SQLite no soporta agregar una
 * columna con FK in-place -Laravel reconstruye toda la tabla (copia a una
 * tabla temporal, dropea, renombra)-, y esa reconstruccion NO preserva el
 * indice UNICO PARCIAL creado a mano con DB::statement() en
 * create_appointments_table (appointments_professional_fecha_hora_activa_unique):
 * se pierde en silencio. Se recrea explicitamente aca para que el bloqueo
 * optimista de citas siga funcionando igual en ambos motores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('especialidad')
                ->constrained('sucursales')->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('professional_id')
                ->constrained('sucursales')->nullOnDelete();
        });

        $this->recrearIndiceUnicoDeCitas();
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
        });

        $this->recrearIndiceUnicoDeCitas();
    }

    private function recrearIndiceUnicoDeCitas(): void
    {
        DB::statement('DROP INDEX IF EXISTS appointments_professional_fecha_hora_activa_unique');
        DB::statement(
            'CREATE UNIQUE INDEX appointments_professional_fecha_hora_activa_unique '
            ."ON appointments (professional_id, fecha_hora) WHERE estado <> 'cancelada'"
        );
    }
};
