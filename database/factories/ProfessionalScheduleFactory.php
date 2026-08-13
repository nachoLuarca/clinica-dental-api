<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalSchedule>
 */
class ProfessionalScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => Professional::factory(),
            'dia_semana' => fake()->numberBetween(1, 5),
            'hora_inicio' => '09:00',
            'hora_fin' => '13:00',
        ];
    }
}
