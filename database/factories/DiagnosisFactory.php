<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'patient_id' => Patient::factory(),
            'professional_id' => Professional::factory(),
            'fecha' => fake()->date(),
            'descripcion' => fake()->sentence(),
            'notas' => fake()->optional()->sentence(),
        ];
    }
}
