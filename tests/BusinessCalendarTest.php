<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Carbon\CarbonImmutable;
use Cmixin\BusinessDay;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Calendar\BusinessCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class BusinessCalendarTest extends TestCase
{
    public static function nonBusinessDates(): iterable
    {
        foreach ([
            '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03', '2026-05-01',
            '2026-05-18', '2026-06-08', '2026-06-15', '2026-06-29', '2026-07-20', '2026-08-07',
            '2026-08-17', '2026-10-12', '2026-11-02', '2026-11-16', '2026-12-08', '2026-12-25',
            '2026-10-10', '2026-10-11',
        ] as $date) {
            yield [$date];
        }
    }

    #[DataProvider('nonBusinessDates')]
    public function testTheDependencySuppliesHolidaysAndWeekendsAreExcluded(string $date): void
    {
        self::assertFalse((new BusinessCalendar())->isBusinessDay(new CarbonImmutable($date, 'UTC')));
    }

    public static function businessDates(): iterable
    {
        foreach (['2026-01-06', '2026-03-19', '2026-06-09', '2026-07-06', '2026-07-27', '2026-10-13', '2026-11-11', '2026-12-31'] as $date) {
            yield [$date];
        }
    }

    #[DataProvider('businessDates')]
    public function testOrdinaryDatesAreNotExcludedByHolidayTransfers(string $date): void
    {
        self::assertTrue((new BusinessCalendar())->isBusinessDay(new CarbonImmutable($date, 'UTC')));
    }

    public static function nextDates(): iterable
    {
        yield 'laborable' => ['2026-10-09', '2026-10-13'];
        yield 'sábado' => ['2026-10-10', '2026-10-13'];
        yield 'domingo' => ['2026-10-11', '2026-10-13'];
        yield 'festivo' => ['2026-10-12', '2026-10-13'];
        yield 'estrictamente posterior' => ['2026-10-13', '2026-10-14'];
        yield 'Semana Santa' => ['2026-04-01', '2026-04-06'];
        yield 'festivo que ya es lunes' => ['2026-06-26', '2026-06-30'];
        yield 'noviembre primero' => ['2026-11-01', '2026-11-03'];
        yield 'noviembre segundo' => ['2026-11-15', '2026-11-17'];
        yield 'cambio de año' => ['2026-12-31', '2027-01-04'];
        yield 'bisiesto antiguo' => ['1996-02-28', '1996-02-29'];
        yield 'bisiesto' => ['2024-02-28', '2024-02-29'];
        yield 'después del bisiesto' => ['2024-02-29', '2024-03-01'];
    }

    #[DataProvider('nextDates')]
    public function testNextBusinessDayPreservesTheInputTimeAndZone(string $date, string $expected): void
    {
        $original = new CarbonImmutable($date . 'T10:30:40.123456', 'America/Bogota');
        $next = (new BusinessCalendar())->nextBusinessDay($original);
        self::assertSame($expected . 'T10:30:40.123456-05:00', $next->format('Y-m-d\TH:i:s.uP'));
        self::assertSame($date . 'T10:30:40.123456-05:00', $original->format('Y-m-d\TH:i:s.uP'));
        self::assertNotSame($original, $next);
        self::assertSame('America/Bogota', $next->getTimezone()->getName());
    }

    public function testBusinessWeekIsAlwaysMondayThroughFriday(): void
    {
        $calendar = new BusinessCalendar();
        foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'] as $date) {
            self::assertTrue($calendar->isBusinessDay(new CarbonImmutable($date, 'UTC')), $date);
        }
        foreach (['2026-10-10', '2026-10-11'] as $date) {
            self::assertFalse($calendar->isBusinessDay(new CarbonImmutable($date, 'UTC')), $date);
        }
    }

    public function testYearsAndCalendarsCanBeInterleaved(): void
    {
        $a = new BusinessCalendar();
        $b = new BusinessCalendar();
        $yearEnd = new CarbonImmutable('2026-12-31', 'UTC');
        self::assertSame('2027-01-04', $a->nextBusinessDay($yearEnd)->format('Y-m-d'));
        self::assertSame('2027-01-04', $b->nextBusinessDay($yearEnd)->format('Y-m-d'));
        self::assertTrue($a->isBusinessDay(new CarbonImmutable('2026-12-31', 'UTC')));
        self::assertFalse($a->isBusinessDay(new CarbonImmutable('2027-01-01', 'UTC')));
        self::assertSame('2027-01-04', $a->nextBusinessDay($yearEnd)->format('Y-m-d'));
    }

    public function testTheEndOfTheRepresentableRangeProducesAControlledError(): void
    {
        try {
            (new BusinessCalendar())->nextBusinessDay(new CarbonImmutable('9999-12-31', 'UTC'));
            self::fail('Se esperaba un error de rango.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('FECHA_RESULTADO_FUERA_DE_RANGO', $error->code());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExistingCarbonConfigurationNeitherChangesNorOverridesTheCalendar(): void
    {
        BusinessDay::enable(CarbonImmutable::class, 'us-national');
        CarbonImmutable::setWeekendDays([CarbonImmutable::FRIDAY, CarbonImmutable::SATURDAY]);
        CarbonImmutable::macro('pqrConsumerMarker', static fn (): string => 'cliente');
        $calendar = new BusinessCalendar();
        self::assertTrue($calendar->isBusinessDay(new CarbonImmutable('2026-07-03', 'UTC')));
        self::assertFalse($calendar->isBusinessDay(new CarbonImmutable('2026-07-20', 'UTC')));
        self::assertSame('2026-10-13', $calendar->nextBusinessDay(new CarbonImmutable('2026-10-09', 'UTC'))->format('Y-m-d'));
        self::assertSame('us-national', CarbonImmutable::getHolidaysRegion());
        self::assertSame([CarbonImmutable::FRIDAY, CarbonImmutable::SATURDAY], CarbonImmutable::getWeekendDays());
        self::assertSame('cliente', CarbonImmutable::pqrConsumerMarker());
    }

    public function testInstancesAndServerTimezoneDoNotLeakBetweenCalculations(): void
    {
        $zone = date_default_timezone_get();
        $macro = CarbonImmutable::hasMacro('nextBusinessDay');
        try {
            $a = new BusinessCalendar();
            $b = new BusinessCalendar();
            $friday = new CarbonImmutable('2026-10-09T00:00:00Z');
            date_default_timezone_set('Pacific/Apia');
            self::assertSame('2026-10-13', $a->nextBusinessDay($friday)->format('Y-m-d'));
            self::assertSame('2026-10-13', $b->nextBusinessDay($friday)->format('Y-m-d'));
            date_default_timezone_set('America/New_York');
            self::assertSame('2026-10-13', $a->nextBusinessDay($friday)->format('Y-m-d'));
            self::assertSame('2026-10-13', (new BusinessCalendar())->nextBusinessDay($friday)->format('Y-m-d'));
            self::assertSame($macro, CarbonImmutable::hasMacro('nextBusinessDay'));
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
