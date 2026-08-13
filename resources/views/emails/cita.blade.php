@php
    $titulos = [
        'confirmacion' => 'Tu cita esta confirmada',
        'recordatorio' => 'Te recordamos tu proxima cita',
        'cancelacion' => 'Tu cita fue cancelada',
    ];
    $titulo = $titulos[$mensaje->tipo] ?? 'Notificacion de tu cita';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2>{{ $titulo }}</h2>
    <p>Hola {{ $mensaje->pacienteNombre }},</p>

    @if ($mensaje->tipo === 'cancelacion')
        <p>Tu cita ha sido cancelada. Si fue un error, puedes reservar nuevamente desde el sitio.</p>
    @elseif ($mensaje->tipo === 'recordatorio')
        <p>Este es un recordatorio de tu proxima cita en {{ $mensaje->clinicaNombre }}.</p>
    @else
        <p>Hemos registrado tu cita en {{ $mensaje->clinicaNombre }}.</p>
    @endif

    <ul>
        <li><strong>Profesional:</strong> {{ $mensaje->profesionalNombre }}</li>
        <li><strong>Tratamiento:</strong> {{ $mensaje->tratamientoNombre }}</li>
        <li><strong>Fecha y hora:</strong> {{ $mensaje->fechaHora }}</li>
    </ul>

    <p>Gracias por preferirnos,<br>{{ $mensaje->clinicaNombre }}</p>
</body>
</html>
