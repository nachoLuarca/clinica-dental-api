<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de datos de demo/desarrollo: deja una clinica de prueba lista para
 * probar el sistema end-to-end sin pasos manuales (staff, profesionales,
 * tratamientos y un paciente de ejemplo con diagnostico).
 *
 * Idempotente: usa firstOrCreate/updateOrCreate en todos lados, asi que correr
 * `php artisan db:seed` varias veces no duplica nada. No usa BelongsToTenant
 * para asignar tenant_id automatico porque en el contexto de un seeder de CLI
 * no hay usuario autenticado (TenantContext vacio) -> se pasa tenant_id a mano
 * en cada modelo aislado por tenant.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'clinica-demo'],
            [
                'nombre' => 'Clinica Demo',
                'activo' => true,
            ]
        );

        // El staff ya puede existir por haberse registrado a mano via el
        // endpoint de registro: no lo tocamos si ya esta, solo lo creamos si
        // falta, para no pisar una password distinta que el usuario haya
        // puesto en la practica.
        User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'staff@demo.cl'],
            [
                'name' => 'Staff Demo',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $profesionalUno = Professional::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'maria.gonzalez@clinica-demo.cl'],
            [
                'nombre' => 'Maria',
                'apellido' => 'Gonzalez',
                'especialidad' => 'Odontologia General',
                'activo' => true,
            ]
        );

        Professional::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'juan.perez@clinica-demo.cl'],
            [
                'nombre' => 'Juan',
                'apellido' => 'Perez',
                'especialidad' => 'Ortodoncia',
                'activo' => true,
            ]
        );

        $tratamientos = [
            [
                'nombre' => 'Limpieza dental',
                'descripcion' => 'Profilaxis y destartraje.',
                'precio' => 25000,
                'es_diferencial' => false,
            ],
            [
                'nombre' => 'Extraccion simple',
                'descripcion' => 'Extraccion de pieza dental sin complicaciones.',
                'precio' => 35000,
                'es_diferencial' => false,
            ],
            [
                'nombre' => 'Resina compuesta',
                'descripcion' => 'Obturacion con resina en pieza dental.',
                'precio' => 30000,
                'es_diferencial' => false,
            ],
        ];

        foreach ($tratamientos as $tratamiento) {
            Treatment::updateOrCreate(
                ['tenant_id' => $tenant->id, 'nombre' => $tratamiento['nombre']],
                [
                    'descripcion' => $tratamiento['descripcion'],
                    'precio' => $tratamiento['precio'],
                    'es_diferencial' => $tratamiento['es_diferencial'],
                    'activo' => true,
                ]
            );
        }

        // Paciente de ejemplo con un diagnostico, para poder probar la ficha
        // clinica end-to-end sin pasos manuales. Si algo aqui fallara, no debe
        // tumbar el resto del seeder (staff/profesionales/tratamientos ya
        // quedaron creados arriba).
        try {
            $paciente = Patient::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => 'paciente@demo.cl'],
                [
                    'nombre' => 'Pedro Paciente',
                    'password' => Hash::make('password123'),
                    'fecha_nacimiento' => '1990-05-15',
                    'email_verified_at' => now(),
                ]
            );

            Diagnosis::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'patient_id' => $paciente->id,
                    'descripcion' => 'Caries en pieza 26, requiere obturacion.',
                ],
                [
                    'professional_id' => $profesionalUno->id,
                    'fecha' => now()->toDateString(),
                    'notas' => 'Paciente refiere sensibilidad al frio.',
                ]
            );
        } catch (\Throwable $e) {
            $this->command?->warn('No se pudo sembrar el paciente/diagnostico de ejemplo: '.$e->getMessage());
        }
    }
}
