<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    protected $model = Professional::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'especialidad' => fake()->randomElement(['Ortodoncia', 'Endodoncia', 'General', 'Cirugia']),
            'email' => fake()->unique()->safeEmail(),
            'activo' => true,
        ];
    }
}
