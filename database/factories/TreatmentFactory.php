<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->randomElement(['Limpieza', 'Extraccion', 'Cirugia', 'Endodoncia', 'Blanqueamiento']),
            'descripcion' => fake()->optional()->sentence(),
            'precio' => fake()->numberBetween(10000, 500000),
            'duracion_minutos' => fake()->randomElement([30, 45, 60]),
            'es_diferencial' => false,
            'activo' => true,
        ];
    }

    public function diferencial(): static
    {
        return $this->state(fn (array $attributes) => [
            'nombre' => 'Atencion diferencial',
            'es_diferencial' => true,
        ]);
    }
}
