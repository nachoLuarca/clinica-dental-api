<?php

namespace App\Services;

use App\Models\Diagnosis;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de diagnosticos clinicos (a cargo del staff).
 *
 * Los diagnosticos se listan/crean siempre en el contexto de un paciente. El
 * paciente y el profesional referenciados deben pertenecer al tenant activo:
 * se validan a traves de los repositorios (que ya filtran por TenantScope), de
 * modo que no se puede colgar un diagnostico de un paciente de otra clinica.
 */
class DiagnosisService
{
    public function __construct(
        private readonly DiagnosisRepositoryInterface $diagnoses,
        private readonly PatientRepositoryInterface $patients,
        private readonly ProfessionalRepositoryInterface $professionals,
    ) {}

    public function paginateForPatient(int $patientId, int $perPage = 15): LengthAwarePaginator
    {
        $this->assertPatientExists($patientId);

        return $this->diagnoses->paginateForPatient($patientId, $perPage, ['professional']);
    }

    public function find(int $patientId, int $id): Diagnosis
    {
        $this->assertPatientExists($patientId);

        $diagnosis = $this->diagnoses->find($id, ['professional', 'patient']);

        if ($diagnosis === null || $diagnosis->patient_id !== $patientId) {
            throw (new ModelNotFoundException)->setModel(Diagnosis::class);
        }

        return $diagnosis;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $patientId, array $data): Diagnosis
    {
        $this->assertPatientExists($patientId);
        $this->assertProfessionalValid($data['professional_id'] ?? null);

        $data['patient_id'] = $patientId;

        return $this->diagnoses->create($data)->load('professional');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $patientId, int $id, array $data): Diagnosis
    {
        $diagnosis = $this->find($patientId, $id);
        $this->assertProfessionalValid($data['professional_id'] ?? null);

        unset($data['patient_id']);

        return $this->diagnoses->update($diagnosis, $data)->load('professional');
    }

    public function delete(int $patientId, int $id): void
    {
        $this->diagnoses->delete($this->find($patientId, $id));
    }

    private function assertPatientExists(int $patientId): void
    {
        if ($this->patients->find($patientId) === null) {
            throw (new ModelNotFoundException)->setModel(\App\Models\Patient::class);
        }
    }

    private function assertProfessionalValid(?int $professionalId): void
    {
        if ($professionalId === null) {
            return;
        }

        if ($this->professionals->find($professionalId) === null) {
            throw ValidationException::withMessages([
                'professional_id' => ['El profesional indicado no pertenece a la clinica.'],
            ]);
        }
    }
}
