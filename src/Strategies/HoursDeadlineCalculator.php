<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Strategies;

use Carbon\Carbon;
use InvalidArgumentException;
use Micrositios\PqrDeadline\Contracts\DeadlineCalculator;

class HoursDeadlineCalculator implements DeadlineCalculator
{
    use AppliesDynamicSuspensions;

    public function calculate(array $params): Carbon
    {
        $createdAt = $params['created_at'] ?? null;
        $baseHours = $params['base_hours'] ?? null;
        $doubleTerm = $params['double_term'] ?? false;
        $approximateEndOfDay = $params['approximate_end_of_day'] ?? false;
        $suspensions = $params['suspensions'] ?? [];

        if (!$createdAt instanceof Carbon) {
            throw new InvalidArgumentException('created_at must be a Carbon instance');
        }
        if (!is_int($baseHours) || $baseHours < 1) {
            throw new InvalidArgumentException('base_hours must be a positive integer');
        }
        if (!is_array($suspensions)) {
            throw new InvalidArgumentException('The suspensions parameter must be an array.');
        }
        if ($doubleTerm) {
            $baseHours *= 2;
        }

        // Calcular plazo inicial: fecha de creación + horas base
        $deadline = $createdAt->copy()->addHours($baseHours);

        // Aplicar suspensiones dinámicamente
        $deadline = $this->applyDynamicSuspensions($deadline, $suspensions, $createdAt);

        // Ajustar al final del día si es necesario (por defecto NO para horas)
        return $approximateEndOfDay ? $deadline->endOfDay() : $deadline;
    }
}
