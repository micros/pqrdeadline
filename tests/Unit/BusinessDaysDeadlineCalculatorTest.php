<?php

namespace Micrositios\PqrDeadline\Tests\Unit;

use Carbon\Carbon;
use Cmixin\BusinessDay;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Strategies\BusinessDaysDeadlineCalculator;
use Micrositios\PqrDeadline\DeadlineWrapper;
use PHPUnit\Framework\TestCase;

class BusinessDaysDeadlineCalculatorTest extends TestCase
{
    private BusinessDaysDeadlineCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // El constructor de BusinessDaysDeadlineCalculator ya configura BusinessDay
        $this->calculator = new BusinessDaysDeadlineCalculator('CO');
    }

    public function testBasicBusinessDaysCalculation(): void
    {
        // Test básico: 5 días hábiles desde un viernes
        $createdAt = Carbon::create(2024, 1, 12, 10, 30, 0); // Viernes 12 enero 2024 a las 10:30

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Día hábil siguiente: lunes 15 enero
        // 5 días hábiles: lun 15, mar 16, mié 17, jue 18, vie 19
        // Deadline: viernes 19 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 19, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertEquals(5, $createdAt->dayOfWeek, 'Fecha de creación debe ser viernes');
    }

    public function testBusinessDaysFromMonday(): void
    {
        // Test desde un día laborable (lunes)
        $createdAt = Carbon::create(2024, 1, 15, 14, 0, 0); // Lunes 15 enero 2024 a las 14:00

        $params = [
            'created_at' => $createdAt,
            'base_days' => 3,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Día hábil siguiente: martes 16 enero
        // 3 días hábiles: mar 16, mié 17, jue 18
        // Deadline: jueves 18 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 18, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertTrue($createdAt->isBusinessDay(), 'Fecha de creación debe ser día laborable');
    }

    public function testBusinessDaysFromHoliday(): void
    {
        // Test desde un festivo: Christmas 2024
        $createdAt = Carbon::create(2024, 12, 25, 16, 30, 0); // Navidad 25 diciembre 2024 a las 16:30

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Primer día hábil después de navidad: 26 diciembre (jueves)
        // 5 días hábiles: jue 26, vie 27, lun 30, mar 31, jue 2 enero (1 enero es festivo)
        // Deadline: jueves 2 enero 2025 a las 23:59:59
        $expectedDate = Carbon::create(2025, 1, 2, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertFalse($createdAt->isBusinessDay(), 'Navidad debe ser festivo');
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

        // 10 días hábiles (5 * 2) desde martes 16 enero
        // Deadline: lunes 29 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 29, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testWithoutApproximateEndOfDay(): void
    {
        // Test sin aproximación al final del día
        $createdAt = Carbon::create(2024, 1, 15, 10, 30, 45); // Lunes 15 enero 2024 a las 10:30:45

        $params = [
            'created_at' => $createdAt,
            'base_days' => 3,
            'double_term' => false,
            'approximate_end_of_day' => false, // No aproximar al final del día
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Debe mantener la hora del nextBusinessDay
        $nextBusinessDay = $createdAt->copy()->nextBusinessDay(); // Martes 16 enero
        $expectedDate = $nextBusinessDay->copy()->addBusinessDays(2); // +2 días hábiles

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
        $this->assertNotEquals('23:59:59', $deadline->format('H:i:s'));
    }

    public function testBusinessDaysWithSimpleSuspension(): void
    {
        // Test con una suspensión simple que aporta días hábiles completos
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 17, 0, 0, 0),  // Miércoles 00:00
            'end_at' => Carbon::create(2024, 1, 19, 23, 59, 59), // Viernes 23:59:59 (3 días completos)
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original: lunes 22 enero (5 días hábiles desde martes 16)
        // Suspensión aporta 3 días hábiles (mié 17, jue 18, vie 19)
        // Deadline final: jueves 25 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 25, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testBusinessDaysWithPartialSuspension(): void
    {
        // Test con una suspensión que incluye horas parciales
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 17, 10, 0, 0), // Miércoles 10:00
            'end_at' => Carbon::create(2024, 1, 18, 14, 0, 0),   // Jueves 14:00
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original: lunes 22 enero (5 días hábiles desde martes 16)
        // Suspensión: miércoles 10:00-17:00 (7h) + jueves 08:00-14:00 (6h) = 13h ≥ 8h = +1 día adicional
        // Deadline final: martes 23 enero a las 23:59:59
        $expectedDate = Carbon::create(2024, 1, 23, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testRequestTypesWithWrapper(): void
    {
        // Test usando DeadlineWrapper con diferentes tipos que usan días hábiles
        $createdAt = Carbon::create(2024, 1, 15, 11, 0, 0); // Lunes 15 enero 2024 a las 11:00

        // Petición general (15 días hábiles)
        $peticionWrapper = DeadlineWrapper::setup(RequestType::PETICION_GENERAL->value, $createdAt, false);
        $peticionDeadline = $peticionWrapper->calculateDeadline();

        // 15 días hábiles desde martes 16 enero = lunes 5 febrero
        $expectedPeticion = Carbon::create(2024, 2, 5, 23, 59, 59);
        $this->assertEquals($expectedPeticion->format('Y-m-d H:i:s'), $peticionDeadline->format('Y-m-d H:i:s'));

        // Información pública (10 días hábiles)
        $informacionWrapper = DeadlineWrapper::setup(RequestType::INFORMACION_PUBLICA->value, $createdAt, false);
        $informacionDeadline = $informacionWrapper->calculateDeadline();

        // 10 días hábiles desde martes 16 enero = lunes 29 enero
        $expectedInformacion = Carbon::create(2024, 1, 29, 23, 59, 59);
        $this->assertEquals($expectedInformacion->format('Y-m-d H:i:s'), $informacionDeadline->format('Y-m-d H:i:s'));

        // Copia historia clínica (3 días hábiles)
        $historiaWrapper = DeadlineWrapper::setup(RequestType::COPIA_HISTORIA_CLINICA->value, $createdAt, false);
        $historiaDeadline = $historiaWrapper->calculateDeadline();

        // 3 días hábiles desde martes 16 enero = jueves 18 enero
        $expectedHistoria = Carbon::create(2024, 1, 18, 23, 59, 59);
        $this->assertEquals($expectedHistoria->format('Y-m-d H:i:s'), $historiaDeadline->format('Y-m-d H:i:s'));
    }

    public function testRequestTypesWithDoubleTerm(): void
    {
        // Test duplicando el plazo para tipos de días hábiles
        $createdAt = Carbon::create(2024, 1, 15, 10, 0, 0); // Lunes 15 enero 2024 a las 10:00

        // Reclamo duplicado (15 * 2 = 30 días hábiles)
        $reclamoWrapper = DeadlineWrapper::setup(RequestType::RECLAMO->value, $createdAt, true);
        $reclamoDeadline = $reclamoWrapper->calculateDeadline();

        // 30 días hábiles desde martes 16 enero = lunes 26 febrero
        $expectedReclamo = Carbon::create(2024, 2, 26, 23, 59, 59);
        $this->assertEquals($expectedReclamo->format('Y-m-d H:i:s'), $reclamoDeadline->format('Y-m-d H:i:s'));

        // Consulta duplicada (30 * 2 = 60 días hábiles)
        $consultaWrapper = DeadlineWrapper::setup(RequestType::CONSULTA->value, $createdAt, true);
        $consultaDeadline = $consultaWrapper->calculateDeadline();

        // 60 días hábiles desde martes 16 enero = jueves 11 abril
        $expectedConsulta = Carbon::create(2024, 4, 11, 23, 59, 59);
        $this->assertEquals($expectedConsulta->format('Y-m-d H:i:s'), $consultaDeadline->format('Y-m-d H:i:s'));
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
            'base_days' => 5, // Deadline: 22 enero
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);
        $expectedDate = Carbon::create(2024, 1, 22, 23, 59, 59); // No debe cambiar

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testMultipleSuspensions(): void
    {
        // Test con múltiples suspensiones
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

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

        // Deadline original: lunes 22 enero (5 días hábiles)
        // Primera suspensión (miércoles completo): 9h hábiles = +1 día hábil
        // Segunda suspensión (viernes completo): 9h hábiles = +1 día hábil
        // Deadline final: miércoles 24 enero (22 + 2 días hábiles)
        $expectedDate = Carbon::create(2024, 1, 24, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testSuspensionOverlap(): void
    {
        // Test con suspensiones que se traslapan (debe manejar correctamente el traslape)
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $suspensions = [
            [
                'start_at' => Carbon::create(2024, 1, 17, 8, 0, 0),  // Miércoles 8:00
                'end_at' => Carbon::create(2024, 1, 18, 15, 0, 0),   // Jueves 15:00
            ],
            [
                'start_at' => Carbon::create(2024, 1, 18, 10, 0, 0), // Jueves 10:00 (traslape)
                'end_at' => Carbon::create(2024, 1, 19, 16, 0, 0),   // Viernes 16:00
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

        // Las suspensiones se deben unificar: miércoles 8:00 - viernes 16:00
        // Esto aporta 3 días hábiles completos (mié, jue, vie)
        // Deadline original + 3 días = jueves 25 enero
        $expectedDate = Carbon::create(2024, 1, 25, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testWeekendSuspension(): void
    {
        // Test con suspensión durante fin de semana (no debe aportar días hábiles)
        $createdAt = Carbon::create(2024, 1, 15, 9, 0, 0); // Lunes 15 enero 2024 a las 9:00

        $suspension = [
            'start_at' => Carbon::create(2024, 1, 20, 8, 0, 0),  // Sábado 8:00
            'end_at' => Carbon::create(2024, 1, 21, 20, 0, 0),   // Domingo 20:00
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original: lunes 22 enero (5 días hábiles)
        // Suspensión en fin de semana no aporta días hábiles
        // Deadline final: no debe cambiar
        $expectedDate = Carbon::create(2024, 1, 22, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testHolidaySuspension(): void
    {
        // Test con suspensión que incluye un festivo
        $createdAt = Carbon::create(2024, 12, 23, 9, 0, 0); // Lunes 23 diciembre 2024 a las 9:00

        $suspension = [
            'start_at' => Carbon::create(2024, 12, 24, 8, 0, 0),  // Martes 24 diciembre
            'end_at' => Carbon::create(2024, 12, 26, 18, 0, 0),   // Jueves 26 diciembre
        ];

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5,
            'double_term' => false,
            'approximate_end_of_day' => true,
            'suspensions' => [$suspension]
        ];

        $deadline = $this->calculator->calculate($params);

        // Deadline original sería: martes 31 diciembre (5 días hábiles desde 24 dic)
        // Suspensión incluye 24 dic (martes) y 26 dic (jueves) = 2 días hábiles
        // 25 dic es festivo, no cuenta
        // Deadline final: viernes 3 enero 2025
        $expectedDate = Carbon::create(2025, 1, 3, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testPreciseBusinessDaysCalculation(): void
    {
        // Test de precisión con diferentes horas de inicio
        $createdAt = Carbon::create(2024, 2, 15, 23, 45, 30); // Jueves 15 febrero 2024 a las 23:45:30

        $params = [
            'created_at' => $createdAt,
            'base_days' => 7,
            'double_term' => false,
            'approximate_end_of_day' => false, // Mantener hora exacta
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Siguiente día hábil: viernes 16 febrero
        // 7 días hábiles: vie 16, lun 19, mar 20, mié 21, jue 22, vie 23, lun 26
        $nextBusinessDay = $createdAt->copy()->nextBusinessDay();
        $expectedDate = $nextBusinessDay->copy()->addBusinessDays(6);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }

    public function testLongTermCalculation(): void
    {
        // Test con plazo largo (consulta: 30 días hábiles)
        $createdAt = Carbon::create(2024, 1, 15, 14, 0, 0); // Lunes 15 enero 2024

        $wrapper = DeadlineWrapper::setup(RequestType::CONSULTA->value, $createdAt, false);
        $deadline = $wrapper->calculateDeadline();

        // 30 días hábiles desde martes 16 enero = lunes 26 febrero
        $expectedDate = Carbon::create(2024, 2, 26, 23, 59, 59);

        $this->assertEquals($expectedDate->format('Y-m-d H:i:s'), $deadline->format('Y-m-d H:i:s'));
    }
}
