<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lineas de un presupuesto. Cada linea puede:
 *  - referenciar un tratamiento del catalogo (treatment_id), o
 *  - ser una atencion diferencial / no listada (treatment_id null, nombre y
 *    precio libres).
 *
 * Se guarda un snapshot de nombre y precio_unitario en la linea para que el
 * presupuesto no cambie si luego se edita el catalogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('budget_id')
                ->constrained('budgets')
                ->cascadeOnDelete();
            $table->foreignId('treatment_id')
                ->nullable()
                ->constrained('treatments')
                ->nullOnDelete();
            $table->string('nombre');
            $table->decimal('precio_unitario', 12, 2);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('budget_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};
