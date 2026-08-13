<?php

use App\Http\Controllers\AvailabilityController;
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
        Route::apiResource('professionals', ProfessionalController::class)
            ->parameters(['professionals' => 'professional']);
        Route::apiResource('patients', PatientController::class)
            ->parameters(['patients' => 'patient']);
        Route::apiResource('patients.diagnoses', DiagnosisController::class)
            ->parameters(['patients' => 'patient', 'diagnoses' => 'diagnosis']);
        Route::apiResource('treatments', TreatmentController::class)
            ->parameters(['treatments' => 'treatment']);
        Route::apiResource('budgets', BudgetController::class)
            ->parameters(['budgets' => 'budget']);

        // Disponibilidad y citas (paso 5). El staff reserva para pacientes de su
        // clinica y consulta/cancela las citas del tenant.
        Route::get('availability', [AvailabilityController::class, 'index'])
            ->middleware('throttle:availability');
        Route::apiResource('appointments', StaffAppointmentController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['appointments' => 'appointment']);
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
