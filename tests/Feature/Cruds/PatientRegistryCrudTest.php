<?php

namespace Tests\Feature\Cruds;

use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class PatientRegistryCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Spatie cachea permisos/roles entre tests si no se limpia (RefreshDatabase
        // resetea la BD pero no el cache); sin esto, el test de diagnosticos podia
        // leer un permiso "pegado" de otro test dentro del mismo proceso.
        Cache::flush();
    }

    public function test_staff_puede_registrar_paciente_sin_password(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [
                'nombre' => 'Juan Paciente',
                'email' => 'juan@correo.test',
                'telefono' => '+56911111111',
                'fecha_nacimiento' => '1990-05-20',
                'notas' => 'Alergico a la penicilina',
            ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'Juan Paciente');

        $this->assertDatabaseHas('patients', [
            'email' => 'juan@correo.test',
            'tenant_id' => $tenant->id,
            'password' => null,
        ]);
    }

    public function test_staff_puede_registrar_paciente_con_password_hasheada(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [
                'nombre' => 'Con Pass',
                'email' => 'pass@correo.test',
                'fecha_nacimiento' => '1990-05-20',
                'password' => 'secret123',
            ])
            ->assertCreated();

        $patient = Patient::withoutGlobalScopes()->where('email', 'pass@correo.test')->first();
        $this->assertNotNull($patient->password);
        $this->assertNotSame('secret123', $patient->password);
    }

    public function test_email_duplicado_en_misma_clinica_falla(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'dup@correo.test']);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [
                'nombre' => 'Otro',
                'email' => 'dup@correo.test',
                'fecha_nacimiento' => '1990-05-20',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_staff_puede_listar_actualizar_y_eliminar_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->staffTokenFor($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Old']);

        $this->withToken($token)->getJson('/api/staff/patients')
            ->assertOk()->assertJsonStructure(['data', 'meta', 'links']);

        $this->withToken($token)->putJson("/api/staff/patients/{$patient->id}", ['nombre' => 'Nuevo'])
            ->assertOk()->assertJsonPath('data.nombre', 'Nuevo');

        $this->withToken($token)->deleteJson("/api/staff/patients/{$patient->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_admin_ve_los_diagnosticos_anidados_en_el_detalle_del_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        Diagnosis::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

        // 'admin' tiene diagnoses.ver: la ficha viene con el detalle clinico.
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->withToken($admin->createToken('staff', ['staff'])->plainTextToken)
            ->getJson("/api/staff/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.diagnoses');
    }

    public function test_recepcion_no_ve_diagnosticos_anidados_en_el_detalle_del_paciente(): void
    {
        // Guard 'staff' aparte (test separado en vez de una segunda request
        // en el mismo metodo con otro usuario): el guard de Sanctum memoiza
        // el usuario resuelto en el mismo proceso de test, asi que un segundo
        // withToken() con un token distinto seguia leyendo el primero.
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        Diagnosis::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

        // 'recepcion' NO tiene diagnoses.ver: el endpoint de detalle de
        // paciente no debe filtrar el historial clinico solo por venir
        // anidado (el endpoint dedicado de diagnosticos ya lo bloqueaba,
        // pero este no).
        $recepcion = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $this->withToken($recepcion->createToken('staff', ['staff'])->plainTextToken)
            ->getJson("/api/staff/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.diagnoses');
    }

    public function test_crear_paciente_valida_campos_requeridos(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'email', 'fecha_nacimiento']);
    }

    public function test_busca_pacientes_por_nombre(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Ignacio Luarca']);
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Otra Persona']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=ignacio')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Ignacio Luarca');
    }

    public function test_la_busqueda_por_nombre_no_distingue_mayusculas(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Maria Jose']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=MARIA')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_busca_pacientes_por_apellido(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Ana', 'apellido' => 'Perez']);
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Beto', 'apellido' => 'Soto']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=perez')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.apellido', 'Perez');
    }

    public function test_busca_pacientes_por_email(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'juan.perez@correo.test']);
        Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'otra@correo.test']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=juan.perez')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_busca_pacientes_por_rut_con_o_sin_puntos(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '12.345.678-9']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search='.urlencode('12.345.678-9'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=12345678-9')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_busqueda_sin_resultados_devuelve_lista_vacia(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Ana']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=nadie-coincide-xyz')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sin_search_devuelve_todos_los_pacientes(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_la_busqueda_no_cruza_pacientes_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ignacio Ajeno']);

        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Ignacio Propio']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/patients?search=ignacio')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Ignacio Propio');
    }
}
