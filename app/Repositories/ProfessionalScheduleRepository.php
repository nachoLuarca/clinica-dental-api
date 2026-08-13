<?php

namespace App\Repositories;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalScheduleRepositoryInterface;

class ProfessionalScheduleRepository implements ProfessionalScheduleRepositoryInterface
{
    public function syncForProfessional(Professional $professional, array $schedules): void
    {
        // Reemplazo total: se borran los tramos actuales y se recrean. Simple y
        // suficiente para configuracion de horarios; el tenant_id lo pone el
        // trait BelongsToTenant al crear.
        $professional->schedules()->delete();

        foreach ($schedules as $schedule) {
            $professional->schedules()->create([
                'dia_semana' => $schedule['dia_semana'],
                'hora_inicio' => $schedule['hora_inicio'],
                'hora_fin' => $schedule['hora_fin'],
            ]);
        }
    }
}
