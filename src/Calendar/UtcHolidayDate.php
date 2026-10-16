<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/** @internal Aísla las expresiones de la dependencia de la zona del servidor. */
final class UtcHolidayDate extends DateTimeImmutable
{
    public function __construct(string $datetime)
    {
        parent::__construct($datetime, new DateTimeZone('UTC'));
    }
}
