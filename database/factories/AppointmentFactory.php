<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $inicio = Carbon::parse(fake()->dateTimeBetween('+1 day', '+1 month'))
            ->setTime(fake()->numberBetween(9, 16), 0);
        $duracion = 30;

        return [
            'tenant_id' => Tenant::factory(),
            'professional_id' => Professional::factory(),
            'patient_id' => Patient::factory(),
            'treatment_id' => Treatment::factory(),
            'fecha_hora' => $inicio,
            'fecha_hora_fin' => (clone $inicio)->addMinutes($duracion),
            'duracion_minutos' => $duracion,
            'estado' => Appointment::ESTADO_RESERVADA,
            'notas' => null,
        ];
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['estado' => Appointment::ESTADO_CANCELADA]);
    }

    /**
     * Fija el inicio de la cita y recalcula el fin segun la duracion dada.
     */
    public function at(Carbon|string $inicio, int $duracion = 30): static
    {
        $inicio = $inicio instanceof Carbon ? $inicio : Carbon::parse($inicio);

        return $this->state(fn () => [
            'fecha_hora' => $inicio,
            'fecha_hora_fin' => (clone $inicio)->addMinutes($duracion),
            'duracion_minutos' => $duracion,
        ]);
    }
}
