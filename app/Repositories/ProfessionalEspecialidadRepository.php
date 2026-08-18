<?php

namespace App\Repositories;

use App\Models\Professional;
use App\Repositories\Contracts\ProfessionalEspecialidadRepositoryInterface;

class ProfessionalEspecialidadRepository implements ProfessionalEspecialidadRepositoryInterface
{
    public function syncForProfessional(Professional $professional, array $especialidadIds): void
    {
        $professional->especialidades()->sync($especialidadIds);
    }
}
