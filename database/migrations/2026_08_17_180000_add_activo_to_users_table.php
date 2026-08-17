<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un staff desactivado conserva su historial (citas, presupuestos, etc.) pero
 * no puede loguearse. Alternativa a borrarlo, igual que Tenant/Professional/
 * Treatment ya usan 'activo' en vez de soft-delete para esto mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
