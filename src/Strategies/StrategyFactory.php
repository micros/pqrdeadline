<?php

namespace Micrositios\PqrDeadline\Strategies;

use Micrositios\PqrDeadline\Contracts\DeadlineCalculator;
use Micrositios\PqrDeadline\Strategies\BusinessDaysDeadlineCalculator;
use Micrositios\PqrDeadline\Strategies\CalendarDaysDeadlineCalculator;
use Micrositios\PqrDeadline\Strategies\HoursDeadlineCalculator;
use InvalidArgumentException;

class StrategyFactory
{
    public static function make(string $strategy): DeadlineCalculator
    {
        return match ($strategy) {
            'business_days' => new BusinessDaysDeadlineCalculator(),
            'calendar_days' => new CalendarDaysDeadlineCalculator(),
            'hours' => new HoursDeadlineCalculator(),
            default => throw new InvalidArgumentException("Unknown strategy {$strategy}"),
        };
    }
}
