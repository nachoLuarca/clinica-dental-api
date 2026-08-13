<?php

namespace App\Repositories;

use App\Models\Appointment;
use App\Models\Scopes\TenantScope;
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

    public function dueForReminder(Carbon $desde, Carbon $hasta, array $with = []): Collection
    {
        // Barrido global de mantenimiento: sin TenantScope (el comando corre sin
        // contexto de tenant). Cada cita conserva su tenant_id, y el mensaje se
        // arma con la clinica correcta via la relacion tenant.
        return Appointment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with($with)
            ->whereIn('estado', [Appointment::ESTADO_RESERVADA, Appointment::ESTADO_CONFIRMADA])
            ->whereNull('recordatorio_enviado_at')
            ->whereBetween('fecha_hora', [$desde, $hasta])
            ->orderBy('fecha_hora')
            ->get();
    }

    public function markReminded(Appointment $appointment): void
    {
        $appointment->forceFill(['recordatorio_enviado_at' => Carbon::now()])->saveQuietly();
    }
}
