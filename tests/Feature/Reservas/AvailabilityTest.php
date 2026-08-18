<?php

namespace Tests\Feature\Reservas;

use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use InteractsWithReservas, InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Evita arrastre de cache entre tests (claves deterministas por :memory:).
        Cache::flush();
    }

    public function test_calcula_slots_libres_segun_horario_y_duracion(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1); // lunes
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);

        // 09:00-11:00 con slots de 60 min => 09:00 y 10:00 (2 slots).
        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}")
            ->assertOk()
            ->assertJsonPath('data.duracion_minutos', 60)
            ->assertJsonCount(2, 'data.slots')
            ->assertJsonPath('data.slots.0.inicio', '09:00')
            ->assertJsonPath('data.slots.1.inicio', '10:00');
    }

    public function test_excluye_slots_ocupados_por_citas_existentes(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        // Cita activa 09:00-10:00 => solo debe quedar libre 10:00.
        Appointment::factory()
            ->at($fecha->copy()->setTime(9, 0), 60)
            ->create([
                'tenant_id' => $tenant->id,
                'professional_id' => $prof->id,
                'patient_id' => $patient->id,
                'treatment_id' => $treatment->id,
            ]);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}")
            ->assertOk()
            ->assertJsonCount(1, 'data.slots')
            ->assertJsonPath('data.slots.0.inicio', '10:00');
    }

    public function test_cita_cancelada_no_ocupa_slot(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        Appointment::factory()
            ->cancelada()
            ->at($fecha->copy()->setTime(9, 0), 60)
            ->create([
                'tenant_id' => $tenant->id,
                'professional_id' => $prof->id,
                'patient_id' => $patient->id,
                'treatment_id' => $treatment->id,
            ]);

        // La cancelada no bloquea: siguen los 2 slots.
        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}")
            ->assertOk()
            ->assertJsonCount(2, 'data.slots');
    }

    public function test_sin_horario_ese_dia_no_hay_slots(): void
    {
        $tenant = Tenant::factory()->create();
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00'); // lunes
        $treatment = $this->tratamiento($tenant, 60);
        $fecha = $this->proximaFechaEnDiaSemana(2); // martes: sin horario

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}")
            ->assertOk()
            ->assertJsonCount(0, 'data.slots');
    }

    public function test_disponibilidad_requiere_autenticacion(): void
    {
        $this->getJson('/api/staff/availability?professional_id=1&treatment_id=1&fecha=2030-01-07')
            ->assertUnauthorized();
    }

    public function test_disponibilidad_valida_parametros(): void
    {
        $tenant = Tenant::factory()->create();

        // professional_id es opcional (paso 11, modo "cualquier profesional
        // disponible"): sin el, no deberia figurar en los errores.
        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/availability')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['treatment_id', 'fecha'])
            ->assertJsonMissingValidationErrors(['professional_id']);
    }

    public function test_no_ve_disponibilidad_de_profesional_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $profB = $this->profesionalConHorario($tenantB, 1, '09:00', '11:00');
        $treatmentA = $this->tratamiento($tenantA, 60);

        // Staff de A pide disponibilidad de un profesional de B => 404.
        $this->withToken($this->staffTokenFor($tenantA))
            ->getJson("/api/staff/availability?professional_id={$profB->id}&treatment_id={$treatmentA->id}&fecha={$fecha->toDateString()}")
            ->assertNotFound();
    }
}
