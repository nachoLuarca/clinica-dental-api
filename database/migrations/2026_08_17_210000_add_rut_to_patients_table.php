<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RUT del paciente: lo pide el sitio publico de pacientes para que puedan ver
 * y cancelar sus citas sin login (RUT + fecha_nacimiento, ver
 * Publico\PatientAppointmentLookupController). Nullable porque los pacientes
 * ya existentes no lo tienen todavia; unico por (tenant_id, rut) igual que el
 * email, para que el mismo RUT pueda existir como paciente en clinicas
 * distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('rut')->nullable()->after('nombre');
            $table->unique(['tenant_id', 'rut']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_tenant_id_rut_unique');
            $table->dropColumn('rut');
        });
    }
};
