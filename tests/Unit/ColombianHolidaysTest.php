<?php

namespace Micrositios\PqrDeadline\Tests\Unit;

use Carbon\Carbon;
use Micrositios\PqrDeadline\Strategies\BusinessDaysDeadlineCalculator;
use PHPUnit\Framework\TestCase;

class ColombianHolidaysTest extends TestCase
{
    private BusinessDaysDeadlineCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        // Crear el calculador que internamente configura BusinessDay para Colombia
        $this->calculator = new BusinessDaysDeadlineCalculator();
    }

    public function testColombianNationalHolidaysAreRecognized(): void
    {
        // Test de los festivos nacionales específicos en 3 años consecutivos
        $testCases = [
            // 2023
            ['2023-12-25', 'Navidad 2023'],
            ['2023-01-01', 'Año Nuevo 2023'],
            ['2023-07-20', 'Independencia 2023'],
            ['2023-08-07', 'Boyacá 2023'],

            // 2024
            ['2024-12-25', 'Navidad 2024'],
            ['2024-01-01', 'Año Nuevo 2024'],
            ['2024-07-20', 'Independencia 2024'],
            ['2024-08-07', 'Boyacá 2024'],

            // 2025
            ['2025-12-25', 'Navidad 2025'],
            ['2025-01-01', 'Año Nuevo 2025'],
            ['2025-07-20', 'Independencia 2025'],
            ['2025-08-07', 'Boyacá 2025'],
        ];

        foreach ($testCases as [$dateString, $description]) {
            $holiday = Carbon::parse($dateString);
            $this->assertFalse(
                $holiday->isBusinessDay(),
                "{$description} ({$dateString}) debe ser festivo en Colombia"
            );
        }
    }

    public function testWeekendsAreNotBusinessDays(): void
    {
        // Sábado 13 de Enero 2024
        $saturday = Carbon::create(2024, 1, 13);
        $this->assertEquals(6, $saturday->dayOfWeek); // Confirmar que es sábado
        $this->assertFalse($saturday->isBusinessDay(), 'Los sábados no deben ser días hábiles');

        // Domingo 14 de Enero 2024
        $sunday = Carbon::create(2024, 1, 14);
        $this->assertEquals(0, $sunday->dayOfWeek); // Confirmar que es domingo
        $this->assertFalse($sunday->isBusinessDay(), 'Los domingos no deben ser días hábiles');
    }

    public function testRegularWeekdaysAreBusinessDays(): void
    {
        // Probar un día regular (lunes no festivo)
        $regularMonday = Carbon::create(2024, 1, 15); // Lunes 15 de Enero 2024
        $this->assertEquals(1, $regularMonday->dayOfWeek); // Confirmar que es lunes
        $this->assertTrue(
            $regularMonday->isBusinessDay(),
            'Un lunes regular debería ser día hábil'
        );

        // Probar un día regular (miércoles no festivo)
        $regularWednesday = Carbon::create(2024, 1, 17); // Miércoles 17 de Enero 2024
        $this->assertEquals(3, $regularWednesday->dayOfWeek); // Confirmar que es miércoles
        $this->assertTrue(
            $regularWednesday->isBusinessDay(),
            'Un miércoles regular debería ser día hábil'
        );
    }

    public function testAdditionalColombianHolidays(): void
    {
        // Verificar algunos festivos adicionales importantes de Colombia
        $year = 2024;

        // 1 de Mayo - Día del Trabajo (festivo fijo)
        $laborDay = Carbon::create($year, 5, 1);
        $this->assertFalse(
            $laborDay->isBusinessDay(),
            "1 de Mayo {$year} debería ser festivo en Colombia"
        );

        // 8 de Diciembre - Inmaculada Concepción (festivo fijo)
        $immaculateConception = Carbon::create($year, 12, 8);
        $this->assertFalse(
            $immaculateConception->isBusinessDay(),
            "8 de Diciembre {$year} debería ser festivo en Colombia"
        );
    }

    public function testBusinessDaysCalculatorHandlesHolidays(): void
    {
        // Verificar que el calculador funciona correctamente saltando festivos
        $createdAt = Carbon::create(2023, 12, 22, 10, 0, 0); // Viernes 22 de diciembre antes de Navidad

        $params = [
            'created_at' => $createdAt,
            'base_days' => 2, // 2 días hábiles
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // El deadline debería saltar Navidad (25 dic), fin de semana (23-24 dic)
        // y posiblemente otros festivos de fin de año
        $this->assertTrue(
            $deadline->greaterThan(Carbon::create(2023, 12, 25)),
            'El deadline debe ser posterior a Navidad cuando se calcula desde antes de las fiestas'
        );

        // Debería ser un día hábil
        $this->assertTrue(
            $deadline->isBusinessDay(),
            'El deadline calculado debe ser un día hábil'
        );
    }

    public function testBusinessDaysCalculationFromRegularDay(): void
    {
        // Test desde un día regular para verificar funcionamiento básico
        $createdAt = Carbon::create(2024, 1, 15, 10, 0, 0); // Lunes 15 de enero 2024 (día regular)

        $params = [
            'created_at' => $createdAt,
            'base_days' => 5, // 5 días hábiles
            'double_term' => false,
            'approximate_end_of_day' => false,
            'suspensions' => []
        ];

        $deadline = $this->calculator->calculate($params);

        // Debería ser un día hábil
        $this->assertTrue(
            $deadline->isBusinessDay(),
            'El deadline calculado debe ser un día hábil'
        );

        // Debería ser después de la fecha de creación
        $this->assertTrue(
            $deadline->greaterThan($createdAt),
            'El deadline debe ser posterior a la fecha de creación'
        );
    }

    public function testHolidaysInSequence(): void
    {
        // Verificar que múltiples festivos en secuencia son manejados correctamente
        $holidays = [
            Carbon::create(2024, 12, 25), // Navidad
            Carbon::create(2024, 1, 1),   // Año Nuevo
            Carbon::create(2024, 7, 20),  // Independencia
            Carbon::create(2024, 8, 7),   // Boyacá
            Carbon::create(2024, 5, 1),   // Día del Trabajo
            Carbon::create(2024, 12, 8),  // Inmaculada Concepción
        ];

        foreach ($holidays as $holiday) {
            $this->assertFalse(
                $holiday->isBusinessDay(),
                "La fecha {$holiday->toDateString()} debe ser festivo en Colombia"
            );
        }
    }
}
