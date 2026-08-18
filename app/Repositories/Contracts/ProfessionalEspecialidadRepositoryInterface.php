<?php

namespace App\Repositories\Contracts;

use App\Models\Professional;

interface ProfessionalEspecialidadRepositoryInterface
{
    /**
     * Reemplaza por completo las especialidades del profesional por el set
     * dado (lista de IDs de especialidad).
     *
     * @param  array<int, int>  $especialidadIds
     */
    public function syncForProfessional(Professional $professional, array $especialidadIds): void;
}
