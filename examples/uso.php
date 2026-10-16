<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Input\Suspension;
use Micrositios\PqrDeadline\Rules\DeadlineRules;

require dirname(__DIR__) . '/vendor/autoload.php';

$calculador = new DeadlineCalculator();
$radicacion = '2026-10-09T10:00:00-05:00';
$crearReglas = fn () => new DeadlineRules(
    plazo: 10, unidad: 'DC', plazoAmpliado: 20, permiteSuspension: true,
    inicio: 'DIA_SIGUIENTE', cierre: 'FIN_DEL_DIA', ajustarVencimientoAHabil: false,
    zonaHoraria: 'America/Bogota', horaCierre: '23:59:59.999999',
);
// La pausa abierta usa una radicación de ayer para poder ejecutar el ejemplo cualquier día.
$ayer = CarbonImmutable::now('America/Bogota')->subDay();
$ejemplos = [
    'por_tipo' => fn () => new CalculationRequest($radicacion, tipoSolicitud: RequestType::PETICION_GENERAL),
    'reglas_explicitas' => fn () => new CalculationRequest($radicacion, reglas: $crearReglas()),
    'horas' => fn () => new CalculationRequest($radicacion, tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL_MENOR),
    'ampliacion' => fn () => new CalculationRequest($radicacion, tipoSolicitud: RequestType::PETICION_GENERAL, ampliarPlazo: true),
    'suspension_cerrada' => fn () => new CalculationRequest($radicacion, tipoSolicitud: RequestType::PETICION_GENERAL,
        suspensiones: [new Suspension('2026-10-19T09:00:00-05:00', '2026-10-26T15:00:00-05:00')]),
    'suspension_abierta' => fn () => new CalculationRequest($ayer, tipoSolicitud: RequestType::PETICION_GENERAL,
        suspensiones: [new Suspension($ayer)]),
    // Para simular otra fecha se envía un cierre normal; no existe fechaReferencia.
    'escenario_con_cierre' => fn () => new CalculationRequest($radicacion, tipoSolicitud: RequestType::PETICION_GENERAL,
        suspensiones: [new Suspension('2026-10-19T09:00:00-05:00', '2026-10-22T12:00:00-05:00')]),
    'modalidades_excluyentes' => fn () => new CalculationRequest($radicacion, tipoSolicitud: RequestType::PETICION_GENERAL, reglas: $crearReglas()),
    'fecha_imposible' => fn () => new CalculationRequest('2026-02-30T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL),
];

$respuestas = [];
foreach ($ejemplos as $nombre => $crearSolicitud) {
    try {
        // Los constructores también pueden detectar un error: quedan dentro del try.
        $respuestas[$nombre] = $calculador->calcular($crearSolicitud());
    } catch (InvalidCalculationRequest $error) {
        $respuestas[$nombre] = $error;
    }
}

echo json_encode($respuestas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
