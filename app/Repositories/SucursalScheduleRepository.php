<?php

namespace App\Repositories;

use App\Models\Sucursal;
use App\Repositories\Contracts\SucursalScheduleRepositoryInterface;

class SucursalScheduleRepository implements SucursalScheduleRepositoryInterface
{
    public function syncForSucursal(Sucursal $sucursal, array $horarios): void
    {
        // Reemplazo total: se borran los tramos actuales y se recrean.
        // Mismo patron que ProfessionalScheduleRepository.
        $sucursal->horarios()->delete();

        foreach ($horarios as $horario) {
            $sucursal->horarios()->create([
                'dia_semana' => $horario['dia_semana'],
                'hora_inicio' => $horario['hora_inicio'],
                'hora_fin' => $horario['hora_fin'],
            ]);
        }
    }
}
