<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'estado' => 'borrador',
            'total' => 0,
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
