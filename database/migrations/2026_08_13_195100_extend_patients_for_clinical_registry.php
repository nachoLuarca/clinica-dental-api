<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende la tabla patients para el REGISTRO CLINICO que hace el staff.
 *
 * Decision de modelado (paso 4): se reutiliza el modelo Patient del paso 3 como
 * unica fuente de verdad del paciente. El staff puede pre-registrar un paciente
 * (ficha clinica) SIN credenciales de acceso; ese paciente puede mas adelante
 * reclamar su cuenta al registrarse en el sitio publico. Por eso:
 *   - password pasa a nullable (paciente creado por staff aun no tiene login).
 *   - se agregan datos de contacto/ficha (telefono, notas).
 * El diagnostico clinico vive en su propia tabla (diagnoses), relacionada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('telefono')->nullable()->after('email');
            $table->text('notas')->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'notas']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
