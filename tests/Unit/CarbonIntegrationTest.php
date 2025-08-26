<?php

namespace Micrositios\PqrDeadline\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CarbonIntegrationTest extends TestCase
{
    public function testCarbonBasicFunctionality(): void
    {
        $date = Carbon::parse('2024-01-15 10:00:00');

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals('2024-01-15', $date->toDateString());
        $this->assertEquals('10:00:00', $date->toTimeString());
    }

    public function testCarbonBusinessDaysExtension(): void
    {
        // Verificar que la extensión BusinessDay se puede cargar
        $this->assertTrue(class_exists('Cmixin\BusinessDay'));

        // Crear una fecha y verificar funcionalidades básicas
        $monday = Carbon::parse('2024-01-15'); // Lunes
        $this->assertEquals(1, $monday->dayOfWeek); // Lunes = 1

        $tuesday = $monday->copy()->addDay();
        $this->assertEquals(2, $tuesday->dayOfWeek); // Martes = 2
    }

    public function testDateManipulation(): void
    {
        $start = Carbon::parse('2024-01-15 10:00:00');
        $end = $start->copy()->addDays(5);

        $this->assertTrue($end->greaterThan($start));
        $this->assertEquals(5, $start->diffInDays($end));
        $this->assertEquals('2024-01-20', $end->toDateString());
    }

    public function testTimeComparisons(): void
    {
        $date1 = Carbon::parse('2024-01-15 10:00:00');
        $date2 = Carbon::parse('2024-01-15 15:00:00');
        $date3 = Carbon::parse('2024-01-16 10:00:00');

        $this->assertTrue($date2->greaterThan($date1));
        $this->assertTrue($date3->greaterThan($date2));
        $this->assertFalse($date1->greaterThan($date2));
        $this->assertTrue($date1->lessThan($date2));
    }
}
