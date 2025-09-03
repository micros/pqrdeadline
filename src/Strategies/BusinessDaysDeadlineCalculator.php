<?php

namespace Micrositios\PqrDeadline\Strategies;

use Carbon\Carbon;
use Cmixin\BusinessDay;
use InvalidArgumentException;
use Micrositios\PqrDeadline\Contracts\DeadlineCalculator;

class BusinessDaysDeadlineCalculator implements DeadlineCalculator
{
    public function __construct(string $countryCode = 'co')
    {
        BusinessDay::enable(Carbon::class, $countryCode);
    }

    /**
     * Calcula el vencimiento de una PQR considerando suspensiones y días hábiles.
     * - El plazo de vencimiento inicia el día hábil siguiente a la radicación.
     * - Se cuentan solo días hábiles, excluyendo sábados, domingos y festivos.
     * - Las suspensiones se procesan en orden cronológico y pueden aportar días hábiles completos.
     * - Se evaluan traslapos entre suspensiones y se excluyen periodos repetidos.
     * - Las horas hábiles son de 8AM a 5PM. Si suman 8 o más, se contabiliza un día adicional.
     * - Solo se aplican suspensiones que inicien antes del deadline vigente.
     */
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

        // Día hábil siguiente a la radicación
        $start = $createdAt->copy()->nextBusinessDay();

        // Cálculo del deadline original
        $deadline = $start->copy()->addBusinessDays($baseDays - 1);

        // Aplicar suspensiones dinámicamente
        $deadline = $this->applyDynamicSuspensions($deadline, $suspensions, $createdAt);

        // Por default se aproxima a la última hora del día
        return $approximateEndOfDay ? $deadline->endOfDay() : $deadline;
    }

    /**
     * Calcula días hábiles aportados por suspensiones (8AM-5PM).
     * Solo aplica suspensiones que inicien antes del deadline vigente.
     * Suma días completos + horas convertidas (8h = 1 día).
     */
    private function applyDynamicSuspensions(Carbon $initialDeadline, array $suspensions, Carbon $createdAt): Carbon
    {
        // Filtrar y validar suspensiones
        $filtered = array_filter($suspensions, function ($s) use ($createdAt) {
            // Verificar que tenga fechas válidas
            if (
                !isset($s['start_at'], $s['end_at']) ||
                !$s['start_at'] instanceof Carbon ||
                !$s['end_at'] instanceof Carbon
            ) {
                return false;
            }

            // Verificar que end_at > start_at y que start_at >= createdAt
            return $s['end_at']->greaterThan($s['start_at']) &&
                $s['start_at']->greaterThanOrEqualTo($createdAt);
        });

        // Ordenar por fecha de inicio ascendente
        usort($filtered, function ($a, $b) {
            return $a['start_at']->getTimestamp() <=> $b['start_at']->getTimestamp();
        });

        // Procesar traslapes y suspensiones contenidas
        $finalSuspensions = [];
        foreach ($filtered as $suspension) {
            $start = $suspension['start_at'];
            $end = $suspension['end_at'];

            // Si la suspensión está contenida completamente en la anterior, ignorar
            if (!empty($finalSuspensions)) {
                $lastIndex = count($finalSuspensions) - 1;
                $last = $finalSuspensions[$lastIndex];
                if ($start->greaterThanOrEqualTo($last['start_at']) && $end->lessThanOrEqualTo($last['end_at'])) {
                    continue; // contenida
                }
                // Traslape parcial: extender la suspensión anterior hasta el final de la actual y descartar la actual
                if ($start->lessThan($last['end_at']) && $end->greaterThan($last['end_at'])) {
                    $finalSuspensions[$lastIndex]['end_at'] = $end->copy();
                    continue; // descartar la suspensión actual
                }
            }

            // Solo agregar si el intervalo es válido
            if ($end->greaterThan($start)) {
                $finalSuspensions[] = ['start_at' => $start, 'end_at' => $end];
            }
        }

        // Aplicar suspensiones dinámicamente con cálculo de días hábiles
        $currentDeadline = $initialDeadline->copy();
        foreach ($finalSuspensions as $suspension) {
            $start = $suspension['start_at'];
            $end = $suspension['end_at'];

            // Solo aplicar si la suspensión inicia antes del plazo vigente
            if ($start->lessThan($currentDeadline)) {
                // Calcular días hábiles completos estrictamente internos
                // Solo si la suspensión abarca más de un día
                $fullBusinessDays = 0;
                if (!$start->isSameDay($end)) {
                    $dayAfterStart = $start->copy()->addDay()->startOfDay();
                    $dayBeforeEnd = $end->copy()->subDay()->endOfDay();

                    // Solo calcular si hay días completos entre start y end
                    if ($dayAfterStart->lessThan($dayBeforeEnd)) {
                        $fullBusinessDays = $dayAfterStart->diffInBusinessDays($dayBeforeEnd);
                    }
                }

                // Sumar horas hábiles del día inicial y final
                $workStart = 8; // Hora inicio jornada
                $workEnd = 17;  // Hora fin jornada
                $initialHours = 0;
                $finalHours = 0;

                // Día inicial: sumar horas hábiles si corresponde
                if ($start->isBusinessDay()) {
                    $startHour = $start->hour + $start->minute / 60.0;
                    $endHourInit = $end->isSameDay($start) ? $end->hour + $end->minute / 60.0 : $workEnd;
                    $initialHours = max(0, min($workEnd, $endHourInit) - max($workStart, $startHour));
                }

                // Día final: sumar horas hábiles si corresponde
                if (!$end->isSameDay($start) && $end->isBusinessDay()) {
                    $endHour = $end->hour + $end->minute / 60.0;
                    $finalHours = max(0, min($workEnd, $endHour) - $workStart);
                }

                // Si la suma de horas hábiles inicial y final es >= 8, se contabiliza como días adicionales
                $extraDays = intdiv((int)round($initialHours + $finalHours), 8);
                $contributedBusinessDays = $fullBusinessDays + $extraDays;

                // Avanzar el deadline por los días hábiles aportados
                if ($contributedBusinessDays > 0) {
                    $currentDeadline = $currentDeadline->copy()->addBusinessDays($contributedBusinessDays);
                    $currentDeadline = $currentDeadline->endOfDay();
                }
            }
        }

        return $currentDeadline;
    }
}
