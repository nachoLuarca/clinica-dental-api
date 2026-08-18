<?php

namespace App\Notificaciones;

use App\Models\Appointment;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

/**
 * Value object con los datos YA SANITIZADOS que alimentan las plantillas de
 * correo y WhatsApp de una notificacion de cita.
 *
 * Es el unico contrato de datos que cruza la frontera de la cola: los canales
 * reciben este objeto y no saben nada de Eloquent ni del request. Todos los
 * campos son escalares serializables (para viajar por la cola sin arrastrar
 * modelos), y el texto se limpia de saltos/controles al construirlo, de modo
 * que ningun input crudo llegue directo a una plantilla.
 */
final class MensajeNotificacion implements Arrayable
{
    public const TIPO_CONFIRMACION = 'confirmacion';

    public const TIPO_RECORDATORIO = 'recordatorio';

    public const TIPO_CANCELACION = 'cancelacion';

    public function __construct(
        public readonly string $tipo,
        public readonly string $pacienteNombre,
        public readonly ?string $pacienteEmail,
        public readonly ?string $pacienteTelefono,
        public readonly string $profesionalNombre,
        public readonly string $tratamientoNombre,
        public readonly string $fechaHora,
        public readonly string $clinicaNombre,
        public readonly ?string $misHorasUrl,
    ) {}

    /**
     * Construye el mensaje a partir de una cita ya cargada con sus relaciones
     * (professional, patient, treatment, tenant). Aqui se centraliza la
     * sanitizacion de todo lo que va a las plantillas.
     */
    public static function desdeCita(string $tipo, Appointment $cita): self
    {
        $cita->loadMissing(['professional', 'patient', 'treatment', 'tenant']);

        $profesional = trim(($cita->professional->nombre ?? '').' '.($cita->professional->apellido ?? ''));

        return new self(
            tipo: $tipo,
            pacienteNombre: self::limpiar($cita->patient->nombre ?? ''),
            pacienteEmail: $cita->patient->email ?: null,
            pacienteTelefono: self::telefono($cita->patient->telefono ?? null),
            profesionalNombre: self::limpiar($profesional),
            tratamientoNombre: self::limpiar($cita->treatment->nombre ?? ''),
            fechaHora: Carbon::parse($cita->fecha_hora)->format('d/m/Y H:i'),
            clinicaNombre: self::limpiar($cita->tenant->nombre ?? config('app.name', 'Clinica')),
            misHorasUrl: self::misHorasUrl(),
        );
    }

    /**
     * Quita saltos de linea y caracteres de control para que ningun valor
     * inyecte contenido no deseado en la plantilla de correo/WhatsApp. El
     * escape final de HTML lo hace Blade; esto es una defensa adicional.
     */
    private static function limpiar(string $valor): string
    {
        $valor = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $valor) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
    }

    /**
     * Normaliza el telefono a solo digitos y '+' inicial (formato que espera el
     * microservicio de WhatsApp). Devuelve null si no queda nada usable.
     */
    private static function telefono(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = preg_replace('/(?!^\+)[^\d]/', '', $valor) ?? '';
        $limpio = preg_replace('/(?<!^)\+/', '', $limpio) ?? '';

        return $limpio !== '' && $limpio !== '+' ? $limpio : null;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'paciente_nombre' => $this->pacienteNombre,
            'paciente_email' => $this->pacienteEmail,
            'paciente_telefono' => $this->pacienteTelefono,
            'profesional_nombre' => $this->profesionalNombre,
            'tratamiento_nombre' => $this->tratamientoNombre,
            'fecha_hora' => $this->fechaHora,
            'clinica_nombre' => $this->clinicaNombre,
            'mis_horas_url' => $this->misHorasUrl,
        ];
    }

    private static function misHorasUrl(): ?string
    {
        $base = config('notificaciones.paciente_frontend_url');

        return $base ? rtrim((string) $base, '/').'/mis-horas' : null;
    }
}
