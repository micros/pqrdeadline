<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Carbon\CarbonImmutable;
use Cmixin\BusinessDay;
use DateTime;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class DependencyIntegrationTest extends TestCase
{
    public function testAddingHoursPreservesTheOriginalDate(): void
    {
        $original = new CarbonImmutable('2026-10-09T18:30:00.123456-05:00');
        $result = $original->addHours(72);

        self::assertInstanceOf(CarbonImmutable::class, $result);
        self::assertNotSame($original, $result);
        self::assertSame('2026-10-09T18:30:00.123456-05:00', $original->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-10-12T18:30:00.123456-05:00', $result->format('Y-m-d\TH:i:s.uP'));
    }

    public function testCopyingAMutableDateProducesAnIndependentImmutableDate(): void
    {
        $source = new DateTime('2026-10-09T10:00:00.123456-05:00');
        $copy = CarbonImmutable::instance($source);

        $source->modify('+1 day');

        self::assertInstanceOf(CarbonImmutable::class, $copy);
        self::assertSame('2026-10-09T10:00:00.123456-05:00', $copy->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-10-10', $source->format('Y-m-d'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBusinessDaysSkipWeekendsAndColombianHolidays(): void
    {
        // El proceso separado contiene las macros globales de la dependencia.
        BusinessDay::enable(CarbonImmutable::class, 'co-national');

        $friday = new CarbonImmutable('2026-10-09T10:00:00.123456-05:00');
        $holiday = new CarbonImmutable('2026-10-12T10:00:00-05:00');
        $next = $friday->nextBusinessDay();

        self::assertTrue($holiday->isHoliday());
        self::assertFalse($holiday->isBusinessDay());
        self::assertInstanceOf(CarbonImmutable::class, $next);
        self::assertNotSame($friday, $next);
        self::assertSame('2026-10-09T10:00:00.123456-05:00', $friday->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-10-13T10:00:00.123456-05:00', $next->format('Y-m-d\TH:i:s.uP'));
    }
}
