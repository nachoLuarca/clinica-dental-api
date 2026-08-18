<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Treatment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use App\Support\AvailabilityCache;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Calculo de disponibilidad (slots libres) de un profesional para un tratamiento
 * en una fecha. Regla de negocio pura + cacheo en Redis.
 *
 * Un slot libre sale de cruzar tres cosas:
 *   1. Los tramos horarios del profesional para ese dia de la semana
 *      (ProfessionalSchedule, dia_semana 0=domingo..6=sabado, igual que Carbon).
 *   2. La duracion del tratamiento: la grilla avanza en pasos de esa duracion.
 *   3. Las citas ACTIVAS ya existentes: se descarta todo slot que las solape.
 *
 * El resultado se cachea por tenant+profesional+fecha (variante por duracion) y
 * se invalida por evento al crear/cancelar una cita, nunca por TTL.
 */
class AvailabilityService
{
    public function __construct(
        private readonly ProfessionalRepositoryInterface $professionals,
        private readonly TreatmentRepositoryInterface $treatments,
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly AvailabilityCache $cache,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forProfessional(int $professionalId, int $treatmentId, string $fecha): array
    {
        $professional = $this->professionals->find($professionalId, ['schedules'])
            ?? throw (new ModelNotFoundException)->setModel(Professional::class);

        $treatment = $this->treatments->find($treatmentId)
            ?? throw (new ModelNotFoundException)->setModel(Treatment::class);

        $date = Carbon::parse($fecha)->startOfDay();
        $fechaKey = $date->toDateString();
        $duracion = (int) $treatment->duracion_minutos;

        $slots = $this->cache->remember(
            (int) $this->tenant->tenantId(),
            $professionalId,
            $fechaKey,
            $duracion,
            fn () => $this->calcularSlots($professional, $date, $duracion),
        );

        return [
            'professional_id' => $professionalId,
            'treatment_id' => $treatmentId,
            'fecha' => $fechaKey,
            'duracion_minutos' => $duracion,
            'slots' => $slots,
        ];
    }

    /**
     * Modo "cualquier profesional disponible": agrega los slots libres de
     * los profesionales activos del tenant elegibles para la categoria del
     * tratamiento (paso 11: filtro por especialidad<->categoria; ver
     * ProfessionalRepository::allActivosParaCategoria). Cada slot trae su propio
     * professional_id (dos profesionales con el mismo horario libre generan
     * dos entradas), ordenados por hora, para que el frontend pueda mostrar
     * "10:00 (con la Dra. X)" sin adivinar quien lo cubre.
     *
     * @return array<string, mixed>
     */
    public function forTenant(int $treatmentId, string $fecha): array
    {
        $treatment = $this->treatments->find($treatmentId)
            ?? throw (new ModelNotFoundException)->setModel(Treatment::class);

        $date = Carbon::parse($fecha)->startOfDay();
        $fechaKey = $date->toDateString();
        $duracion = (int) $treatment->duracion_minutos;

        $slots = $this->professionals->allActivosParaCategoria($treatment->categoria)
            ->flatMap(function (Professional $professional) use ($fechaKey, $date, $duracion) {
                $slotsDelProfesional = $this->cache->remember(
                    (int) $this->tenant->tenantId(),
                    $professional->id,
                    $fechaKey,
                    $duracion,
                    fn () => $this->calcularSlots($professional, $date, $duracion),
                );

                return array_map(
                    fn (array $slot) => $slot + ['professional_id' => $professional->id],
                    $slotsDelProfesional,
                );
            })
            ->sortBy('fecha_hora')
            ->values()
            ->all();

        return [
            'professional_id' => null,
            'treatment_id' => $treatmentId,
            'fecha' => $fechaKey,
            'duracion_minutos' => $duracion,
            'slots' => $slots,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function calcularSlots(Professional $professional, Carbon $date, int $duracion): array
    {
        if ($duracion <= 0) {
            return [];
        }

        $diaSemana = $date->dayOfWeek; // 0=domingo .. 6=sabado

        /** @var EloquentCollection<int, ProfessionalSchedule> $tramos */
        $tramos = $professional->schedules->where('dia_semana', $diaSemana);

        $ocupadas = $this->appointments->activeForProfessionalOnDate($professional->id, $date);

        $slots = [];

        foreach ($tramos as $tramo) {
            $cursor = $date->copy()->setTimeFromTimeString($tramo->hora_inicio);
            $finTramo = $date->copy()->setTimeFromTimeString($tramo->hora_fin);

            while ($cursor->copy()->addMinutes($duracion)->lessThanOrEqualTo($finTramo)) {
                $slotInicio = $cursor->copy();
                $slotFin = $cursor->copy()->addMinutes($duracion);

                if (! $this->solapaAlguna($ocupadas, $slotInicio, $slotFin)) {
                    $slots[] = [
                        'inicio' => $slotInicio->format('H:i'),
                        'fin' => $slotFin->format('H:i'),
                        'fecha_hora' => $slotInicio->toIso8601String(),
                    ];
                }

                $cursor->addMinutes($duracion);
            }
        }

        return $slots;
    }

    /**
     * @param  EloquentCollection<int, Appointment>  $ocupadas
     */
    private function solapaAlguna(EloquentCollection $ocupadas, Carbon $inicio, Carbon $fin): bool
    {
        foreach ($ocupadas as $cita) {
            // Solape de rangos [inicio, fin): inicio < finCita && inicioCita < fin.
            if ($inicio->lessThan($cita->fecha_hora_fin) && $cita->fecha_hora->lessThan($fin)) {
                return true;
            }
        }

        return false;
    }
}
