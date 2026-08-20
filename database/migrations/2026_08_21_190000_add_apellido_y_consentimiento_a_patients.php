<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para el alta publica sin login (paso de Identificacion por RUT del
 * flujo de reserva):
 *  - apellido: separado de 'nombre' (antes un solo campo de texto libre) para
 *    poder buscar/ordenar/mostrar por separado en el portal admin.
 *  - email ahora nullable: el formulario de este paso lo pide opcional (a
 *    diferencia del auto-registro con cuenta/password, donde es obligatorio).
 *    El unique(tenant_id, email) sigue funcionando: Postgres/SQLite no
 *    consideran duplicados dos NULL bajo un unique index.
 *  - datos_aceptados_at: timestamp de cuando el paciente acepto el
 *    tratamiento de datos personales (checkbox obligatorio del formulario).
 *    Se guarda la fecha, no solo un booleano, como evidencia de consentimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('apellido')->nullable()->after('nombre');
            $table->timestamp('datos_aceptados_at')->nullable()->after('notas');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['apellido', 'datos_aceptados_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
