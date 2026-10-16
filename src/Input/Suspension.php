<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Input;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Temporal\DateInput;

final readonly class Suspension
{
    public CarbonImmutable $inicio;
    public ?CarbonImmutable $fin;

    public function __construct(
        DateTimeInterface|string $inicio,
        DateTimeInterface|string|null $fin = null,
        ?string $zonaHoraria = null,
    ) {
        $this->inicio = DateInput::parse($inicio, 'inicio', $zonaHoraria);
        $this->fin = $fin === null ? null : DateInput::parse($fin, 'fin', $zonaHoraria);
        if ($this->fin !== null && $this->fin < $this->inicio) {
            throw new InvalidCalculationRequest(
                'SUSPENSION_FIN_ANTERIOR_A_INICIO', 'El cierre no puede ser anterior al inicio.', ['inicio', 'fin'],
            );
        }
    }
}
