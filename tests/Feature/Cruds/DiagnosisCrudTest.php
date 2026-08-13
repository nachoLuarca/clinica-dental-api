<?php

namespace Tests\Feature\Cruds;

use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class DiagnosisCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_puede_crear_diagnostico_para_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $prof = Professional::factory()->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson("/api/staff/patients/{$patient->id}/diagnoses", [
                'professional_id' => $prof->id,
                'fecha' => '2026-08-01',
                'descripcion' => 'Caries en molar',
                'notas' => 'Requiere control',
            ])
            ->assertCreated()
            ->assertJsonPath('data.descripcion', 'Caries en molar')
            ->assertJsonPath('data.professional.id', $prof->id);

        $this->assertDatabaseHas('diagnoses', [
            'patient_id' => $patient->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_no_se_puede_asignar_profesional_de_otro_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otro = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $profAjeno = Professional::factory()->create(['tenant_id' => $otro->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson("/api/staff/patients/{$patient->id}/diagnoses", [
                'professional_id' => $profAjeno->id,
                'fecha' => '2026-08-01',
                'descripcion' => 'X',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['professional_id']);
    }

    public function test_staff_lista_diagnosticos_de_un_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        Diagnosis::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
        ]);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/patients/{$patient->id}/diagnoses")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_diagnostico_de_otro_paciente_no_es_accesible_por_ruta_cruzada(): void
    {
        $tenant = Tenant::factory()->create();
        $patientA = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $patientB = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $diag = Diagnosis::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patientB->id,
        ]);

        // Pedir el diagnostico de B a traves de la ruta de A -> 404.
        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/patients/{$patientA->id}/diagnoses/{$diag->id}")
            ->assertNotFound();
    }

    public function test_staff_puede_actualizar_y_eliminar_diagnostico(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->staffTokenFor($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $diag = Diagnosis::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'descripcion' => 'Old',
        ]);

        $this->withToken($token)
            ->putJson("/api/staff/patients/{$patient->id}/diagnoses/{$diag->id}", ['descripcion' => 'Nuevo'])
            ->assertOk()
            ->assertJsonPath('data.descripcion', 'Nuevo');

        $this->withToken($token)
            ->deleteJson("/api/staff/patients/{$patient->id}/diagnoses/{$diag->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('diagnoses', ['id' => $diag->id]);
    }
}
