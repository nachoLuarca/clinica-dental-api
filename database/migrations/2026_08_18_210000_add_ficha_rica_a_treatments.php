<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Campos que el catalogo publico necesita para mostrar fichas ricas de
 * tratamiento (no solo nombre/precio):
 *  - categoria: agrupa el catalogo (ej. "Estetica", "Ortodoncia").
 *  - incluye: lista de que trae la sesion (JSON array de strings).
 *  - slug: identificador legible para la URL de la ficha en el sitio publico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('nombre');
            $table->json('incluye')->nullable()->after('descripcion');
            $table->string('slug')->nullable()->after('nombre');
        });

        // Backfill: los tratamientos ya sembrados no tienen slug. Se deriva del
        // nombre + id para que sea unico incluso con nombres repetidos entre
        // tenants (el slug es unico por tenant, no global).
        $treatments = DB::table('treatments')->select('id', 'tenant_id', 'nombre')->get();

        foreach ($treatments as $treatment) {
            DB::table('treatments')
                ->where('id', $treatment->id)
                ->update(['slug' => Str::slug($treatment->nombre).'-'.$treatment->id]);
        }

        Schema::table('treatments', function (Blueprint $table) {
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->dropUnique('treatments_tenant_id_slug_unique');
            $table->dropColumn(['categoria', 'incluye', 'slug']);
        });
    }
};
