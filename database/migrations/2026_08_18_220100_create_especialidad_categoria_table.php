<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Que categorias de tratamiento (Treatment::categoria, texto libre elegido
 * por la clinica al cargar el catalogo) puede cubrir cada especialidad. Es
 * la tabla que habilita el filtro profesion <-> categoria del tratamiento
 * en la reserva: tratamiento -> categoria -> especialidades que la cubren
 * -> profesionales con esa especialidad.
 *
 * No lleva tenant_id propio: hereda el aislamiento de especialidad_id, que
 * ya pertenece a un tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidad_categoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especialidad_id')
                ->constrained('especialidades')
                ->cascadeOnDelete();
            $table->string('categoria');
            $table->timestamps();

            $table->unique(['especialidad_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especialidad_categoria');
    }
};
