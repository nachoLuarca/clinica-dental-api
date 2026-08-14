<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Paciente\AppointmentController as PatientAppointmentController;
use App\Http\Controllers\Publico\CatalogController;
use App\Http\Controllers\Staff\AppointmentController as StaffAppointmentController;
use App\Http\Controllers\Staff\BudgetController;
use App\Http\Controllers\Staff\DiagnosisController;
use App\Http\Controllers\Staff\PatientController;
use App\Http\Controllers\Staff\ProfessionalController;
use App\Http\Controllers\Staff\TreatmentController;
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
    Route::get('availability', [AvailabilityController::class, 'index']);
});

// --- STAFF (portal clinica) ---
Route::prefix('staff')->group(function () {
    Route::post('register', [StaffAuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:staff', 'abilities:staff', 'tenant'])->group(function () {
        Route::get('me', [StaffAuthController::class, 'me']);
        Route::post('logout', [StaffAuthController::class, 'logout']);

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
        Route::apiResource('budgets', BudgetController::class)
            ->parameters(['budgets' => 'budget'])
            ->middlewareFor(['index', 'show'], 'permission:budgets.ver,staff')
            ->middlewareFor('store', 'permission:budgets.crear,staff')
            ->middlewareFor('update', 'permission:budgets.editar,staff')
            ->middlewareFor('destroy', 'permission:budgets.eliminar,staff');

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
