<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Convenio;
use App\Models\Diagnosis;
use App\Models\Especialidad;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Sucursal;
use App\Models\Tenant;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Auth\RoleProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder de datos de demo/desarrollo: deja una clinica de prueba funcionando
 * de punta a punta (staff con los 3 roles, profesionales CON horario cargado,
 * catalogo, pacientes, citas pasadas/futuras, diagnosticos y un presupuesto),
 * para poder probar los frontends sin pasos manuales previos.
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

    private Tenant $tenant;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->tenant = Tenant::firstOrCreate(
            ['slug' => 'clinica-demo'],
            [
                'nombre' => 'Clinica Demo',
                'activo' => true,
            ]
        );

        $this->sembrarStaff();
        [$profesionalGeneral, $profesionalOrtodoncia, $profesionalEndodoncia, $profesionalPeriodoncia, $profesionalRehabilitacion] = $this->sembrarProfesionales();
        $this->sembrarSucursales($profesionalGeneral, $profesionalOrtodoncia, $profesionalEndodoncia, $profesionalPeriodoncia, $profesionalRehabilitacion);
        $this->sembrarConvenios();
        [$tratamientos, $especialidades] = $this->sembrarCatalogo();
        $this->asignarEspecialidadesAProfesionales(
            $profesionalGeneral,
            $profesionalOrtodoncia,
            $profesionalEndodoncia,
            $profesionalPeriodoncia,
            $profesionalRehabilitacion,
            $especialidades,
        );
        $pacientes = $this->sembrarPacientes();
        $this->sembrarCitasDiagnosticosYPresupuesto($profesionalGeneral, $profesionalOrtodoncia, $tratamientos, $pacientes);
    }

    /**
     * Un staff por rol de la matriz base, para poder probar cada vista del
     * portal admin sin tener que crear usuarios a mano.
     */
    private function sembrarStaff(): void
    {
        // El staff ya puede existir por haberse registrado a mano via el
        // endpoint de registro: no lo tocamos si ya esta, solo lo creamos si
        // falta, para no pisar una password distinta que el usuario haya
        // puesto en la practica.
        $admin = User::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'staff@demo.cl'],
            [
                'name' => 'Staff Demo',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // El auto-registro publico siempre asigna 'recepcion' (menor
        // privilegio); el staff de demo se eleva a 'admin' a mano aca para
        // poder probar el sistema completo sin pasos manuales.
        $roles = app(RoleProvisioner::class);
        $roles->asignarRol($admin, 'admin');

        $profesionalStaff = User::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'profesional@demo.cl'],
            [
                'name' => 'Maria Gonzalez (staff)',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $roles->asignarRol($profesionalStaff, 'profesional');

        $recepcionStaff = User::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'recepcion@demo.cl'],
            [
                'name' => 'Recepcion Demo',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );
        $roles->asignarRol($recepcionStaff, 'recepcion');
    }

    /**
     * 5 profesionales activos, cada uno CON horario cargado (sin esto la
     * disponibilidad siempre da vacia, sin importar la fecha que se pida):
     * uno por cada una de las 3 especialidades originales, mas 2 que cubren
     * entre ambos las 5 especialidades restantes del catalogo (paso 12),
     * para que el filtro de reserva por especialidad tenga al menos un
     * candidato elegible en las 8.
     *
     * @return array{0: Professional, 1: Professional, 2: Professional, 3: Professional, 4: Professional}
     */
    private function sembrarProfesionales(): array
    {
        $general = Professional::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'maria.gonzalez@clinica-demo.cl'],
            [
                'nombre' => 'Maria',
                'apellido' => 'Gonzalez',
                'especialidad' => 'Odontologia General',
                'activo' => true,
            ]
        );
        // Lunes a viernes, manana y tarde (con pausa de almuerzo).
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $this->sembrarHorario($general, $dia, '09:00', '13:00');
            $this->sembrarHorario($general, $dia, '15:00', '18:00');
        }

        $ortodoncia = Professional::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'juan.perez@clinica-demo.cl'],
            [
                'nombre' => 'Juan',
                'apellido' => 'Perez',
                'especialidad' => 'Ortodoncia',
                'activo' => true,
            ]
        );
        // Martes y jueves, jornada completa.
        foreach ([2, 4] as $dia) {
            $this->sembrarHorario($ortodoncia, $dia, '10:00', '17:00');
        }

        $endodoncia = Professional::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'camila.rojas@clinica-demo.cl'],
            [
                'nombre' => 'Camila',
                'apellido' => 'Rojas',
                'especialidad' => 'Endodoncia',
                'activo' => true,
            ]
        );
        // Lunes, miercoles y viernes por la manana.
        foreach ([1, 3, 5] as $dia) {
            $this->sembrarHorario($endodoncia, $dia, '09:00', '14:00');
        }

        $periodoncia = Professional::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'valentina.soto@clinica-demo.cl'],
            [
                'nombre' => 'Valentina',
                'apellido' => 'Soto',
                'especialidad' => 'Periodoncia y Odontopediatria',
                'activo' => true,
            ]
        );
        // Lunes, miercoles y viernes por la manana.
        foreach ([1, 3, 5] as $dia) {
            $this->sembrarHorario($periodoncia, $dia, '09:00', '13:00');
        }

        $rehabilitacion = Professional::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'email' => 'diego.fuentes@clinica-demo.cl'],
            [
                'nombre' => 'Diego',
                'apellido' => 'Fuentes',
                'especialidad' => 'Rehabilitacion Oral, Implantologia y Cirugia Maxilofacial',
                'activo' => true,
            ]
        );
        // Martes y jueves, jornada completa.
        foreach ([2, 4] as $dia) {
            $this->sembrarHorario($rehabilitacion, $dia, '10:00', '17:00');
        }

        return [$general, $ortodoncia, $endodoncia, $periodoncia, $rehabilitacion];
    }

    private function sembrarHorario(Professional $professional, int $diaSemana, string $inicio, string $fin): void
    {
        ProfessionalSchedule::firstOrCreate([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $professional->id,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ]);
    }

    /**
     * 2 sedes demo, cada una con su horario (puede variar por dia). Los 5
     * profesionales demo quedan repartidos entre ambas.
     */
    private function sembrarSucursales(
        Professional $general,
        Professional $ortodoncia,
        Professional $endodoncia,
        Professional $periodoncia,
        Professional $rehabilitacion,
    ): void {
        $centro = Sucursal::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'nombre' => 'Sede Centro'],
            ['direccion' => 'Alameda 1234', 'comuna' => 'Santiago', 'telefono' => '+56221111111'],
        );
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $centro->horarios()->firstOrCreate(['tenant_id' => $this->tenant->id, 'dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '19:00']);
        }
        $centro->horarios()->firstOrCreate(['tenant_id' => $this->tenant->id, 'dia_semana' => 6, 'hora_inicio' => '09:00', 'hora_fin' => '14:00']);

        $providencia = Sucursal::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'nombre' => 'Sede Providencia'],
            ['direccion' => 'Av. Providencia 5678', 'comuna' => 'Providencia', 'telefono' => '+56222222222'],
        );
        foreach ([2, 4] as $dia) {
            $providencia->horarios()->firstOrCreate(['tenant_id' => $this->tenant->id, 'dia_semana' => $dia, 'hora_inicio' => '10:00', 'hora_fin' => '18:00']);
        }

        $general->update(['sucursal_id' => $centro->id]);
        $endodoncia->update(['sucursal_id' => $centro->id]);
        $periodoncia->update(['sucursal_id' => $centro->id]);
        $ortodoncia->update(['sucursal_id' => $providencia->id]);
        $rehabilitacion->update(['sucursal_id' => $providencia->id]);
    }

    /**
     * Catalogo chico de convenios demo (paso 12).
     */
    private function sembrarConvenios(): void
    {
        $definiciones = [
            ['nombre' => 'Fonasa', 'tipo' => 'fonasa'],
            ['nombre' => 'Isapre Colmena', 'tipo' => 'isapre'],
            ['nombre' => 'Caja Los Andes', 'tipo' => 'caja_compensacion'],
        ];

        foreach ($definiciones as $datos) {
            Convenio::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'nombre' => $datos['nombre']],
                ['tipo' => $datos['tipo']],
            );
        }
    }

    /**
     * Catalogo completo de especialidades odontologicas + los tratamientos
     * que cubre cada una, enlazados por FK real (Treatment::especialidad_id,
     * paso 12). Sin esto, el filtro de reserva por especialidad no tiene
     * nada que filtrar (ver ProfessionalRepository::allActivosParaEspecialidad:
     * sin tratamiento con especialidad asignada, no filtra -asi que esto no
     * es estrictamente necesario para que la demo funcione-, pero sin
     * sembrarlo no se puede probar el filtro en si).
     *
     * @return array{0: array<string, Treatment>, 1: array<string, Especialidad>}
     */
    private function sembrarCatalogo(): array
    {
        $definiciones = [
            'Odontologia General' => [
                'Limpieza dental' => [
                    'categoria' => 'Prevencion',
                    'descripcion' => 'Profilaxis y destartraje.',
                    'incluye' => ['Destartraje', 'Pulido dental', 'Revision general'],
                    'precio' => 25000,
                    'duracion_minutos' => 45,
                ],
                'Extraccion simple' => [
                    'categoria' => 'Cirugia',
                    'descripcion' => 'Extraccion de pieza dental sin complicaciones.',
                    'incluye' => ['Anestesia local', 'Extraccion', 'Indicaciones post-operatorias'],
                    'precio' => 35000,
                    'duracion_minutos' => 30,
                ],
                'Resina compuesta' => [
                    'categoria' => 'Restauracion',
                    'descripcion' => 'Obturacion con resina en pieza dental.',
                    'incluye' => ['Anestesia local si es necesaria', 'Obturacion con resina'],
                    'precio' => 30000,
                    'duracion_minutos' => 45,
                ],
                'Blanqueamiento dental' => [
                    'categoria' => 'Estetica',
                    'descripcion' => 'Blanqueamiento profesional en consulta.',
                    'incluye' => ['Evaluacion previa', 'Aplicacion de gel blanqueador', 'Kit de cuidado post-sesion'],
                    'precio' => 80000,
                    'duracion_minutos' => 60,
                ],
                'Evaluacion inicial' => [
                    'categoria' => 'Prevencion',
                    'descripcion' => 'Revision odontologica completa y diagnostico inicial.',
                    'incluye' => ['Examen clinico', 'Diagnostico', 'Plan de tratamiento sugerido'],
                    'precio' => 15000,
                    'duracion_minutos' => 30,
                ],
            ],
            'Ortodoncia' => [
                'Control de ortodoncia' => [
                    'categoria' => 'Ortodoncia',
                    'descripcion' => 'Ajuste y revision mensual de brackets/alineadores.',
                    'incluye' => ['Revision', 'Ajuste de brackets/alineadores'],
                    'precio' => 20000,
                    'duracion_minutos' => 20,
                ],
                'Frenillos mecanicos' => [
                    'categoria' => 'Ortodoncia',
                    'descripcion' => 'Instalacion de brackets metalicos tradicionales.',
                    'incluye' => ['Evaluacion', 'Instalacion de brackets', 'Indicaciones de cuidado'],
                    'precio' => 350000,
                    'duracion_minutos' => 90,
                ],
                'Alineadores invisibles' => [
                    'categoria' => 'Ortodoncia',
                    'descripcion' => 'Evaluacion e inicio de tratamiento con alineadores transparentes removibles.',
                    'incluye' => ['Escaneo digital', 'Plan de alineacion', 'Primer set de alineadores'],
                    'precio' => 450000,
                    'duracion_minutos' => 60,
                ],
                'Retenedores' => [
                    'categoria' => 'Ortodoncia',
                    'descripcion' => 'Confeccion y entrega de retenedores post-tratamiento de ortodoncia.',
                    'incluye' => ['Toma de impresion', 'Confeccion de retenedor', 'Indicaciones de uso'],
                    'precio' => 60000,
                    'duracion_minutos' => 30,
                ],
                'Aparatos de expansion palatina' => [
                    'categoria' => 'Ortodoncia',
                    'descripcion' => 'Instalacion de aparato para ampliar el paladar en casos de maxilar estrecho.',
                    'incluye' => ['Evaluacion', 'Instalacion del aparato', 'Control de activacion'],
                    'precio' => 280000,
                    'duracion_minutos' => 60,
                ],
            ],
            'Endodoncia' => [
                'Tratamiento de conducto' => [
                    'categoria' => 'Endodoncia',
                    'descripcion' => 'Endodoncia (tratamiento de conducto) en pieza dental danada.',
                    'incluye' => ['Anestesia local', 'Limpieza y sellado del conducto', 'Control post-operatorio'],
                    'precio' => 60000,
                    'duracion_minutos' => 60,
                ],
                'Retratamiento de conducto' => [
                    'categoria' => 'Endodoncia',
                    'descripcion' => 'Repeticion de un tratamiento de conducto previo que no sano correctamente.',
                    'incluye' => ['Anestesia local', 'Remocion de material anterior', 'Limpieza y resellado'],
                    'precio' => 90000,
                    'duracion_minutos' => 75,
                ],
                'Apicectomia' => [
                    'categoria' => 'Endodoncia',
                    'descripcion' => 'Cirugia menor para remover la punta de la raiz infectada.',
                    'incluye' => ['Anestesia local', 'Cirugia apical', 'Control post-operatorio'],
                    'precio' => 150000,
                    'duracion_minutos' => 60,
                ],
            ],
            'Periodoncia' => [
                'Limpieza profunda' => [
                    'categoria' => 'Periodoncia',
                    'descripcion' => 'Raspaje y alisado radicular bajo la encia para tratar enfermedad periodontal.',
                    'incluye' => ['Anestesia local si es necesaria', 'Raspaje y alisado radicular'],
                    'precio' => 45000,
                    'duracion_minutos' => 60,
                ],
                'Injerto de encia' => [
                    'categoria' => 'Periodoncia',
                    'descripcion' => 'Cirugia para cubrir raices expuestas por recesion de encia.',
                    'incluye' => ['Anestesia local', 'Injerto de tejido', 'Control post-operatorio'],
                    'precio' => 200000,
                    'duracion_minutos' => 90,
                ],
                'Cirugia periodontal' => [
                    'categoria' => 'Periodoncia',
                    'descripcion' => 'Cirugia para tratar enfermedad periodontal avanzada.',
                    'incluye' => ['Anestesia local', 'Cirugia de encia', 'Control post-operatorio'],
                    'precio' => 180000,
                    'duracion_minutos' => 75,
                ],
            ],
            'Odontopediatria' => [
                'Sellantes de fosas y fisuras' => [
                    'categoria' => 'Odontopediatria',
                    'descripcion' => 'Aplicacion de sellante preventivo en muelas de ninos.',
                    'incluye' => ['Limpieza previa', 'Aplicacion de sellante'],
                    'precio' => 15000,
                    'duracion_minutos' => 20,
                ],
                'Tapadura infantil' => [
                    'categoria' => 'Odontopediatria',
                    'descripcion' => 'Obturacion de caries en dientes de leche.',
                    'incluye' => ['Anestesia local si es necesaria', 'Obturacion'],
                    'precio' => 25000,
                    'duracion_minutos' => 30,
                ],
                'Pulpotomia' => [
                    'categoria' => 'Odontopediatria',
                    'descripcion' => 'Tratamiento del nervio en dientes de leche.',
                    'incluye' => ['Anestesia local', 'Tratamiento pulpar', 'Restauracion'],
                    'precio' => 45000,
                    'duracion_minutos' => 45,
                ],
            ],
            'Rehabilitacion Oral' => [
                'Corona fija' => [
                    'categoria' => 'Rehabilitacion Oral',
                    'descripcion' => 'Confeccion e instalacion de corona fija sobre pieza dental.',
                    'incluye' => ['Preparacion de la pieza', 'Toma de impresion', 'Instalacion de corona'],
                    'precio' => 180000,
                    'duracion_minutos' => 60,
                ],
                'Puente dental' => [
                    'categoria' => 'Rehabilitacion Oral',
                    'descripcion' => 'Reposicion de una o mas piezas ausentes con puente fijo.',
                    'incluye' => ['Preparacion de pilares', 'Toma de impresion', 'Instalacion de puente'],
                    'precio' => 350000,
                    'duracion_minutos' => 90,
                ],
                'Carilla estetica' => [
                    'categoria' => 'Rehabilitacion Oral',
                    'descripcion' => 'Carilla de porcelana o resina para mejorar la estetica dental.',
                    'incluye' => ['Preparacion minima', 'Toma de impresion', 'Instalacion de carilla'],
                    'precio' => 150000,
                    'duracion_minutos' => 60,
                ],
                'Protesis removible' => [
                    'categoria' => 'Rehabilitacion Oral',
                    'descripcion' => 'Confeccion de protesis dental removible, total o parcial.',
                    'incluye' => ['Toma de impresion', 'Prueba de protesis', 'Entrega e instalacion'],
                    'precio' => 250000,
                    'duracion_minutos' => 60,
                ],
            ],
            'Implantologia' => [
                'Implante dental de titanio' => [
                    'categoria' => 'Implantologia',
                    'descripcion' => 'Colocacion quirurgica de implante de titanio.',
                    'incluye' => ['Anestesia local', 'Colocacion del implante', 'Control post-operatorio'],
                    'precio' => 450000,
                    'duracion_minutos' => 90,
                ],
                'Injerto oseo' => [
                    'categoria' => 'Implantologia',
                    'descripcion' => 'Injerto de hueso para preparar la zona antes de un implante.',
                    'incluye' => ['Anestesia local', 'Injerto de hueso', 'Control post-operatorio'],
                    'precio' => 300000,
                    'duracion_minutos' => 75,
                ],
                'Elevacion de seno maxilar' => [
                    'categoria' => 'Implantologia',
                    'descripcion' => 'Cirugia para levantar el piso del seno maxilar antes de implantar en el maxilar superior.',
                    'incluye' => ['Anestesia local', 'Elevacion de seno', 'Control post-operatorio'],
                    'precio' => 400000,
                    'duracion_minutos' => 90,
                ],
            ],
            'Cirugia Maxilofacial' => [
                'Extraccion de muela del juicio impactada' => [
                    'categoria' => 'Cirugia Maxilofacial',
                    'descripcion' => 'Cirugia para extraer una muela del juicio impactada o incluida.',
                    'incluye' => ['Anestesia local', 'Cirugia de extraccion', 'Indicaciones post-operatorias'],
                    'precio' => 120000,
                    'duracion_minutos' => 60,
                ],
                'Cirugia reconstructiva maxilofacial' => [
                    'categoria' => 'Cirugia Maxilofacial',
                    'descripcion' => 'Cirugia reconstructiva de tejidos y huesos maxilofaciales.',
                    'incluye' => ['Evaluacion', 'Cirugia reconstructiva', 'Control post-operatorio'],
                    'precio' => 600000,
                    'duracion_minutos' => 120,
                ],
                'Correccion de mandibula' => [
                    'categoria' => 'Cirugia Maxilofacial',
                    'descripcion' => 'Cirugia ortognatica para corregir la posicion de la mandibula.',
                    'incluye' => ['Evaluacion', 'Cirugia ortognatica', 'Control post-operatorio'],
                    'precio' => 800000,
                    'duracion_minutos' => 120,
                ],
            ],
        ];

        $tratamientos = [];
        $especialidades = [];

        foreach ($definiciones as $nombreEspecialidad => $tratamientosDeEspecialidad) {
            $especialidad = Especialidad::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'nombre' => $nombreEspecialidad],
            );
            $especialidades[$nombreEspecialidad] = $especialidad;

            foreach ($tratamientosDeEspecialidad as $nombre => $datos) {
                $tratamientos[$nombre] = Treatment::updateOrCreate(
                    ['tenant_id' => $this->tenant->id, 'nombre' => $nombre],
                    [
                        'categoria' => $datos['categoria'],
                        'especialidad_id' => $especialidad->id,
                        'descripcion' => $datos['descripcion'],
                        'incluye' => $datos['incluye'],
                        'precio' => $datos['precio'],
                        'duracion_minutos' => $datos['duracion_minutos'],
                        'slug' => Str::slug($nombre),
                        'es_diferencial' => false,
                        'activo' => true,
                    ]
                );
            }
        }

        return [$tratamientos, $especialidades];
    }

    /**
     * Asigna a cada uno de los 5 profesionales demo la(s) especialidad(es)
     * que le corresponden (de las 8 sembradas en sembrarCatalogo), para que
     * el filtro de reserva por especialidad tenga al menos un candidato
     * elegible en las 8.
     *
     * @param  array<string, Especialidad>  $especialidades
     */
    private function asignarEspecialidadesAProfesionales(
        Professional $general,
        Professional $ortodoncia,
        Professional $endodoncia,
        Professional $periodoncia,
        Professional $rehabilitacion,
        array $especialidades,
    ): void {
        $general->especialidades()->sync([$especialidades['Odontologia General']->id]);
        $ortodoncia->especialidades()->sync([$especialidades['Ortodoncia']->id]);
        $endodoncia->especialidades()->sync([$especialidades['Endodoncia']->id]);
        $periodoncia->especialidades()->sync([
            $especialidades['Periodoncia']->id,
            $especialidades['Odontopediatria']->id,
        ]);
        $rehabilitacion->especialidades()->sync([
            $especialidades['Rehabilitacion Oral']->id,
            $especialidades['Implantologia']->id,
            $especialidades['Cirugia Maxilofacial']->id,
        ]);
    }

    /**
     * @return array<int, Patient>
     */
    private function sembrarPacientes(): array
    {
        $definiciones = [
            [
                'email' => 'paciente@demo.cl',
                'nombre' => 'Pedro Paciente',
                'rut' => '11.111.111-1',
                'fecha_nacimiento' => '1990-05-15',
            ],
            [
                'email' => 'ana.martinez@demo.cl',
                'nombre' => 'Ana Martinez',
                'rut' => '22.222.222-2',
                'fecha_nacimiento' => '1985-11-02',
            ],
            [
                'email' => 'roberto.diaz@demo.cl',
                'nombre' => 'Roberto Diaz',
                'rut' => '33.333.333-3',
                'fecha_nacimiento' => '2001-02-20',
            ],
        ];

        return array_map(function (array $datos) {
            $patient = Patient::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'email' => $datos['email']],
                [
                    'nombre' => $datos['nombre'],
                    'rut' => $datos['rut'],
                    'password' => Hash::make('password123'),
                    'fecha_nacimiento' => $datos['fecha_nacimiento'],
                    'email_verified_at' => now(),
                ]
            );

            // El paciente demo original (paso 2) se creo antes de que 'rut'
            // existiera como columna: se completa aca si a un paciente ya
            // existente le falta, sin tocar nada mas (password incluida).
            if ($patient->rut === null) {
                $patient->update(['rut' => $datos['rut']]);
            }

            return $patient;
        }, $definiciones);
    }

    /**
     * Citas pasadas (completadas) y futuras (reservadas), un diagnostico por
     * paciente y un presupuesto con lineas, para que las pantallas de agenda,
     * ficha clinica y presupuestos no arranquen vacias. Envuelto en try/catch:
     * si algo falla aca no debe tumbar el resto del seeder (staff/profesionales/
     * tratamientos/pacientes ya quedaron creados arriba).
     *
     * @param  array<string, Treatment>  $tratamientos
     * @param  array<int, Patient>  $pacientes
     */
    private function sembrarCitasDiagnosticosYPresupuesto(
        Professional $general,
        Professional $ortodoncia,
        array $tratamientos,
        array $pacientes,
    ): void {
        try {
            [$pedro, $ana, $roberto] = $pacientes;
            $limpieza = $tratamientos['Limpieza dental'];
            $resina = $tratamientos['Resina compuesta'];
            $controlOrto = $tratamientos['Control de ortodoncia'];

            // Cita ya pasada y completada: para que la ficha del paciente
            // tenga historial, no solo turnos a futuro.
            $this->sembrarCita($general, $pedro, $resina, $this->proximoDiaSemana(1, -14)->setTime(9, 0), Appointment::ESTADO_COMPLETADA);

            // Citas a futuro, en dias que calzan con el horario de cada
            // profesional (lunes = general, martes = ortodoncia).
            $this->sembrarCita($general, $ana, $limpieza, $this->proximoDiaSemana(1, 7)->setTime(10, 0), Appointment::ESTADO_RESERVADA);
            $this->sembrarCita($ortodoncia, $roberto, $controlOrto, $this->proximoDiaSemana(2, 7)->setTime(11, 0), Appointment::ESTADO_RESERVADA);

            Diagnosis::firstOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'patient_id' => $pedro->id,
                    'descripcion' => 'Caries en pieza 26, requiere obturacion.',
                ],
                [
                    'professional_id' => $general->id,
                    'fecha' => now()->subDays(14)->toDateString(),
                    'notas' => 'Paciente refiere sensibilidad al frio.',
                ]
            );

            Diagnosis::firstOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'patient_id' => $ana->id,
                    'descripcion' => 'Sarro acumulado, indicada limpieza profunda.',
                ],
                [
                    'professional_id' => $general->id,
                    'fecha' => now()->subDays(3)->toDateString(),
                    'notas' => null,
                ]
            );

            $presupuesto = Budget::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'patient_id' => $ana->id, 'estado' => 'enviado'],
                ['total' => 0, 'notas' => 'Plan de tratamiento sugerido tras el control.']
            );

            if ($presupuesto->items()->count() === 0) {
                BudgetItem::create([
                    'tenant_id' => $this->tenant->id,
                    'budget_id' => $presupuesto->id,
                    'treatment_id' => $limpieza->id,
                    'nombre' => $limpieza->nombre,
                    'precio_unitario' => $limpieza->precio,
                    'cantidad' => 1,
                    'subtotal' => $limpieza->precio,
                ]);
                BudgetItem::create([
                    'tenant_id' => $this->tenant->id,
                    'budget_id' => $presupuesto->id,
                    'treatment_id' => $resina->id,
                    'nombre' => $resina->nombre,
                    'precio_unitario' => $resina->precio,
                    'cantidad' => 2,
                    'subtotal' => $resina->precio * 2,
                ]);
                $presupuesto->update(['total' => $presupuesto->items()->sum('subtotal')]);
            }
        } catch (\Throwable $e) {
            $this->command?->warn('No se pudo sembrar citas/diagnosticos/presupuesto de ejemplo: '.$e->getMessage());
        }
    }

    private function sembrarCita(
        Professional $professional,
        Patient $patient,
        Treatment $treatment,
        Carbon $inicio,
        string $estado,
    ): void {
        Appointment::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'professional_id' => $professional->id,
                'fecha_hora' => $inicio,
            ],
            [
                'patient_id' => $patient->id,
                'treatment_id' => $treatment->id,
                'fecha_hora_fin' => $inicio->copy()->addMinutes((int) $treatment->duracion_minutos),
                'duracion_minutos' => $treatment->duracion_minutos,
                'estado' => $estado,
                'notas' => null,
            ]
        );
    }

    /**
     * Proxima fecha (o pasada, si $offsetDias es negativo) cuyo dia de la
     * semana sea $diaSemana (Carbon: 0=domingo..6=sabado), moviendose desde
     * hoy + $offsetDias dias.
     */
    private function proximoDiaSemana(int $diaSemana, int $offsetDias): Carbon
    {
        $fecha = now()->addDays($offsetDias)->startOfDay();
        $direccion = $offsetDias < 0 ? -1 : 1;

        while ($fecha->dayOfWeek !== $diaSemana) {
            $fecha->addDays($direccion);
        }

        return $fecha;
    }
}
