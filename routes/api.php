<?php

use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Paciente\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Publico\CatalogController;
use App\Http\Controllers\Publico\EspecialidadController as PublicoEspecialidadController;
use App\Http\Controllers\Publico\PatientAppointmentController as PublicoPatientAppointmentController;
use App\Http\Controllers\Publico\PatientRegistroController;
use App\Http\Controllers\Publico\ProfessionalController as PublicoProfessionalController;
use App\Http\Controllers\Publico\TenantController as PublicoTenantController;
use App\Http\Controllers\Staff\AppointmentController as StaffAppointmentController;
use App\Http\Controllers\Staff\BudgetController;
use App\Http\Controllers\Staff\DiagnosisController;
use App\Http\Controllers\Staff\EspecialidadController;
use App\Http\Controllers\Staff\PatientController;
use App\Http\Controllers\Staff\PermissionController;
use App\Http\Controllers\Staff\ProfessionalController;
use App\Http\Controllers\Staff\RoleController;
use App\Http\Controllers\Staff\TenantController;
use App\Http\Controllers\Staff\TreatmentController;
use App\Http\Controllers\Staff\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| Dos bloques de auth totalmente independientes: staff y paciente. Cada uno
| protege sus rutas privadas con su propio guard Sanctum ('auth:staff' /
| 'auth:paciente'). Un token de un guard NUNCA pasa el 'auth' del otro porque
| Sanctum valida que el modelo del token coincida con el provider del guard.
|
| El middleware 'tenant' corre DESPUES del 'auth' para resolver el tenant a
| partir del usuario ya autenticado (nunca de input del cliente).
|
*/

// --- DOCUMENTACION (OpenAPI / Swagger UI) ---
// Publica y sin tenant: es el contrato de referencia para ambos frontends.
// /api/documentation carga la UI; /api/openapi.yaml expone el contrato crudo.
Route::get('documentation', [DocsController::class, 'ui']);
Route::get('openapi.yaml', [DocsController::class, 'spec']);

// --- PUBLICO (sitio de pacientes, sin login) ---
// El tenant se resuelve por el slug de clinica del header 'X-Clinica'
// (middleware 'tenant.publico'). Rate limiting por tenant + IP ('throttle:publico').
Route::prefix('publico')->middleware(['tenant.publico', 'throttle:publico'])->group(function () {
    Route::get('tratamientos', [CatalogController::class, 'index']);
    Route::get('especialidades', [PublicoEspecialidadController::class, 'index']);
    Route::get('profesionales', [PublicoProfessionalController::class, 'index']);
    Route::get('availability', [AvailabilityController::class, 'index']);

    // Marca de la clinica (nombre/logo/color) para el sitio publico.
    Route::get('tenant', [PublicoTenantController::class, 'show']);

    // Gestion de citas SIN login: el paciente se identifica con RUT + fecha
    // de nacimiento (ver PatientLookupService) en vez de un token Sanctum.
    // store() (paso Confirmar del wizard) se identifica distinto: RUT +
    // Turnstile, igual que el paso de Identificacion (ver controller).
    Route::get('citas', [PublicoPatientAppointmentController::class, 'index']);
    Route::post('citas', [PublicoPatientAppointmentController::class, 'store']);
    Route::delete('citas/{appointment}', [PublicoPatientAppointmentController::class, 'destroy']);

    // Paso de Identificacion por RUT del flujo de reserva (sin login, sin
    // password): confirma si el RUT ya es paciente (protegido con Turnstile,
    // ver PatientRegistroService) y da de alta uno nuevo si no lo es.
    Route::post('pacientes/verificar-rut', [PatientRegistroController::class, 'verificarRut']);
    Route::post('pacientes', [PatientRegistroController::class, 'store']);
});

// --- STAFF (portal clinica) ---
Route::prefix('staff')->group(function () {
    Route::post('register', [StaffAuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:staff', 'abilities:staff', 'tenant'])->group(function () {
        Route::get('me', [StaffAuthController::class, 'me']);
        Route::post('logout', [StaffAuthController::class, 'logout']);

        // Datos de marca de la propia clinica (nombre/logo/color). Sin
        // {tenant} en la ruta: siempre es la del usuario autenticado. 'ver'
        // lo tienen los 3 roles (la UI necesita mostrar nombre/logo siempre);
        // 'editar' es solo de 'admin'. Subida de logo: el front debe mandar
        // POST multipart con '_method=PATCH' (Laravel no parsea PATCH
        // multipart nativo).
        Route::get('tenant', [TenantController::class, 'show'])
            ->middleware('permission:tenant.ver,staff');
        Route::patch('tenant', [TenantController::class, 'update'])
            ->middleware('permission:tenant.editar,staff');

        // CRUDs base (paso 4). Todos tenant-scoped por el middleware 'tenant'.
        // Cada accion exige el permiso correspondiente (paso 9, roles/permisos
        // por tenant via Spatie "teams" = tenant_id); ver README seccion Roles.
        Route::apiResource('professionals', ProfessionalController::class)
            ->parameters(['professionals' => 'professional'])
            ->middlewareFor(['index', 'show'], 'permission:professionals.ver,staff')
            ->middlewareFor('store', 'permission:professionals.crear,staff')
            ->middlewareFor('update', 'permission:professionals.editar,staff')
            ->middlewareFor('destroy', 'permission:professionals.eliminar,staff');
        Route::apiResource('patients', PatientController::class)
            ->parameters(['patients' => 'patient'])
            ->middlewareFor(['index', 'show'], 'permission:patients.ver,staff')
            ->middlewareFor('store', 'permission:patients.crear,staff')
            ->middlewareFor('update', 'permission:patients.editar,staff')
            ->middlewareFor('destroy', 'permission:patients.eliminar,staff');
        Route::apiResource('patients.diagnoses', DiagnosisController::class)
            ->parameters(['patients' => 'patient', 'diagnoses' => 'diagnosis'])
            ->middlewareFor(['index', 'show'], 'permission:diagnoses.ver,staff')
            ->middlewareFor('store', 'permission:diagnoses.crear,staff')
            ->middlewareFor('update', 'permission:diagnoses.editar,staff')
            ->middlewareFor('destroy', 'permission:diagnoses.eliminar,staff');
        Route::apiResource('treatments', TreatmentController::class)
            ->parameters(['treatments' => 'treatment'])
            ->middlewareFor(['index', 'show'], 'permission:treatments.ver,staff')
            ->middlewareFor('store', 'permission:treatments.crear,staff')
            ->middlewareFor('update', 'permission:treatments.editar,staff')
            ->middlewareFor('destroy', 'permission:treatments.eliminar,staff');
        // Catalogo de especialidades (paso 11): asignables a profesionales,
        // mapeadas a categorias de tratamiento para el filtro de reserva.
        Route::apiResource('especialidades', EspecialidadController::class)
            ->parameters(['especialidades' => 'especialidad'])
            ->middlewareFor(['index', 'show'], 'permission:especialidades.ver,staff')
            ->middlewareFor('store', 'permission:especialidades.crear,staff')
            ->middlewareFor('update', 'permission:especialidades.editar,staff')
            ->middlewareFor('destroy', 'permission:especialidades.eliminar,staff');
        Route::apiResource('budgets', BudgetController::class)
            ->parameters(['budgets' => 'budget'])
            ->middlewareFor(['index', 'show'], 'permission:budgets.ver,staff')
            ->middlewareFor('store', 'permission:budgets.crear,staff')
            ->middlewareFor('update', 'permission:budgets.editar,staff')
            ->middlewareFor('destroy', 'permission:budgets.eliminar,staff');

        // Gestion de usuarios/roles/permisos de la propia clinica (paso 10).
        // Solo 'admin' llega aca (unico rol con usuarios.*/roles.* en la
        // matriz base); el rol 'admin' mismo esta protegido en el servicio
        // (RoleProvisioner::ROL_PROTEGIDO): no se puede renombrar, editarle
        // los permisos ni borrarlo.
        Route::apiResource('users', UserController::class)
            ->parameters(['users' => 'user'])
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:usuarios.ver,staff')
            ->middlewareFor('store', 'permission:usuarios.crear,staff')
            ->middlewareFor('update', 'permission:usuarios.editar,staff');
        Route::patch('users/{user}/rol', [UserController::class, 'actualizarRol'])
            ->middleware('permission:usuarios.editar,staff');
        Route::patch('users/{user}/estado', [UserController::class, 'actualizarEstado'])
            ->middleware('permission:usuarios.editar,staff');
        Route::patch('users/{user}/password', [UserController::class, 'resetearPassword'])
            ->middleware('permission:usuarios.editar,staff');

        Route::apiResource('roles', RoleController::class)
            ->parameters(['roles' => 'role'])
            ->middlewareFor(['index', 'show'], 'permission:roles.ver,staff')
            ->middlewareFor('store', 'permission:roles.crear,staff')
            ->middlewareFor('update', 'permission:roles.editar,staff')
            ->middlewareFor('destroy', 'permission:roles.eliminar,staff');
        Route::patch('roles/{role}/permisos', [RoleController::class, 'actualizarPermisos'])
            ->middleware('permission:roles.editar,staff');

        Route::get('permisos', [PermissionController::class, 'index'])
            ->middleware('permission:roles.ver,staff');

        // Disponibilidad y citas (paso 5). El staff reserva para pacientes de su
        // clinica y consulta/cancela las citas del tenant.
        Route::get('availability', [AvailabilityController::class, 'index'])
            ->middleware('throttle:availability');
        Route::apiResource('appointments', StaffAppointmentController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['appointments' => 'appointment'])
            ->middlewareFor(['index', 'show'], 'permission:appointments.ver,staff')
            ->middlewareFor('store', 'permission:appointments.crear,staff')
            ->middlewareFor('destroy', 'permission:appointments.eliminar,staff');
    });
});

// --- PACIENTE (sitio publico + reservas) ---
Route::prefix('paciente')->group(function () {
    Route::post('register', [PatientAuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [PatientAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:paciente', 'abilities:paciente', 'tenant'])->group(function () {
        Route::get('me', [PatientAuthController::class, 'me']);
        Route::post('logout', [PatientAuthController::class, 'logout']);

        // Disponibilidad y reservas del propio paciente (paso 5). El paciente
        // solo lista/cancela SUS citas; el patient_id sale del token, no del body.
        Route::get('availability', [AvailabilityController::class, 'index'])
            ->middleware('throttle:availability');
        Route::apiResource('appointments', PatientAppointmentController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['appointments' => 'appointment']);
    });
});
