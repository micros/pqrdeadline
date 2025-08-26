<?php

namespace Micrositios\PqrDeadline\Tests\Unit;

use Carbon\Carbon;
use Cmixin\BusinessDay;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Strategies\HoursDeadlineCalculator;
use Micrositios\PqrDeadline\Support\DeadlineWrapper;
use PHPUnit\Framework\TestCase;

class HoursDeadlineCalculatorTest extends TestCase
{
    private HoursDeadlineCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar BusinessDay para Colombia (como en BusinessDaysDeadlineCalculator)
        BusinessDay::enable(Carbon::class, 'CO');

        $this->calculator = new HoursDeadlineCalculator();
    }

    public function testBasicHoursCalculation(): void
    {
        // Test básico: 24 horas desde un día laborable
        $createdAt = Carbon::create(2024, 1, 15, 10, 30, 0); // Lunes 15 enero 2024 a las 10:30

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 24,
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDeadline = $createdAt->copy()->addHours(24); // Martes 16 enero 2024 a las 10:30

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertTrue($deadline->greaterThan($createdAt));
    }

    public function testHoursCalculationFromHoliday(): void
    {
        // Test desde un festivo: no debe cambiar el cálculo
        $createdAt = Carbon::create(2024, 12, 25, 14, 0, 0); // Navidad 25 diciembre 2024 a las 14:00

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 48,
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDeadline = $createdAt->copy()->addHours(48); // 27 diciembre 2024 a las 14:00

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($createdAt->isBusinessDay(), 'Fecha de creación debe ser festivo');
    }

    public function testHoursCalculationFromWeekend(): void
    {
        // Test desde fin de semana: no debe cambiar el cálculo
        $createdAt = Carbon::create(2024, 1, 13, 16, 45, 0); // Sábado 13 enero 2024 a las 16:45

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 72,
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDeadline = $createdAt->copy()->addHours(72); // Martes 16 enero 2024 a las 16:45

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertEquals(6, $createdAt->dayOfWeek, 'Fecha de creación debe ser sábado');
    }

    public function testDoubleTermCalculation(): void
    {
        // Test duplicando el plazo
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 24,
            'double_term' => true, // Duplicar el plazo
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDeadline = $createdAt->copy()->addHours(48); // 48 horas (24 * 2)

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testApproximateEndOfDay(): void
    {
        // Test con aproximación al final del día
        $createdAt = Carbon::create(2024, 1, 15, 10, 30, 0); // Lunes 15 enero 2024 a las 10:30

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 24,
            'double_term' => false,
            'approximate_end_of_day' => true, // Aproximar al final del día
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Debería terminar a las 23:59:59 del día calculado
        $this->assertEquals('23:59:59', $deadline->format('H:i:s'));
        $this->assertEquals('2024-01-16', $deadline->format('Y-m-d'));
    }

    public function testHoursCalculationWithSuspension(): void
    {
        // Test con una suspensión
        $createdAt = Carbon::create(2024, 1, 15, 10, 0, 0); // Lunes 15 enero 2024 a las 10:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 15, 15, 0, 0), // Mismo día a las 15:00
            'end_at' => Carbon::create(2024, 1, 16, 9, 0, 0),    // Martes a las 9:00 (18 horas de suspensión)
        ];

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 24,
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // El deadline original sería: 15 enero 10:00 + 24h = 16 enero 10:00
        // La suspensión (15 enero 15:00 - 16 enero 9:00) añade 18 horas
        // Deadline final: 16 enero 10:00 + 18h = 17 enero 4:00
        $expectedDeadline = Carbon::create(2024, 1, 17, 4, 0, 0);

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testHealthRiskTypesWithWrapper(): void
    {
        // Test usando DeadlineWrapper con tipos de salud específicos
        $createdAt = Carbon::create(2024, 1, 15, 14, 30, 0); // Lunes 15 enero 2024 a las 14:30

        // Riesgo vital (24 horas)
        $vitalWrapper = DeadlineWrapper::setup(RequestType::SALUD_RIESGO_VITAL->value, $createdAt, false);
        $vitalDeadline = $vitalWrapper->calculateDeadline();
        $expectedVital = $createdAt->copy()->addHours(24);
        $this->assertEquals($expectedVital->format('Y-m-d H:i:s'), $vitalDeadline->format('Y-m-d H:i:s'));

        // Riesgo priorizado (48 horas)
        $prioritizedWrapper = DeadlineWrapper::setup(RequestType::SALUD_RIESGO_PRIORIZADO->value, $createdAt, false);
        $prioritizedDeadline = $prioritizedWrapper->calculateDeadline();
        $expectedPrioritized = $createdAt->copy()->addHours(48);
        $this->assertEquals($expectedPrioritized->format('Y-m-d H:i:s'), $prioritizedDeadline->format('Y-m-d H:i:s'));

        // Riesgo simple (72 horas)
        $simpleWrapper = DeadlineWrapper::setup(RequestType::SALUD_RIESGO_SIMPLE->value, $createdAt, false);
        $simpleDeadline = $simpleWrapper->calculateDeadline();
        $expectedSimple = $createdAt->copy()->addHours(72);
        $this->assertEquals($expectedSimple->format('Y-m-d H:i:s'), $simpleDeadline->format('Y-m-d H:i:s'));
    }

    public function testHealthRiskTypesWithDoubleTerm(): void
    {
        // Test duplicando el plazo para tipos de salud
        $createdAt = Carbon::create(2024, 1, 15, 10, 0, 0); // Lunes 15 enero 2024 a las 10:00

        // Riesgo vital duplicado (24 * 2 = 48 horas)
        $vitalWrapper = DeadlineWrapper::setup(RequestType::SALUD_RIESGO_VITAL->value, $createdAt, true);
        $vitalDeadline = $vitalWrapper->calculateDeadline();
        $expectedVital = $createdAt->copy()->addHours(48);
        $this->assertEquals($expectedVital->format('Y-m-d H:i:s'), $vitalDeadline->format('Y-m-d H:i:s'));

        // Riesgo priorizado duplicado (48 * 2 = 96 horas)
        $prioritizedWrapper = DeadlineWrapper::setup(RequestType::SALUD_RIESGO_PRIORIZADO->value, $createdAt, true);
        $prioritizedDeadline = $prioritizedWrapper->calculateDeadline();
        $expectedPrioritized = $createdAt->copy()->addHours(96);
        $this->assertEquals($expectedPrioritized->format('Y-m-d H:i:s'), $prioritizedDeadline->format('Y-m-d H:i:s'));
    }

    public function testSuspensionDuringCalculatedPeriod(): void
    {
        // Test con suspensión que ocurre durante el período calculado
        $createdAt = Carbon::create(2024, 1, 15, 8, 0, 0); // Lunes 15 enero 2024 a las 8:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 15, 20, 0, 0), // Mismo día a las 20:00
            'end_at' => Carbon::create(2024, 1, 16, 14, 0, 0),   // Martes a las 14:00 (18 horas)
        ];

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 48, // 48 horas
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original: 15 enero 8:00 + 48h = 17 enero 8:00
        // Suspensión comienza antes del deadline (15 enero 20:00), por lo que se aplica
        // Añade 18 horas: 17 enero 8:00 + 18h = 18 enero 2:00
        $expectedDeadline = Carbon::create(2024, 1, 18, 2, 0, 0);

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testSuspensionAfterDeadline(): void
    {
        // Test con suspensión que ocurre después del deadline calculado (no debe aplicarse)
        $createdAt = Carbon::create(2024, 1, 15, 10, 0, 0); // Lunes 15 enero 2024 a las 10:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 17, 15, 0, 0), // Miércoles a las 15:00
            'end_at' => Carbon::create(2024, 1, 18, 10, 0, 0),   // Jueves a las 10:00
        ];

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 24, // 24 horas -> deadline: 16 enero 10:00
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDeadline = $createdAt->copy()->addHours(24); // No debe cambiar

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testPreciseHourCalculation(): void
    {
        // Test de precisión con minutos y segundos
        $createdAt = Carbon::create(2024, 1, 15, 15, 45, 30); // Lunes 15 enero 2024 a las 15:45:30

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 25, // 25 horas exactas
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDeadline = Carbon::create(2024, 1, 16, 16, 45, 30); // Martes a las 16:45:30

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testMultipleSuspensions(): void
    {
        // Test con múltiples suspensiones
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $suspensions = [
            [
                'start_at' => Carbon::create(2024, 1, 15, 12, 0, 0), // Día 1 a las 12:00
                'end_at' => Carbon::create(2024, 1, 15, 16, 0, 0),   // Día 1 a las 16:00 (4 horas)
            ],
            [
                'start_at' => Carbon::create(2024, 1, 16, 8, 0, 0),  // Día 2 a las 8:00
                'end_at' => Carbon::create(2024, 1, 16, 12, 0, 0),   // Día 2 a las 12:00 (4 horas)
            ]
        ];

        $params = [
            'created_at' => $createdAt,
            'base_hours' => 72, // 72 horas
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => $suspensions
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original: 15 enero 9:00 + 72h = 18 enero 9:00
        // Primera suspensión (4h): 18 enero 9:00 + 4h = 18 enero 13:00
        // Segunda suspensión (4h): 18 enero 13:00 + 4h = 18 enero 17:00
        $expectedDeadline = Carbon::create(2024, 1, 18, 17, 0, 0);

        $this->assertEquals($expectedDeadline->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }
}
