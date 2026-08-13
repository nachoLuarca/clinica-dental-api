<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de citas: barrido cada 15 min. Encola en la cola 'recordatorios'
// (separada de las notificaciones inmediatas). withoutOverlapping evita que dos
// pasadas se pisen si una tarda.
Schedule::command('citas:recordatorios')->everyFifteenMinutes()->withoutOverlapping();
