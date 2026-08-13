<?php

use App\Http\Controllers\Auth\PatientAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
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

// --- STAFF (portal clinica) ---
Route::prefix('staff')->group(function () {
    Route::post('register', [StaffAuthController::class, 'register']);
    Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:staff', 'tenant'])->group(function () {
        Route::get('me', [StaffAuthController::class, 'me']);
        Route::post('logout', [StaffAuthController::class, 'logout']);
    });
});

// --- PACIENTE (sitio publico + reservas) ---
Route::prefix('paciente')->group(function () {
    Route::post('register', [PatientAuthController::class, 'register']);
    Route::post('login', [PatientAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:paciente', 'tenant'])->group(function () {
        Route::get('me', [PatientAuthController::class, 'me']);
        Route::post('logout', [PatientAuthController::class, 'logout']);
    });
});
