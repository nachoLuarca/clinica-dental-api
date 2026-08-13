<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presupuestos: propuesta de tratamientos con estado y total, previa a las
 * citas. Un presupuesto pertenece a un paciente y agrupa lineas (budget_items).
 *
 * total NO se acepta del cliente: lo recalcula el servicio a partir de las
 * lineas (precio_unitario * cantidad), para que sea siempre consistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();
            // borrador | enviado | aceptado | rechazado
            $table->string('estado')->default('borrador');
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
