<?php

namespace App\Repositories\Contracts;

use App\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Contrato del repositorio de citas. Unica capa que habla con el Model
 * Appointment; todas las consultas quedan aisladas por tenant via TenantScope.
 */
interface AppointmentRepositoryInterface
{
    /**
     * Crea la cita. Puede lanzar QueryException por violacion del indice unico
     * parcial (professional_id + fecha_hora en citas no canceladas): es el
     * bloqueo optimista, que el servicio traduce a un error de slot ocupado.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Appointment;

    /**
     * Citas ACTIVAS (no canceladas) de un profesional en un dia concreto,
     * ordenadas por inicio. Insumo del calculo de slots libres.
     *
     * @return Collection<int, Appointment>
     */
    public function activeForProfessionalOnDate(int $professionalId, Carbon $fecha): Collection;

    /**
     * Indica si el profesional tiene alguna cita ACTIVA que solape el rango
     * [inicio, fin). Verificacion en tiempo real previa a intentar reservar.
     */
    public function hasOverlap(int $professionalId, Carbon $inicio, Carbon $fin): bool;

    /**
     * @param  array<int, string>  $with
     */
    public function find(int $id, array $with = []): ?Appointment;

    /**
     * Listado paginado de las citas de un paciente concreto.
     *
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function paginateForPatient(int $patientId, int $perPage = 15, array $with = []): LengthAwarePaginator;

    /**
     * Listado paginado de todas las citas del tenant activo.
     *
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    public function cancel(Appointment $appointment): Appointment;
}
