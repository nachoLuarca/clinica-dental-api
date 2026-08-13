<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BudgetItem>
 */
class BudgetItemFactory extends Factory
{
    public function definition(): array
    {
        $precio = fake()->numberBetween(10000, 200000);
        $cantidad = fake()->numberBetween(1, 3);

        return [
            'tenant_id' => Tenant::factory(),
            'budget_id' => Budget::factory(),
            'treatment_id' => null,
            'nombre' => fake()->randomElement(['Limpieza', 'Extraccion', 'Control']),
            'precio_unitario' => $precio,
            'cantidad' => $cantidad,
            'subtotal' => $precio * $cantidad,
        ];
    }
}
