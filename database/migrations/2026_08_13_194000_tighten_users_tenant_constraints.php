<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra los puntos abiertos que dejo el paso 2 sobre la tabla de staff (users),
 * ahora que el modelo de auth quedo firme en el paso 3:
 *
 *  - tenant_id pasa a NOT NULL: todo miembro del staff pertenece si o si a una
 *    clinica. Ya no tiene sentido un staff sin tenant.
 *  - El email deja de ser unico global y pasa a ser unico por (tenant_id,
 *    email): dos clinicas distintas pueden tener un staff con el mismo correo,
 *    pero dentro de una misma clinica el correo es unico.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El unico global de email estorba al modelo multi-tenant: se elimina
        // antes de imponer tenant_id NOT NULL para no chocar en el rearmado que
        // sqlite hace al alterar columnas.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_id_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
