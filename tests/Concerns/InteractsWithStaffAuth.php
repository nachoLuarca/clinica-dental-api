<?php

namespace Tests\Concerns;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;

/**
 * Utilidades para los tests de los CRUDs del staff: crear un tenant, un token
 * de staff (que el middleware 'tenant' resuelve al tenant del usuario) y un
 * token de paciente (para verificar que NO accede a estos endpoints).
 */
trait InteractsWithStaffAuth
{
    protected function staffTokenFor(Tenant $tenant): string
    {
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        return $staff->createToken('staff', ['staff'])->plainTextToken;
    }

    protected function patientTokenFor(Tenant $tenant): string
    {
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        return $patient->createToken('paciente', ['paciente'])->plainTextToken;
    }
}
