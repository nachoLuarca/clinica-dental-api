<?php

namespace Tests\Concerns;

use App\Models\Patient;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Support\Carbon;

/**
 * Utilidades para armar el escenario de disponibilidad/reservas en los tests:
 * profesional con horario, tratamiento con duracion y token de un paciente
 * concreto (necesitamos su id, no solo el token).
 */
trait InteractsWithReservas
{
    protected function profesionalConHorario(Tenant $tenant, int $diaSemana, string $inicio, string $fin): Professional
    {
        $prof = Professional::factory()->create(['tenant_id' => $tenant->id]);

        ProfessionalSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);

        return $prof;
    }

    protected function tratamiento(Tenant $tenant, int $duracion): Treatment
    {
        return Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'duracion_minutos' => $duracion,
        ]);
    }

    /**
     * Devuelve el paciente y su token Bearer del guard 'paciente'.
     *
     * @return array{0: Patient, 1: string}
     */
    protected function pacienteConToken(Tenant $tenant): array
    {
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        return [$patient, $patient->createToken('paciente', ['paciente'])->plainTextToken];
    }

    /**
     * Una fecha futura cuyo dia de la semana (Carbon: 0=domingo..6=sabado) sea
     * el pedido. Asi el horario del profesional aplica en esa fecha.
     */
    protected function proximaFechaEnDiaSemana(int $diaSemana): Carbon
    {
        $fecha = Carbon::parse('2030-01-07')->startOfDay(); // lunes conocido
        while ($fecha->dayOfWeek !== $diaSemana) {
            $fecha->addDay();
        }

        return $fecha;
    }
}
