<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Output;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Micrositios\PqrDeadline\Temporal\DateInput;

final readonly class DeadlineResult implements JsonSerializable
{
    public CarbonImmutable $fechaVencimientoInicial;
    public CarbonImmutable $fechaMaximaRespuesta;
    public CarbonImmutable $fechaConsulta;
    /** @var list<array{codigo: string, detalle: string}> */
    public array $avisos;

    public function __construct(
        DateTimeInterface|string $fechaVencimientoInicial,
        DateTimeInterface|string $fechaMaximaRespuesta,
        DateTimeInterface|string $fechaConsulta,
        public bool $conCierrePendiente = false,
        array $avisos = [],
    ) {
        $this->fechaVencimientoInicial = DateInput::parse($fechaVencimientoInicial, 'fechaVencimientoInicial');
        $this->fechaMaximaRespuesta = DateInput::parse($fechaMaximaRespuesta, 'fechaMaximaRespuesta');
        $this->fechaConsulta = DateInput::parse($fechaConsulta, 'fechaConsulta');
        if (!array_is_list($avisos)) {
            throw new InvalidArgumentException('Los avisos deben ser una lista.');
        }
        $copy = [];
        foreach ($avisos as $aviso) {
            if (!is_array($aviso) || !is_string($aviso['codigo'] ?? null) || !is_string($aviso['detalle'] ?? null)) {
                throw new InvalidArgumentException('Cada aviso requiere código y detalle en texto.');
            }
            $copy[] = ['codigo' => $aviso['codigo'], 'detalle' => $aviso['detalle']];
        }
        $this->avisos = $copy;
    }

    public function toArray(): array
    {
        return [
            'conCierrePendiente' => $this->conCierrePendiente,
            'fechaConsulta' => $this->fechaConsulta->format('Y-m-d\TH:i:s.uP'),
            'fechaVencimientoInicial' => $this->fechaVencimientoInicial->format('Y-m-d\TH:i:s.uP'),
            'fechaMaximaRespuesta' => $this->fechaMaximaRespuesta->format('Y-m-d\TH:i:s.uP'),
            'avisos' => $this->avisos,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
