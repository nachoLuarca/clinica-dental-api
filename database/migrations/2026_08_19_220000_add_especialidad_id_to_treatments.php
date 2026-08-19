<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el mapeo por texto (treatments.categoria <-> especialidad_categoria.categoria)
 * por una relacion real: treatments.especialidad_id -> especialidades.id (FK).
 *
 * Un tratamiento pertenece a UNA sola especialidad (o ninguna): es una FK
 * simple, no una tabla pivote. Nullable porque un tratamiento puede no tener
 * especialidad asignada (atencion diferencial, o clinica que aun no adopto
 * el catalogo de especialidades) -mismo criterio que ya se usaba para el
 * fallback de "sin mapeo configurado" en el filtro de reserva.
 *
 * 'categoria' (texto libre) se conserva como dato descriptivo del catalogo
 * publico (agrupa la ficha rica), pero deja de tener uso relacional: el
 * filtro de reserva por especialidad ahora usa especialidad_id directamente
 * (ver ProfessionalRepository::allActivosParaEspecialidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatments', function (Blueprint $table) {
            $table->foreignId('especialidad_id')->nullable()->after('categoria')
                ->constrained('especialidades')->nullOnDelete();
        });

        // Backfill: para cada tratamiento con 'categoria' seteada, si existe
        // una especialidad del mismo tenant que cubra esa categoria (via la
        // tabla que se retira mas abajo), enlazarlo por FK antes de perder
        // ese mapeo por texto. Se arma con un SELECT + update por fila (en
        // vez de un UPDATE con join) para que la migracion corra igual en
        // Postgres (dev/prod) y SQLite (tests, ver phpunit.xml): un UPDATE
        // con join no es portable entre ambos motores.
        $matches = DB::table('treatments as t')
            ->join('especialidad_categoria as ec', 't.categoria', '=', 'ec.categoria')
            ->join('especialidades as e', function ($join) {
                $join->on('e.id', '=', 'ec.especialidad_id')
                    ->on('e.tenant_id', '=', 't.tenant_id');
            })
            ->select('t.id as treatment_id', 'e.id as especialidad_id')
            ->get();

        foreach ($matches as $match) {
            DB::table('treatments')
                ->where('id', $match->treatment_id)
                ->update(['especialidad_id' => $match->especialidad_id]);
        }

        Schema::dropIfExists('especialidad_categoria');
    }

    public function down(): void
    {
        // Recrea el esquema de la tabla retirada (no los datos: el mapeo por
        // texto se perdio al migrar a la FK, es esperable en un down()).
        Schema::create('especialidad_categoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especialidad_id')
                ->constrained('especialidades')
                ->cascadeOnDelete();
            $table->string('categoria');
            $table->timestamps();

            $table->unique(['especialidad_id', 'categoria']);
        });

        Schema::table('treatments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('especialidad_id');
        });
    }
};
