<?php

namespace App\Console\Commands;

use App\Notificaciones\NotificadorDeCitas;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Barre las citas proximas y encola su recordatorio (cola 'recordatorios',
 * separada de la de notificaciones inmediatas).
 *
 * Idempotente: solo toma citas activas sin recordatorio previo dentro de la
 * ventana [horas_antes, horas_antes + ventana], y las marca como recordadas al
 * encolar. Pensado para correr programado (ver routes/console.php). No abre
 * contexto de tenant: es un barrido global de mantenimiento, y el mensaje se
 * arma con la clinica correcta de cada cita.
 */
class EnviarRecordatoriosCitas extends Command
{
    protected $signature = 'citas:recordatorios';

    protected $description = 'Encola recordatorios de las citas proximas (cola separada de las notificaciones inmediatas).';

    public function handle(
        AppointmentRepositoryInterface $appointments,
        NotificadorDeCitas $notificador,
    ): int {
        $horasAntes = (int) config('notificaciones.recordatorio.horas_antes', 24);
        $ventana = (int) config('notificaciones.recordatorio.ventana_minutos', 60);

        $desde = Carbon::now()->addHours($horasAntes);
        $hasta = $desde->copy()->addMinutes($ventana);

        $citas = $appointments->dueForReminder($desde, $hasta, ['professional', 'patient', 'treatment', 'tenant']);

        foreach ($citas as $cita) {
            $notificador->recordatorio($cita);
            $appointments->markReminded($cita);
        }

        $this->info("Recordatorios encolados: {$citas->count()}.");

        return self::SUCCESS;
    }
}
