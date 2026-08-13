<?php

namespace App\Repositories;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    public function create(array $data): Appointment
    {
        // El tenant_id lo asigna el trait BelongsToTenant. Una colision en el
        // indice unico parcial burbujea como QueryException hasta el servicio.
        return Appointment::query()->create($data);
    }

    public function activeForProfessionalOnDate(int $professionalId, Carbon $fecha): Collection
    {
        return Appointment::query()
            ->where('professional_id', $professionalId)
            ->where('estado', '!=', Appointment::ESTADO_CANCELADA)
            ->whereBetween('fecha_hora', [
                $fecha->copy()->startOfDay(),
                $fecha->copy()->endOfDay(),
            ])
            ->orderBy('fecha_hora')
            ->get();
    }

    public function hasOverlap(int $professionalId, Carbon $inicio, Carbon $fin): bool
    {
        return Appointment::query()
            ->where('professional_id', $professionalId)
            ->where('estado', '!=', Appointment::ESTADO_CANCELADA)
            // Solape de rangos: inicio_existente < fin_nuevo && fin_existente > inicio_nuevo.
            ->where('fecha_hora', '<', $fin)
            ->where('fecha_hora_fin', '>', $inicio)
            ->exists();
    }

    public function find(int $id, array $with = []): ?Appointment
    {
        return Appointment::query()->with($with)->find($id);
    }

    public function paginateForPatient(int $patientId, int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return Appointment::query()
            ->with($with)
            ->where('patient_id', $patientId)
            ->orderByDesc('fecha_hora')
            ->paginate($perPage);
    }

    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return Appointment::query()
            ->with($with)
            ->orderByDesc('fecha_hora')
            ->paginate($perPage);
    }

    public function cancel(Appointment $appointment): Appointment
    {
        $appointment->update(['estado' => Appointment::ESTADO_CANCELADA]);

        return $appointment->refresh();
    }
}
