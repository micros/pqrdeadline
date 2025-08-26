<?php

namespace Micrositios\PqrDeadline\Tests\Unit;

use Carbon\Carbon;
use Cmixin\BusinessDay;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Strategies\CalendarDaysDeadlineCalculator;
use Micrositios\PqrDeadline\Support\DeadlineWrapper;
use PHPUnit\Framework\TestCase;

class CalendarDaysDeadlineCalculatorTest extends TestCase
{
    private CalendarDaysDeadlineCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar BusinessDay para Colombia (necesario para algunas validaciones)
        BusinessDay::enable(Carbon::class, 'CO');

        $this->calculator = new CalendarDaysDeadlineCalculator();
    }

    public function testBasicCalendarDaysCalculation(): void
    {
        // Test básico: 5 días calendario desde un día laborable
        $createdAt = Carbon::create(2024, 1, 15, 10, 30, 0); // Lunes 15 enero 2024 a las 10:30

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Días calendario: día inicial + 1 día + 4 días más = 20 enero (5 días después del 15)
        // Con approximate_end_of_day=true debe terminar a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 20, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testCalendarDaysFromWeekend(): void
    {
        // Test desde fin de semana: incluye todos los días (sábados y domingos)
        $createdAt = Carbon::create(2024, 1, 13, 14, 0, 0); // Sábado 13 enero 2024 a las 14:00

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 5 días calendario desde sábado 13: dom 14, lun 15, mar 16, mié 17, jue 18
        $expectedDate = Carbon::create(2024, 1, 18, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertEquals(6, $createdAt->dayOfWeek, 'Fecha de creación debe ser sábado');
    }

    public function testCalendarDaysFromHoliday(): void
    {
        // Test desde un festivo: incluye todos los días (festivos también)
        $createdAt = Carbon::create(2024, 12, 25, 16, 30, 0); // Navidad 25 diciembre 2024 a las 16:30

        $params = [
            'created_at' => $createdAt,
            'base_days' => 3,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 3 días calendario desde navidad 25: 26, 27, 28 diciembre
        $expectedDate = Carbon::create(2024, 12, 28, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testDoubleTermCalculation(): void
    {
        // Test duplicando el plazo
        $createdAt = Carbon::create(2024, 1, 15, 12, 0, 0); // Lunes 15 enero 2024 a las 12:00

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => true, // Duplicar el plazo
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 10 días calendario (5 * 2) desde lunes 15: 25 enero
        $expectedDate = Carbon::create(2024, 1, 25, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testWithoutApproximateEndOfDay(): void
    {
        // Test sin aproximación al final del día
        $createdAt = Carbon::create(2024, 1, 15, 10, 30, 45); // Lunes 15 enero 2024 a las 10:30:45

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => false, // No aproximar al final del día
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Debe mantener la hora exacta de creación en la fecha de deadline
        $expectedDate = Carbon::create(2024, 1, 20, 10, 30, 45);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testCalendarDaysWithSuspension(): void
    {
        // Test con una suspensión
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 17, 8, 0, 0),  // Miércoles a las 8:00
            'end_at' => Carbon::create(2024, 1, 19, 18, 0, 0),   // Viernes a las 18:00
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline inicial: 20 enero 09:00:00 (sin endOfDay)
        // Suspensión: del 17 enero 8:00 al 19 enero 18:00 = 58 horas
        // Deadline después de suspensión: 20 enero 09:00 + 58 horas = 22 enero 19:00
        // Con approximate_end_of_day=true se aplica endOfDay() al final: 22 enero 23:59:59
        $expectedDate = Carbon::create(2024, 1, 22, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testInformeCongresistasWithWrapper(): void
    {
        // Test usando DeadlineWrapper con el tipo de informe congresistas (único tipo en días calendario)
        $createdAt = Carbon::create(2024, 1, 15, 11, 0, 0); // Lunes 15 enero 2024 a las 11:00

        $wrapper = DeadlineWrapper::setup(RequestType::INFORME_CONGRESISTAS->value, $createdAt, false);
        $deadline = $wrapper->calculateDeadline();

        // 5 días calendario desde lunes 15: sábado 20 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 20, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testInformeCongresistasWithDoubleTerm(): void
    {
        // Test duplicando el plazo para informe de congresistas
        $createdAt = Carbon::create(2024, 1, 15, 15, 30, 0); // Lunes 15 enero 2024 a las 15:30

        $wrapper = DeadlineWrapper::setup(RequestType::INFORME_CONGRESISTAS->value, $createdAt, true);
        $deadline = $wrapper->calculateDeadline();

        // 10 días calendario (5 * 2) desde lunes 15: jueves 25 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 25, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testCalendarDaysIncludeWeekendsAndHolidays(): void
    {
        // Test verificando que los días calendario incluyen fines de semana y festivos
        $createdAt = Carbon::create(2024, 12, 23, 10, 0, 0); // Lunes 23 diciembre 2024

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 5 días calendario: mar 24, mié 25 (navidad), jue 26, vie 27, sab 28
        $expectedDate = Carbon::create(2024, 12, 28, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));

        // Verificar que el 25 (navidad) está incluido en el conteo
        $christmasDay = Carbon::create(2024, 12, 25);
        $this->assertFalse($christmasDay->isBusinessDay(), 'Navidad debe ser festivo');
    }

    public function testMultipleSuspensions(): void
    {
        // Test con múltiples suspensiones
        $createdAt = Carbon::create(2024, 1, 15, 8, 0, 0); // Lunes 15 enero 2024 a las 8:00

        $suspensions = [
            [
                'start_at' => Carbon::create(2024, 1, 17, 0, 0, 0),  // Miércoles completo
                'end_at' => Carbon::create(2024, 1, 17, 23, 59, 59),
            ],
            [
                'start_at' => Carbon::create(2024, 1, 19, 0, 0, 0),  // Viernes completo
                'end_at' => Carbon::create(2024, 1, 19, 23, 59, 59),
            ]
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => $suspensions
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original: 20 enero
        // Dos suspensiones de 1 día cada una = 2 días adicionales
        // Deadline final: 22 enero
        $expectedDate = Carbon::create(2024, 1, 22, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testSuspensionAfterDeadline(): void
    {
        // Test con suspensión que ocurre después del deadline calculado (no debe aplicarse)
        $createdAt = Carbon::create(2024, 1, 15, 10, 0, 0); // Lunes 15 enero 2024 a las 10:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 25, 0, 0, 0),  // 25 enero (después del deadline)
            'end_at' => Carbon::create(2024, 1, 26, 23, 59, 59),
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5, // Deadline: 20 enero
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDate = Carbon::create(2024, 1, 20, 23, 59, 59); // No debe cambiar

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testPreciseDayCalculation(): void
    {
        // Test de precisión con diferentes horas del día
        $createdAt = Carbon::create(2024, 2, 15, 23, 45, 30); // Jueves 15 febrero 2024 a las 23:45:30

        $params = [
            'created_at' => $createdAt,
            'base_days' => 7,
            'double_term' => false,
            'approximate_end_of_day' => false, // Mantener hora exacta
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 7 días calendario: vie 16, sab 17, dom 18, lun 19, mar 20, mié 21, jue 22
        $expectedDate = Carbon::create(2024, 2, 22, 23, 45, 30);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testLeapYearCalculation(): void
    {
        // Test en año bisiesto
        $createdAt = Carbon::create(2024, 2, 26, 10, 0, 0); // 26 febrero 2024 (año bisiesto)

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 5 días calendario: 27, 28, 29 (bisiesto), 1 mar, 2 mar
        $expectedDate = Carbon::create(2024, 3, 2, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertTrue($createdAt->isLeapYear(), 'Debe ser año bisiesto');
    }

    public function testCrossMonthCalculation(): void
    {
        // Test cruzando fin de mes
        $createdAt = Carbon::create(2024, 1, 29, 14, 20, 0); // 29 enero 2024

        $params = [
            'created_at' => $createdAt,
            'base_days' => 8,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // 8 días calendario: 30, 31 ene, 1, 2, 3, 4, 5, 6 feb
        $expectedDate = Carbon::create(2024, 2, 6, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }
}
