<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Strategies;

use Carbon\Carbon;
use InvalidArgumentException;
use Micrositios\PqrDeadline\Contracts\DeadlineCalculator;

class CalendarDaysDeadlineCalculator implements DeadlineCalculator
{
    use AppliesDynamicSuspensions;

    public function calculate(array $params): Carbon
    {
        $createdAt = $params['created_at'] ?? null;
        $baseDays = $params['base_days'] ?? null;
        $doubleTerm = $params['double_term'] ?? false;
        $approximateEndOfDay = $params['approximate_end_of_day'] ?? true;
        $suspensions = $params['suspensions'] ?? [];

        if (!$createdAt instanceof Carbon) {
            throw new InvalidArgumentException('created_at must be a Carbon instance');
        }
        if (!is_int($baseDays) || $baseDays < 1) {
            throw new InvalidArgumentException('base_days must be a positive integer');
        }
        if (!is_array($suspensions)) {
            throw new InvalidArgumentException('The suspensions parameter must be an array.');
        }
        if ($doubleTerm) {
            $baseDays *= 2;
        }

        // Calcular plazo inicial: día inicial + días del término menos uno
        $deadline = $createdAt->copy()->addDay()->addDays($baseDays - 1);

        // Aplicar suspensiones dinámicamente
        $deadline = $this->applyDynamicSuspensions($deadline, $suspensions, $createdAt);

        // Ajustar al final del día si es necesario
        return $approximateEndOfDay ? $deadline->endOfDay() : $deadline;
    }
}
