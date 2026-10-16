<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Calendar;

use Carbon\CarbonImmutable;
use Cmixin\BusinessDay;
use Cmixin\BusinessDay\Calculator\HolidayCalculator;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Throwable;

/** @internal Una instancia por cálculo; no registra macros ni cambia opciones globales. */
final class BusinessCalendar
{
    private array $holidays = [];

    public function isBusinessDay(CarbonImmutable $date): bool
    {
        if ($date->isoWeekday() > 5) {
            return false;
        }
        $year = (int) $date->format('Y');
        $this->holidays[$year] ??= $this->holidaysFor($year);

        return !isset($this->holidays[$year][$date->format('Y-m-d')]);
    }

    public function nextBusinessDay(CarbonImmutable $date): CarbonImmutable
    {
        do {
            $date = $date->addDay();
            if ($date->year > 9999) {
                throw new InvalidCalculationRequest('FECHA_RESULTADO_FUERA_DE_RANGO', 'El resultado excede las fechas representables con cuatro dígitos de año.', ['reglas.plazo']);
            }
        } while (!$this->isBusinessDay($date));

        return $date;
    }

    private function holidaysFor(int $year): array
    {
        try {
            // Se concentra aquí el acceso al calculador interno de cmixin/business-day 1.21.
            $mixin = new BusinessDay();
            ($mixin->setHolidaysRegion())('co-national');
            $definitions = ($mixin->getHolidays())();
            if ($definitions === []) {
                throw new \UnexpectedValueException('No hay definiciones de festivos para Colombia.');
            }
            $calculator = new HolidayCalculator($year, 'string', 'co-national', $definitions);
            $calculator->setOutputClass(UtcHolidayDate::class);
            $seen = [];
            $calculator->setHolidaysList($seen);
            $dates = [];
            while ($holiday = $calculator->next()) {
                if (preg_match('/\A(\d{2})\/(\d{2})(?:\/(\d{4}))?\z/', $holiday[1], $parts) !== 1) {
                    throw new \UnexpectedValueException('Formato de festivo no reconocido.');
                }
                if (!isset($parts[3]) || (int) $parts[3] === $year) {
                    $dates[sprintf('%04d-%s-%s', $year, $parts[2], $parts[1])] = true;
                }
            }

            return $dates;
        } catch (Throwable $error) {
            throw new InvalidCalculationRequest('CALENDARIO_NO_DISPONIBLE', 'No se pudieron obtener los festivos para el cálculo.', ['reglas.calendario'], ['anio' => $year, 'causa' => $error->getMessage()]);
        }
    }
}
