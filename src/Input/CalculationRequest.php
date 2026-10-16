<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Input;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Rules\DeadlineRules;
use Micrositios\PqrDeadline\Temporal\DateInput;

final readonly class CalculationRequest
{
    public CarbonImmutable $fechaRadicacion;
    public DeadlineRules $reglas;
    /** @var list<Suspension> */
    public array $suspensiones;

    public function __construct(
        DateTimeInterface|string $fechaRadicacion,
        public bool $ampliarPlazo = false,
        array $suspensiones = [],
        ?string $zonaHoraria = null,
        public ?RequestType $tipoSolicitud = null,
        ?DeadlineRules $reglas = null,
    ) {
        if ($tipoSolicitud !== null && $reglas !== null) {
            throw new InvalidCalculationRequest('TIPO_Y_REGLAS_MUTUAMENTE_EXCLUYENTES', 'Indique el tipo o las reglas, no ambos.', ['tipoSolicitud', 'reglas']);
        }
        if ($tipoSolicitud === null && $reglas === null) {
            throw new InvalidCalculationRequest('TIPO_O_REGLAS_REQUERIDOS', 'Indique un tipo o un conjunto completo de reglas.', ['tipoSolicitud', 'reglas']);
        }
        if ($reglas !== null && $zonaHoraria !== null && $zonaHoraria !== $reglas->zonaHoraria) {
            throw new InvalidCalculationRequest('CONFIGURACION_INCOMPATIBLE', 'La zona de la solicitud contradice las reglas explícitas.', ['zonaHoraria', 'reglas.zonaHoraria']);
        }
        $this->reglas = $reglas ?? $tipoSolicitud->reglas($zonaHoraria);
        $this->fechaRadicacion = DateInput::parse($fechaRadicacion, 'fechaRadicacion', $this->reglas->zonaHoraria);
        if (!array_is_list($suspensiones)) {
            throw new InvalidCalculationRequest('SUSPENSIONES_FORMATO_INVALIDO', 'Las suspensiones deben ser una lista.', ['suspensiones']);
        }
        $copy = [];
        foreach ($suspensiones as $index => $suspension) {
            if (!$suspension instanceof Suspension) {
                throw new InvalidCalculationRequest('SUSPENSION_INVALIDA', 'Cada elemento debe ser una suspensión.', ["suspensiones[$index]"]);
            }
            if ($suspension->inicio < $this->fechaRadicacion) {
                throw new InvalidCalculationRequest(
                    'SUSPENSION_INICIO_ANTERIOR_A_RADICACION', 'La suspensión no puede comenzar antes de la radicación.',
                    ["suspensiones[$index].inicio", 'fechaRadicacion'],
                );
            }
            $copy[] = $suspension;
        }
        $this->suspensiones = $copy;
    }
}
