<?php

namespace App\Repositories\Contracts;

use App\Models\Sucursal;

/**
 * Mismo contrato que ProfessionalScheduleRepositoryInterface, para el
 * horario de atencion de una sucursal.
 */
interface SucursalScheduleRepositoryInterface
{
    /**
     * @param  array<int, array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     */
    public function syncForSucursal(Sucursal $sucursal, array $horarios): void;
}
