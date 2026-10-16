<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use ArgumentCountError;
use Error;
use Micrositios\PqrDeadline\Enums\TimeUnit;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Rules\DeadlineRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeadlineRulesTest extends TestCase
{
    private static function daily(array $changes = []): DeadlineRules
    {
        return new DeadlineRules(...array_replace([
            'plazo' => 10, 'unidad' => 'DC', 'plazoAmpliado' => 20,
            'permiteSuspension' => false, 'inicio' => 'DIA_SIGUIENTE', 'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => false, 'zonaHoraria' => 'America/Bogota',
            'horaCierre' => '23:59:59.999999',
        ], $changes));
    }

    public function testCalendarDaysAndExplicitExtensionTotals(): void
    {
        $rules = self::daily(['plazo' => 15, 'plazoAmpliado' => 23]);
        self::assertSame(TimeUnit::DC, $rules->unidad);
        self::assertSame(15, $rules->plazo);
        self::assertSame(23, $rules->plazoAmpliado);
        self::assertFalse($rules->permiteSuspension);
        self::assertNull($rules->calendario);
    }

    public function testHourlyRulesRequireNoCalendarOrClosingHour(): void
    {
        $rules = new DeadlineRules(48, TimeUnit::H, 96, false, 'INMEDIATO', 'INMEDIATO', false, 'Europe/Madrid');
        self::assertSame(TimeUnit::H, $rules->unidad);
        self::assertSame('Europe/Madrid', $rules->zonaHoraria);
        self::assertNull($rules->horaCierre);
    }

    public function testBusinessRulesAndCustomClosingHourRequireNoWeekConfiguration(): void
    {
        $rules = self::daily(['unidad' => TimeUnit::DH, 'inicio' => 'SIGUIENTE_HABIL', 'permiteSuspension' => true,
            'calendario' => 'COLOMBIA', 'horaCierre' => '16:30:00.12']);
        self::assertSame('16:30:00.120000', $rules->horaCierre);
        self::assertArrayNotHasKey('diasHabiles', get_object_vars($rules));
        $this->expectException(Error::class);
        $rules->horaCierre = '17:00:00';
    }

    public static function removedWeekArguments(): iterable
    {
        yield ['diasHabiles'];
        yield ['weekdays'];
    }

    #[DataProvider('removedWeekArguments')]
    public function testBusinessWeekCannotBeSuppliedAsAnArgument(string $argument): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown named parameter $' . $argument);
        self::daily([$argument => ['SABADO']]);
    }

    public function testAnAdjustmentInCalendarDaysRequiresAnExplicitCalendar(): void
    {
        $rules = self::daily(['ajustarVencimientoAHabil' => true, 'calendario' => 'COLOMBIA']);
        self::assertSame(TimeUnit::DC, $rules->unidad);
        self::assertTrue($rules->ajustarVencimientoAHabil);
    }

    public function testMandatoryPoliciesCannotBeOmitted(): void
    {
        $this->expectException(ArgumentCountError::class);
        new DeadlineRules(plazo: 10);
    }

    public static function invalidRules(): iterable
    {
        foreach ([0, -1, 1.5, '15', true, null] as $value) {
            yield 'plazo ' . var_export($value, true) => [['plazo' => $value], 'PLAZO_INVALIDO', 'reglas.plazo'];
        }
        foreach ([null, 0, 5, 10, 15.5, '20', false] as $value) {
            yield 'ampliado ' . var_export($value, true) => [['plazoAmpliado' => $value], 'PLAZO_AMPLIADO_INVALIDO', 'reglas.plazoAmpliado'];
        }
        yield [['unidad' => 'HORAS_HABILES'], 'UNIDAD_DE_PLAZO_NO_SOPORTADA', 'reglas.unidad'];
        yield [['inicio' => 'INSTANTE'], 'REGLA_INICIO_NO_SOPORTADA', 'reglas.inicio'];
        yield [['cierre' => 'DESCONOCIDO'], 'REGLA_CIERRE_NO_SOPORTADA', 'reglas.cierre'];
        yield [['inicio' => 'INMEDIATO'], 'REGLAS_INCOMPATIBLES', 'reglas.inicio'];
        yield [['cierre' => 'INMEDIATO'], 'REGLAS_INCOMPATIBLES', 'reglas.cierre'];
        yield [['unidad' => 'DH'], 'REGLAS_INCOMPLETAS', 'reglas.calendario'];
        yield [['ajustarVencimientoAHabil' => true], 'REGLAS_INCOMPLETAS', 'reglas.calendario'];
        yield [['inicio' => 'SIGUIENTE_HABIL'], 'REGLAS_INCOMPLETAS', 'reglas.calendario'];
        yield [['unidad' => 'DH', 'calendario' => 'DESCONOCIDO'], 'CALENDARIO_NO_SOPORTADO', 'reglas.calendario'];
        yield [['calendario' => 'COLOMBIA'], 'REGLAS_INCOMPATIBLES', 'reglas.calendario'];
        yield [['zonaHoraria' => 'Zona/Inventada'], 'ZONA_HORARIA_DESCONOCIDA', 'reglas.zonaHoraria'];
        yield [['horaCierre' => null], 'REGLAS_INCOMPLETAS', 'reglas.horaCierre'];
        foreach (['24:00:00', '23:60:00', '23:59:60', '16:30', "16:30:00\n", '16:30:00.1234567'] as $hour) {
            yield [['horaCierre' => $hour], 'HORA_CIERRE_INVALIDA', 'reglas.horaCierre'];
        }
    }

    #[DataProvider('invalidRules')]
    public function testInvalidRulesHaveControlledCodesAndFields(array $changes, string $code, string $field): void
    {
        try {
            self::daily($changes);
            self::fail('Se esperaba un error de reglas.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame($code, $error->code());
            self::assertContains($field, $error->toArray()['campos']);
        }
    }

    public function testInformationalFlagsDoNotRestrictUnitsOrStartRules(): void
    {
        self::assertTrue(self::daily(['permiteSuspension' => true])->permiteSuspension);
        $hours = new DeadlineRules(24, 'H', 48, true, 'INMEDIATO', 'INMEDIATO', false, 'UTC', permiteAmpliacion: false);
        self::assertTrue($hours->permiteSuspension);
        self::assertFalse($hours->permiteAmpliacion);
        self::assertSame(48, $hours->plazoAmpliado);
        self::assertSame('MISMO_DIA', self::daily(['unidad' => 'DH', 'inicio' => 'MISMO_DIA',
            'permiteSuspension' => true, 'calendario' => 'COLOMBIA'])->inicio);
    }

    public static function incompatibleHours(): iterable
    {
        yield [['inicio' => 'DIA_SIGUIENTE']];
        yield [['cierre' => 'FIN_DEL_DIA']];
        yield [['ajustarVencimientoAHabil' => true]];
        yield [['horaCierre' => '16:00:00']];
        yield [['calendario' => 'COLOMBIA']];
    }

    #[DataProvider('incompatibleHours')]
    public function testHoursCannotAcquireDailyRules(array $changes): void
    {
        try {
            new DeadlineRules(...array_replace([
                'plazo' => 24, 'unidad' => 'H', 'plazoAmpliado' => 48, 'permiteSuspension' => false,
                'inicio' => 'INMEDIATO', 'cierre' => 'INMEDIATO', 'ajustarVencimientoAHabil' => false,
                'zonaHoraria' => 'America/Bogota',
            ], $changes));
            self::fail('Se esperaba una incompatibilidad.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('REGLAS_INCOMPATIBLES', $error->code());
        }
    }
}
