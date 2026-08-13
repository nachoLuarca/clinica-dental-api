<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Treatment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use App\Support\AvailabilityCache;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de citas/reservas.
 *
 * Bloqueo optimista (nucleo del modulo): antes de insertar se valida el slot en
 * tiempo real (dentro de horario y sin solape). Aun asi, dos requests podrian
 * pasar esa validacion a la vez; por eso la garantia dura es el indice UNICO
 * PARCIAL (professional_id + fecha_hora en citas no canceladas): la BD acepta
 * una sola y rechaza la otra con QueryException, que aqui se traduce a
 * SlotUnavailableException (409) para que el frontend ofrezca otro horario.
 *
 * La cita es la fuente de verdad: al crear/cancelar se invalida la cache de
 * disponibilidad de ese profesional/fecha (por evento, no por TTL).
 */
class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly ProfessionalRepositoryInterface $professionals,
        private readonly TreatmentRepositoryInterface $treatments,
        private readonly PatientRepositoryInterface $patients,
        private readonly AvailabilityCache $cache,
        private readonly TenantContext $tenant,
    ) {}

    private const WITH = ['professional', 'patient', 'treatment'];

    /**
     * Crea una cita. $patientId, cuando se fuerza (reserva del propio paciente),
     * ignora cualquier patient_id que venga en $data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $patientId = null): Appointment
    {
        $patientId ??= (int) $data['patient_id'];

        $professional = $this->professionals->find((int) $data['professional_id'], ['schedules'])
            ?? throw (new ModelNotFoundException)->setModel(Professional::class);

        $treatment = $this->treatments->find((int) $data['treatment_id'])
            ?? throw (new ModelNotFoundException)->setModel(Treatment::class);

        $patient = $this->patients->find($patientId)
            ?? throw (new ModelNotFoundException)->setModel(Patient::class);

        $inicio = Carbon::parse($data['fecha_hora']);
        $duracion = (int) $treatment->duracion_minutos;
        $fin = $inicio->copy()->addMinutes($duracion);

        $this->assertDentroDeHorario($professional, $inicio, $fin);
        $this->assertSlotLibre($professional->id, $inicio, $fin);

        $appointment = $this->persistir([
            'professional_id' => $professional->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $inicio,
            'fecha_hora_fin' => $fin,
            'duracion_minutos' => $duracion,
            'estado' => Appointment::ESTADO_RESERVADA,
            'notas' => $data['notas'] ?? null,
        ]);

        $this->invalidarCache($professional->id, $inicio);

        return $appointment->load(self::WITH);
    }

    /**
     * Cancela una cita. Si se pasa $patientId, la cita debe pertenecer a ese
     * paciente (el paciente solo cancela lo suyo); si no coincide -> 404.
     */
    public function cancel(int $id, ?int $patientId = null): Appointment
    {
        $appointment = $this->appointments->find($id, self::WITH)
            ?? throw (new ModelNotFoundException)->setModel(Appointment::class);

        if ($patientId !== null && $appointment->patient_id !== $patientId) {
            throw (new ModelNotFoundException)->setModel(Appointment::class);
        }

        if (! $appointment->isCancelada()) {
            $this->appointments->cancel($appointment);
            $this->invalidarCache($appointment->professional_id, $appointment->fecha_hora);
        }

        return $appointment->refresh()->load(self::WITH);
    }

    public function paginateForPatient(int $patientId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appointments->paginateForPatient($patientId, $perPage, self::WITH);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->appointments->paginate($perPage, self::WITH);
    }

    public function find(int $id, ?int $patientId = null): Appointment
    {
        $appointment = $this->appointments->find($id, self::WITH)
            ?? throw (new ModelNotFoundException)->setModel(Appointment::class);

        if ($patientId !== null && $appointment->patient_id !== $patientId) {
            throw (new ModelNotFoundException)->setModel(Appointment::class);
        }

        return $appointment;
    }

    /**
     * Persiste la cita traduciendo la colision del indice unico parcial en un
     * error de negocio claro (bloqueo optimista).
     *
     * @param  array<string, mixed>  $data
     */
    private function persistir(array $data): Appointment
    {
        try {
            return $this->appointments->create($data);
        } catch (QueryException $e) {
            if ($this->esViolacionDeUnicidad($e)) {
                throw new SlotUnavailableException;
            }

            throw $e;
        }
    }

    private function assertDentroDeHorario(Professional $professional, Carbon $inicio, Carbon $fin): void
    {
        $diaSemana = $inicio->dayOfWeek;

        $cabe = $professional->schedules
            ->where('dia_semana', $diaSemana)
            ->contains(function ($tramo) use ($inicio, $fin) {
                $tramoInicio = $inicio->copy()->setTimeFromTimeString($tramo->hora_inicio);
                $tramoFin = $inicio->copy()->setTimeFromTimeString($tramo->hora_fin);

                return $inicio->greaterThanOrEqualTo($tramoInicio)
                    && $fin->lessThanOrEqualTo($tramoFin);
            });

        if (! $cabe) {
            throw ValidationException::withMessages([
                'fecha_hora' => ['El horario elegido esta fuera del horario de atencion del profesional.'],
            ]);
        }
    }

    private function assertSlotLibre(int $professionalId, Carbon $inicio, Carbon $fin): void
    {
        if ($this->appointments->hasOverlap($professionalId, $inicio, $fin)) {
            throw new SlotUnavailableException;
        }
    }

    private function invalidarCache(int $professionalId, Carbon $inicio): void
    {
        $this->cache->forget(
            (int) $this->tenant->tenantId(),
            $professionalId,
            $inicio->toDateString(),
        );
    }

    private function esViolacionDeUnicidad(QueryException $e): bool
    {
        // Postgres: SQLSTATE 23505. SQLite: mensaje 'UNIQUE constraint failed'.
        return ($e->getCode() === '23505')
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
