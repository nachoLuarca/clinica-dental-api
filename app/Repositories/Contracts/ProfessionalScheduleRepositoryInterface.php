<?php

namespace App\Repositories\Contracts;

use App\Models\Professional;

interface ProfessionalScheduleRepositoryInterface
{
    /**
     * Reemplaza por completo los horarios del profesional por el set dado.
     * Cada elemento: ['dia_semana' => int, 'hora_inicio' => string, 'hora_fin' => string].
     *
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function syncForProfessional(Professional $professional, array $schedules): void;
}
