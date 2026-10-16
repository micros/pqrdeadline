<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Exceptions;

use InvalidArgumentException;
use JsonSerializable;

final class InvalidCalculationRequest extends InvalidArgumentException implements JsonSerializable
{
    private readonly array $campos;
    private readonly array $detalles;

    public function __construct(
        private readonly string $codigo,
        string $mensaje,
        array $campos = [],
        array $detalles = [],
    ) {
        parent::__construct($mensaje);
        $fields = [];
        foreach ($campos as $campo) {
            if (!is_string($campo)) {
                throw new InvalidArgumentException('Los campos del error deben ser cadenas.');
            }
            $fields[] = $campo;
        }
        $details = [];
        foreach ($detalles as $key => $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Los detalles del error deben ser valores escalares o null.');
            }
            $details[$key] = $value;
        }
        $this->campos = $fields;
        $this->detalles = $details;
    }

    public function code(): string
    {
        return $this->codigo;
    }

    public function details(): array
    {
        return $this->detalles;
    }

    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'mensaje' => $this->getMessage(),
            'campos' => $this->campos,
            'detalles' => $this->detalles,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
