<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Carbon\CarbonImmutable;
use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Input\Suspension;
use Micrositios\PqrDeadline\Rules\DeadlineRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeadlineCalculatorTest extends TestCase
{
    private static function rules(string $unit = 'DH', int $term = 5, int $extended = 10, array $changes = []): DeadlineRules
    {
        $hours = $unit === 'H';
        $business = $unit === 'DH';

        return new DeadlineRules(...array_replace([
            'plazo' => $term, 'unidad' => $unit, 'plazoAmpliado' => $extended,
            'permiteSuspension' => false, 'permiteAmpliacion' => false,
            'inicio' => $hours ? 'INMEDIATO' : ($business ? 'SIGUIENTE_HABIL' : 'DIA_SIGUIENTE'),
            'cierre' => $hours ? 'INMEDIATO' : 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => $business, 'zonaHoraria' => 'America/Bogota',
            'calendario' => $business ? 'COLOMBIA' : null,
            'horaCierre' => $hours ? null : '23:59:59.999999',
        ], $changes));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public static function baseCases(): iterable
    {
        yield ['DH', 1, '2026-10-09T10:00:00-05:00', '2026-10-13T23:59:59.999999-05:00'];
        yield ['DH', 5, '2026-10-09T10:00:00-05:00', '2026-10-19T23:59:59.999999-05:00'];
        yield ['DH', 5, '2026-10-10T10:00:00-05:00', '2026-10-19T23:59:59.999999-05:00'];
        yield ['DC', 3, '2026-10-09T10:00:00-05:00', '2026-10-12T23:59:59.999999-05:00'];
        yield ['DC', 2, '1996-02-28T10:00:00-05:00', '1996-03-01T23:59:59.999999-05:00'];
        yield ['DC', 2, '2026-12-31T10:00:00-05:00', '2027-01-02T23:59:59.999999-05:00'];
        yield ['H', 72, '2026-10-09T10:00:00.123456-05:00', '2026-10-12T10:00:00.123456-05:00'];
    }

    #[DataProvider('baseCases')]
    public function testBaseDatesWithoutEvents(string $unit, int $term, string $filing, string $expected): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest($filing, reglas: self::rules($unit, $term, 200)));
        self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
        self::assertEquals($result->fechaVencimientoInicial, $result->fechaMaximaRespuesta);
        self::assertFalse($result->conCierrePendiente);
        self::assertSame([], $result->avisos);
    }

    public static function baseCatalog(): iterable
    {
        yield ['PETICION_GENERAL', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['SOLICITUD_INFORMACION', 10, 'DH', '2026-10-26T23:59:59.999999-05:00'];
        yield ['CONSULTA', 30, 'DH', '2026-11-25T23:59:59.999999-05:00'];
        yield ['QUEJA', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['RECLAMO', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['SUGERENCIA', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['FELICITACION', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['PETICION_INFORMACION_ENTRE_AUTORIDADES', 10, 'DH', '2026-10-26T23:59:59.999999-05:00'];
        yield ['INFORME_CONGRESISTAS', 5, 'DH', '2026-10-19T23:59:59.999999-05:00'];
        yield ['RECLAMO_SALUD_SIMPLE', 72, 'H', '2026-10-12T18:30:00.123456-05:00'];
        yield ['RECLAMO_SALUD_PRIORIZADO', 48, 'H', '2026-10-11T18:30:00.123456-05:00'];
        yield ['RECLAMO_SALUD_VITAL', 24, 'H', '2026-10-10T18:30:00.123456-05:00'];
        yield ['RECLAMO_SALUD_VITAL_MENOR', 8, 'H', '2026-10-10T02:30:00.123456-05:00'];
        yield ['COPIAS_SALUD', 3, 'DH', '2026-10-15T23:59:59.999999-05:00'];
        yield ['PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['PQR_TELECOMUNICACIONES', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['PQR_POSTAL', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['CONSULTA_DATOS_PERSONALES', 10, 'DH', '2026-10-26T23:59:59.999999-05:00'];
        yield ['RECLAMO_DATOS_PERSONALES', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
        yield ['CONSULTA_HABEAS_DATA_FINANCIERO', 10, 'DH', '2026-10-26T23:59:59.999999-05:00'];
        yield ['RECLAMO_HABEAS_DATA_FINANCIERO', 15, 'DH', '2026-11-03T23:59:59.999999-05:00'];
    }

    #[DataProvider('baseCatalog')]
    public function testEveryTypeAndExplicitRulesProduceTheExpectedBaseDeadline(string $code, int $term, string $unit, string $expected): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-11-04T12:00:00-05:00'));
        $filing = '2026-10-09T18:30:00.123456-05:00';
        $request = new CalculationRequest($filing, tipoSolicitud: RequestType::desdeCodigo($code));
        $calculator = new DeadlineCalculator();
        $typed = $calculator->calcular($request);
        $explicit = $calculator->calcular(new CalculationRequest($filing, reglas: self::rules($unit, $term, 200)));
        self::assertSame($expected, $typed->toArray()['fechaVencimientoInicial']);
        self::assertSame($expected, $typed->toArray()['fechaMaximaRespuesta']);
        self::assertSame($typed->toArray(), $explicit->toArray());
        self::assertFalse($typed->conCierrePendiente);
        self::assertSame([], $typed->avisos);
        self::assertSame($filing, $request->fechaRadicacion->format('Y-m-d\TH:i:s.uP'));
        self::assertFalse($request->ampliarPlazo);
    }

    public static function dailyStarts(): iterable
    {
        yield 'A03 diez DC' => ['DC', 'DIA_SIGUIENTE', false, '2026-10-09', 10, '2026-10-19'];
        yield 'DC sábado sin ajuste' => ['DC', 'DIA_SIGUIENTE', false, '2026-10-09', 1, '2026-10-10'];
        yield 'DC sábado ajustado' => ['DC', 'DIA_SIGUIENTE', true, '2026-10-09', 1, '2026-10-13'];
        yield 'DC vencimiento ya hábil' => ['DC', 'DIA_SIGUIENTE', true, '2026-10-09', 4, '2026-10-13'];
        yield 'DC mismo sábado' => ['DC', 'MISMO_DIA', false, '2026-10-10', 1, '2026-10-10'];
        yield 'DC inclusivo con fin de semana' => ['DC', 'MISMO_DIA', false, '2026-10-09', 3, '2026-10-11'];
        yield 'DC inicia en hábil sin excluir días intermedios' => ['DC', 'SIGUIENTE_HABIL', false, '2026-10-09', 7, '2026-10-19'];
        yield 'DH mismo laborable' => ['DH', 'MISMO_DIA', true, '2026-10-09', 1, '2026-10-09'];
        yield 'DH mismo sábado' => ['DH', 'MISMO_DIA', true, '2026-10-10', 1, '2026-10-13'];
        yield 'DH mismo festivo' => ['DH', 'MISMO_DIA', true, '2026-10-12', 1, '2026-10-13'];
        yield 'DH día siguiente inhábil' => ['DH', 'DIA_SIGUIENTE', true, '2026-10-09', 1, '2026-10-13'];
        yield 'DH cambio de año' => ['DH', 'SIGUIENTE_HABIL', true, '2026-12-24', 5, '2027-01-04'];
        yield 'DC bisiesto' => ['DC', 'DIA_SIGUIENTE', false, '2024-02-28', 1, '2024-02-29'];
        yield 'DC no bisiesto' => ['DC', 'DIA_SIGUIENTE', false, '2025-02-28', 1, '2025-03-01'];
        yield 'DH cruza febrero bisiesto' => ['DH', 'SIGUIENTE_HABIL', true, '2024-02-28', 2, '2024-03-01'];
    }

    #[DataProvider('dailyStarts')]
    public function testDailyStartCountingAndFinalAdjustment(string $unit, string $start, bool $adjust, string $filing, int $term, string $expected): void
    {
        $rules = self::rules($unit, $term, 200, [
            'inicio' => $start, 'ajustarVencimientoAHabil' => $adjust,
            'calendario' => $unit === 'DH' || $start === 'SIGUIENTE_HABIL' || $adjust ? 'COLOMBIA' : null,
        ]);
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest($filing . 'T10:00:00-05:00', reglas: $rules));
        self::assertSame($expected . 'T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertEquals($result->fechaVencimientoInicial, $result->fechaMaximaRespuesta);
    }

    public static function dstCases(): iterable
    {
        yield 'H salto de primavera' => ['H', 8, '2026-03-08T00:30:00.123456-05:00', '2026-03-08T09:30:00.123456-04:00'];
        yield 'H repetición de otoño' => ['H', 8, '2026-11-01T00:30:00.123456-04:00', '2026-11-01T07:30:00.123456-05:00'];
        yield 'DC día de 25 horas' => ['DC', 1, '2026-10-31T12:00:00-04:00', '2026-11-01T23:59:59.999999-05:00'];
    }

    #[DataProvider('dstCases')]
    public function testDaylightSavingDoesNotChangeTheMeaningOfHoursOrDays(string $unit, int $term, string $filing, string $expected): void
    {
        $request = new CalculationRequest($filing, reglas: self::rules($unit, $term, 200, ['zonaHoraria' => 'America/New_York']));
        $result = (new DeadlineCalculator())->calcular($request);
        self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
        if ($unit === 'H') {
            self::assertSame($term * 3600, $result->fechaMaximaRespuesta->getTimestamp() - $request->fechaRadicacion->getTimestamp());
        }
    }

    public function testTheRulesTimezoneDeterminesTheInitialCalendarDate(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-10T02:00:00Z', reglas: self::rules('DC', 1, 2)));
        self::assertSame('2026-10-10T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
    }

    public function testBusinessDaysPreserveTheConfiguredClosingTimePrecision(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00',
            reglas: self::rules('DH', 1, 2, ['horaCierre' => '16:45:30.123456'])));
        self::assertSame('2026-10-13T16:45:30.123456-05:00', $result->toArray()['fechaMaximaRespuesta']);
    }

    public function testA44TheBaseDeadlineDoesNotDependOnQueryTimeOrServerTimezone(): void
    {
        $serverZone = date_default_timezone_get();
        try {
            $request = new CalculationRequest('2026-10-09T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL);
            $calculator = new DeadlineCalculator();
            date_default_timezone_set('Pacific/Apia');
            CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-09T12:00:00-05:00'));
            $first = $calculator->calcular($request);
            date_default_timezone_set('Europe/Madrid');
            CarbonImmutable::setTestNow(new CarbonImmutable('2026-11-04T12:00:00-05:00'));
            $second = $calculator->calcular($request);
            self::assertSame('2026-11-03T23:59:59.999999-05:00', $second->toArray()['fechaMaximaRespuesta']);
            self::assertEquals($first->fechaMaximaRespuesta, $second->fechaMaximaRespuesta);
            self::assertFalse($first->conCierrePendiente);
            self::assertFalse($second->conCierrePendiente);
            self::assertSame([], $second->avisos);
            self::assertSame('2026-11-04T12:00:00.000000-05:00', $second->toArray()['fechaConsulta']);
            self::assertNotEquals($first->fechaConsulta, $second->fechaConsulta);
        } finally {
            date_default_timezone_set($serverZone);
        }
    }

    public function testAnUnusedExtensionDoesNotPreventARepresentableBaseResult(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('9999-12-31T22:00:00.123456Z',
            reglas: self::rules('H', 1, 48, ['zonaHoraria' => 'UTC'])));
        self::assertSame('9999-12-31T23:00:00.123456+00:00', $result->toArray()['fechaMaximaRespuesta']);
    }

    public static function catalogExtensions(): iterable
    {
        yield [RequestType::PETICION_GENERAL, '2026-11-25T23:59:59.999999-05:00', false];
        yield [RequestType::SOLICITUD_INFORMACION, '2026-11-10T23:59:59.999999-05:00', false];
        yield [RequestType::CONSULTA, '2027-01-12T23:59:59.999999-05:00', false];
        yield [RequestType::QUEJA, '2026-11-25T23:59:59.999999-05:00', false];
        yield [RequestType::RECLAMO, '2026-11-25T23:59:59.999999-05:00', false];
        yield [RequestType::SUGERENCIA, '2026-11-25T23:59:59.999999-05:00', false];
        yield [RequestType::FELICITACION, '2026-11-25T23:59:59.999999-05:00', false];
        yield [RequestType::PETICION_INFORMACION_ENTRE_AUTORIDADES, '2026-11-10T23:59:59.999999-05:00', true];
        yield [RequestType::RECLAMO_SALUD_SIMPLE, '2026-10-15T10:00:00.000000-05:00', true];
        yield [RequestType::RECLAMO_SALUD_PRIORIZADO, '2026-10-13T10:00:00.000000-05:00', true];
        yield [RequestType::RECLAMO_SALUD_VITAL, '2026-10-11T10:00:00.000000-05:00', true];
        yield [RequestType::RECLAMO_SALUD_VITAL_MENOR, '2026-10-10T02:00:00.000000-05:00', true];
        yield [RequestType::INFORME_CONGRESISTAS, '2026-10-26T23:59:59.999999-05:00', true];
        yield [RequestType::COPIAS_SALUD, '2026-10-20T23:59:59.999999-05:00', true];
        yield [RequestType::PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS, '2026-11-25T23:59:59.999999-05:00', true];
        yield [RequestType::PQR_TELECOMUNICACIONES, '2026-11-25T23:59:59.999999-05:00', false];
        yield [RequestType::PQR_POSTAL, '2026-11-25T23:59:59.999999-05:00', true];
        yield [RequestType::CONSULTA_DATOS_PERSONALES, '2026-11-03T23:59:59.999999-05:00', false];
        yield [RequestType::CONSULTA_HABEAS_DATA_FINANCIERO, '2026-11-03T23:59:59.999999-05:00', false];
        yield [RequestType::RECLAMO_DATOS_PERSONALES, '2026-11-13T23:59:59.999999-05:00', false];
        yield [RequestType::RECLAMO_HABEAS_DATA_FINANCIERO, '2026-11-13T23:59:59.999999-05:00', false];
    }

    #[DataProvider('catalogExtensions')]
    public function testConfiguredExtensionWinsAndSpecialTotalsRemain(RequestType $type, string $expected, bool $notice): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-09T12:00:00-05:00'));
        $calculator = new DeadlineCalculator();
        $filing = '2026-10-09T10:00:00-05:00';
        $request = new CalculationRequest($filing, tipoSolicitud: $type, ampliarPlazo: true);
        $rulesBefore = get_object_vars($request->reglas);
        $first = $calculator->calcular($request);
        $omitted = $calculator->calcular(new CalculationRequest($filing, tipoSolicitud: $type));
        $disabled = $calculator->calcular(new CalculationRequest($filing, tipoSolicitud: $type, ampliarPlazo: false));
        $second = $calculator->calcular($request);
        self::assertSame($expected, $first->toArray()['fechaMaximaRespuesta']);
        self::assertSame($first->toArray(), $second->toArray());
        self::assertSame($omitted->toArray(), $disabled->toArray());
        self::assertEquals($omitted->fechaMaximaRespuesta, $first->fechaVencimientoInicial);
        self::assertEquals($omitted->fechaVencimientoInicial, $omitted->fechaMaximaRespuesta);
        self::assertFalse($first->conCierrePendiente);
        self::assertSame([], $disabled->avisos);
        self::assertSame($notice ? ['AMPLIACION_NO_PREVISTA'] : [], array_column($first->avisos, 'codigo'));
        self::assertSame($rulesBefore, get_object_vars($request->reglas));
        self::assertSame($filing, $request->fechaRadicacion->format('Y-m-d\TH:i:sP'));
        self::assertTrue($request->ampliarPlazo);
    }

    public static function explicitExtensions(): iterable
    {
        yield 'H total de once horas' => ['H', 8, 11, [], '2026-10-09T10:00:00.123456-05:00',
            '2026-10-09T18:00:00.123456-05:00', '2026-10-09T21:00:00.123456-05:00'];
        yield 'DH total de siete días' => ['DH', 5, 7, [], '2026-10-09T10:00:00-05:00',
            '2026-10-19T23:59:59.999999-05:00', '2026-10-21T23:59:59.999999-05:00'];
        yield 'DC total de siete días' => ['DC', 5, 7, [], '2026-10-09T10:00:00-05:00',
            '2026-10-14T23:59:59.999999-05:00', '2026-10-16T23:59:59.999999-05:00'];
        yield 'DC conserva inicio inclusivo y hora de cierre' => ['DC', 5, 7, ['inicio' => 'MISMO_DIA', 'horaCierre' => '16:30:00'],
            '2026-10-09T10:00:00-05:00', '2026-10-13T16:30:00.000000-05:00', '2026-10-15T16:30:00.000000-05:00'];
        yield 'DC no suma al vencimiento inicialmente ajustado' => ['DC', 1, 5, ['ajustarVencimientoAHabil' => true, 'calendario' => 'COLOMBIA'],
            '2026-10-09T10:00:00-05:00', '2026-10-13T23:59:59.999999-05:00', '2026-10-14T23:59:59.999999-05:00'];
        yield 'H ampliadas siguen siendo continuas con cambio horario' => ['H', 8, 11, ['zonaHoraria' => 'America/New_York'],
            '2026-03-08T00:30:00.123456-05:00', '2026-03-08T09:30:00.123456-04:00', '2026-03-08T12:30:00.123456-04:00'];
    }

    #[DataProvider('explicitExtensions')]
    public function testExplicitTotalsPreserveCountingRulesRegardlessOfPermission(string $unit, int $term, int $extended, array $changes, string $filing, string $initial, string $expected): void
    {
        $calculator = new DeadlineCalculator();
        foreach ([false, true] as $permission) {
            $rules = self::rules($unit, $term, $extended, array_replace($changes, ['permiteAmpliacion' => $permission]));
            $result = $calculator->calcular(new CalculationRequest($filing, reglas: $rules, ampliarPlazo: true));
            self::assertSame($initial, $result->toArray()['fechaVencimientoInicial']);
            self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
            self::assertSame($permission ? [] : ['AMPLIACION_NO_PREVISTA'], array_column($result->avisos, 'codigo'));
            $base = $calculator->calcular(new CalculationRequest($filing, reglas: $rules));
            self::assertSame($initial, $base->toArray()['fechaMaximaRespuesta']);
            self::assertSame([], $base->avisos);
        }
    }

    public function testAnExtensionAfterExpiryStillCountsFromTheOriginalFiling(): void
    {
        $calculator = new DeadlineCalculator();
        $request = new CalculationRequest('2026-10-09T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL, ampliarPlazo: true);
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-09T12:00:00-05:00'));
        $first = $calculator->calcular($request);
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-12-01T12:00:00-05:00'));
        $later = $calculator->calcular($request);
        self::assertSame('2026-11-03T23:59:59.999999-05:00', $later->toArray()['fechaVencimientoInicial']);
        self::assertSame('2026-11-25T23:59:59.999999-05:00', $later->toArray()['fechaMaximaRespuesta']);
        self::assertEquals($first->fechaMaximaRespuesta, $later->fechaMaximaRespuesta);
        self::assertSame('2026-12-01T12:00:00.000000-05:00', $later->toArray()['fechaConsulta']);
        self::assertFalse($later->conCierrePendiente);
        self::assertSame([], $later->avisos);
    }

    public static function suspensionsAcrossUnits(): iterable
    {
        yield ['DH', 5, 10, '2026-10-14T10:00:00-05:00', '2026-10-20T10:00:00-05:00', '2026-10-23T23:59:59.999999-05:00'];
        yield ['DC', 5, 10, '2026-10-11T10:00:00-05:00', '2026-10-20T10:00:00-05:00', '2026-10-23T23:59:59.999999-05:00'];
        yield ['H', 24, 48, '2026-10-09T12:00:00-05:00', '2026-10-11T12:00:00-05:00', '2026-10-12T10:00:00.000000-05:00'];
    }

    #[DataProvider('suspensionsAcrossUnits')]
    public function testSuspensionsAreAppliedDespiteFalsePermissionAndMayEndAfterDeadline(string $unit, int $term, int $extended, string $start, string $end, string $expected): void
    {
        $pause = new Suspension($start, $end);
        $calculator = new DeadlineCalculator();
        foreach ([false, true] as $permission) {
            $request = new CalculationRequest('2026-10-09T10:00:00-05:00',
                reglas: self::rules($unit, $term, $extended, ['permiteSuspension' => $permission]), suspensiones: [$pause]);
            $before = serialize($request);
            CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-09T11:00:00-05:00'));
            $first = $calculator->calcular($request);
            CarbonImmutable::setTestNow(new CarbonImmutable('2026-12-01T11:00:00-05:00'));
            $later = $calculator->calcular($request);
            self::assertSame($expected, $first->toArray()['fechaMaximaRespuesta']);
            self::assertEquals($first->fechaMaximaRespuesta, $later->fechaMaximaRespuesta);
            self::assertEquals($first->fechaVencimientoInicial, $later->fechaVencimientoInicial);
            self::assertSame($permission ? [] : ['SUSPENSION_NO_PREVISTA'], array_column($first->avisos, 'codigo'));
            self::assertSame($first->avisos, $later->avisos);
            self::assertFalse($first->conCierrePendiente);
            self::assertFalse($later->conCierrePendiente);
            self::assertGreaterThan($first->fechaVencimientoInicial, $pause->fin);
            self::assertSame($before, serialize($request));
        }
    }

    public static function closedBusinessSuspensions(): iterable
    {
        yield 'A08 conserva saldo' => [false, '2026-10-19T09:00:00-05:00', '2026-10-26T15:00:00-05:00', '2026-11-10'];
        yield 'A09 ampliación no duplica la pausa' => [true, '2026-10-19T09:00:00-05:00', '2026-10-26T15:00:00-05:00', '2026-12-02'];
        yield 'A31 radica y suspende el mismo día' => [false, '2026-10-09T11:00:00-05:00', '2026-10-19T15:00:00-05:00', '2026-11-10'];
        yield 'A32 cierre el mismo día' => [false, '2026-10-19T09:00:00-05:00', '2026-10-19T15:00:00-05:00', '2026-11-03'];
        yield 'A33 sábado a lunes' => [false, '2026-10-17T09:00:00-05:00', '2026-10-19T15:00:00-05:00', '2026-11-04'];
        yield 'A34 fin de semana con lunes festivo' => [false, '2026-10-10T09:00:00-05:00', '2026-10-13T15:00:00-05:00', '2026-11-04'];
        yield 'A35 viernes a domingo' => [false, '2026-10-16T09:00:00-05:00', '2026-10-18T15:00:00-05:00', '2026-11-03'];
        yield 'A38 cierre explícito después de agotar saldo' => [false, '2026-11-03T09:00:00-05:00', '2026-11-10T15:00:00-05:00', '2026-11-03'];
    }

    #[DataProvider('closedBusinessSuspensions')]
    public function testClosedBusinessSuspensionsAgainstAcceptanceDates(bool $extend, string $start, string $end, string $expected): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-09T12:00:00-05:00'));
        $calculator = new DeadlineCalculator();
        $pauses = [new Suspension($start, $end)];
        $typed = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL,
            ampliarPlazo: $extend, suspensiones: $pauses));
        $explicit = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00',
            reglas: self::rules('DH', 15, 30, ['permiteSuspension' => true, 'permiteAmpliacion' => true]),
            ampliarPlazo: $extend, suspensiones: $pauses));
        self::assertSame('2026-11-03T23:59:59.999999-05:00', $typed->toArray()['fechaVencimientoInicial']);
        self::assertSame($expected . 'T23:59:59.999999-05:00', $typed->toArray()['fechaMaximaRespuesta']);
        self::assertSame($typed->toArray(), $explicit->toArray());
        self::assertFalse($typed->conCierrePendiente);
        self::assertSame([], $typed->avisos);
    }

    public function testBothWarningsAreReturnedAndTheSuspensionIsNotDoubled(): void
    {
        $request = new CalculationRequest('2026-10-09T10:00:00.123456-05:00', tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL,
            ampliarPlazo: true, suspensiones: [new Suspension('2026-10-09T12:00:00.100000-05:00', '2026-10-09T14:00:00.200000-05:00')]);
        $result = (new DeadlineCalculator())->calcular($request);
        self::assertSame('2026-10-11T12:00:00.223456-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertSame(['AMPLIACION_NO_PREVISTA', 'SUSPENSION_NO_PREVISTA'], array_column($result->avisos, 'codigo'));
        self::assertSame('2026-10-10T10:00:00.123456-05:00', $result->toArray()['fechaVencimientoInicial']);
    }

    public function testFilingAndSuspendingOnTheSameDayConsumeNoBusinessDay(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules('DH', 3, 6),
            suspensiones: [new Suspension('2026-10-09T10:00:00-05:00', '2026-10-14T10:00:00-05:00')]));
        self::assertSame('2026-10-19T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
    }

    public static function units(): iterable
    {
        yield ['H'];
        yield ['DC'];
        yield ['DH'];
    }

    #[DataProvider('units')]
    public function testEqualInstantsAreZeroAndDoNotChangeTheDeadline(string $unit): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules($unit),
            suspensiones: [new Suspension('2026-10-09T11:00:00-05:00', '2026-10-09T16:00:00Z')]));
        self::assertEquals($result->fechaVencimientoInicial, $result->fechaMaximaRespuesta);
    }

    public function testWeekendPauseDoesNotConsumeAnExtraBusinessDay(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules(),
            suspensiones: [new Suspension('2026-10-10T10:00:00-05:00', '2026-10-12T10:00:00-05:00')]));
        self::assertEquals($result->fechaVencimientoInicial, $result->fechaMaximaRespuesta);
    }

    public static function suspensionUnions(): iterable
    {
        $overlaps = [
            ['2026-10-09T12:00:00-05:00', '2026-10-12T10:00:00-05:00'],
            ['2026-10-09T14:00:00-05:00', '2026-10-14T10:00:00-05:00'],
            ['2026-10-09T16:00:00-05:00', '2026-10-10T10:00:00-05:00'],
        ];
        yield 'H solapadas y contenidas' => ['H', 24, $overlaps, '2026-10-15T08:00:00.000000-05:00'];
        yield 'DC solapadas y contenidas' => ['DC', 5, $overlaps, '2026-10-19T23:59:59.999999-05:00'];
        yield 'DH solapadas y contenidas' => ['DH', 5, $overlaps, '2026-10-21T23:59:59.999999-05:00'];
        yield 'H contiguas' => ['H', 24, [
            ['2026-10-09T12:00:00-05:00', '2026-10-09T14:00:00-05:00'],
            ['2026-10-09T14:00:00-05:00', '2026-10-09T16:00:00-05:00'],
        ], '2026-10-10T14:00:00.000000-05:00'];
        yield 'DC contiguas' => ['DC', 5, [
            ['2026-10-10T09:00:00-05:00', '2026-10-11T09:00:00-05:00'],
            ['2026-10-11T09:00:00-05:00', '2026-10-12T09:00:00-05:00'],
        ], '2026-10-16T23:59:59.999999-05:00'];
        yield 'DH contiguas' => ['DH', 5, [
            ['2026-10-13T09:00:00-05:00', '2026-10-14T09:00:00-05:00'],
            ['2026-10-14T09:00:00-05:00', '2026-10-15T09:00:00-05:00'],
        ], '2026-10-21T23:59:59.999999-05:00'];
        yield 'H separadas conservan horas libres' => ['H', 24, [
            ['2026-10-09T12:00:00-05:00', '2026-10-09T14:00:00-05:00'],
            ['2026-10-09T16:00:00-05:00', '2026-10-09T18:00:00-05:00'],
        ], '2026-10-10T14:00:00.000000-05:00'];
        yield 'DC separadas conservan días libres' => ['DC', 5, [
            ['2026-10-10T09:00:00-05:00', '2026-10-11T09:00:00-05:00'],
            ['2026-10-12T09:00:00-05:00', '2026-10-13T09:00:00-05:00'],
        ], '2026-10-16T23:59:59.999999-05:00'];
        yield 'DH separadas conservan días libres' => ['DH', 5, [
            ['2026-10-13T09:00:00-05:00', '2026-10-14T09:00:00-05:00'],
            ['2026-10-15T09:00:00-05:00', '2026-10-16T09:00:00-05:00'],
        ], '2026-10-21T23:59:59.999999-05:00'];
    }

    #[DataProvider('suspensionUnions')]
    public function testOverlapsDuplicatesAndOrderingNeverDoubleCountTime(string $unit, int $term, array $intervals, string $expected): void
    {
        $calculator = new DeadlineCalculator();
        $rules = self::rules($unit, $term, 48);
        $pauses = array_map(static fn (array $pair) => new Suspension(...$pair), $intervals);
        foreach ([$pauses, array_reverse($pauses), [...$pauses, ...$pauses]] as $list) {
            $request = new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules, suspensiones: $list);
            $before = serialize($request);
            $result = $calculator->calcular($request);
            self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
            self::assertSame(['SUSPENSION_NO_PREVISTA'], array_column($result->avisos, 'codigo'));
            self::assertFalse($result->conCierrePendiente);
            self::assertSame($before, serialize($request));
        }
    }

    public function testAStartOutsideTheInitialTermBecomesValidOnlyWithExtension(): void
    {
        $pause = new Suspension('2026-10-10T11:00:00-05:00', '2026-10-10T12:00:00-05:00');
        $calculator = new DeadlineCalculator();
        $rules = self::rules('H', 24, 48);
        $result = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules, ampliarPlazo: true, suspensiones: [$pause]));
        self::assertSame('2026-10-11T11:00:00.000000-05:00', $result->toArray()['fechaMaximaRespuesta']);
        $this->expectException(InvalidCalculationRequest::class);
        $this->expectExceptionMessage('El inicio de la suspensión supera el vencimiento');
        $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules, suspensiones: [$pause]));
    }

    public static function invalidStartCases(): iterable
    {
        yield ['DH', 5, 10, false, '2026-10-20T00:00:00-05:00'];
        yield ['DH', 5, 10, true, '2026-10-27T00:00:00-05:00'];
        yield ['DC', 5, 10, false, '2026-10-15T00:00:00-05:00'];
        yield ['DC', 5, 10, true, '2026-10-20T00:00:00-05:00'];
        yield ['H', 24, 48, false, '2026-10-10T10:00:00.000001-05:00'];
        yield ['H', 24, 48, true, '2026-10-11T10:00:00.000001-05:00'];
    }

    #[DataProvider('invalidStartCases')]
    public function testAStartOutsideTheApplicableTermIsAControlledError(string $unit, int $term, int $extended, bool $extend, string $start): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-12-01T12:00:00-05:00'));
        foreach ([$start, null] as $end) {
            try {
                (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules($unit, $term, $extended),
                    ampliarPlazo: $extend, suspensiones: [new Suspension($start, $end)]));
                self::fail('Se esperaba el error de inicio fuera de plazo.');
            } catch (InvalidCalculationRequest $error) {
                self::assertSame('SUSPENSION_INICIO_FUERA_DE_PLAZO', $error->code());
                self::assertSame(['suspensiones[0].inicio'], $error->toArray()['campos']);
                self::assertArrayHasKey('limite', $error->details());
            }
        }
    }

    #[DataProvider('invalidStartCases')]
    public function testAPreviousPauseDoesNotExpandTheAllowedStartRange(string $unit, int $term, int $extended, bool $extend, string $start): void
    {
        try {
            (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00',
                reglas: self::rules($unit, $term, $extended), ampliarPlazo: $extend, suspensiones: [
                    new Suspension('2026-10-09T11:00:00-05:00', '2026-11-01T10:00:00-05:00'),
                    new Suspension($start, $start),
                ]));
            self::fail('La primera pausa no debe habilitar un inicio fuera del plazo.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('SUSPENSION_INICIO_FUERA_DE_PLAZO', $error->code());
            self::assertSame(['suspensiones[1].inicio'], $error->toArray()['campos']);
        }
    }

    public function testInclusiveBusinessStartStillSuspendsOnTheFollowingBusinessDay(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00',
            reglas: self::rules('DH', 5, 10, ['inicio' => 'MISMO_DIA', 'horaCierre' => '16:00:00', 'permiteSuspension' => true]),
            suspensiones: [new Suspension('2026-10-09T11:00:00-05:00', '2026-10-13T15:00:00-05:00')]));
        self::assertSame('2026-10-16T16:00:00.000000-05:00', $result->toArray()['fechaVencimientoInicial']);
        self::assertSame('2026-10-19T16:00:00.000000-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertSame([], $result->avisos);
    }

    public static function exactHourlySuspensions(): iterable
    {
        yield 'hora repetida por cambio horario' => ['2026-10-31T12:00:00-04:00', '2026-11-01T01:30:00-04:00',
            '2026-11-01T01:30:00-05:00', 'America/New_York', '2026-11-01T12:00:00.000000-05:00'];
        yield 'fracción cruza un segundo' => ['2026-10-09T10:00:00.900000-05:00', '2026-10-09T12:00:00.900000-05:00',
            '2026-10-09T12:00:01.100000-05:00', 'America/Bogota', '2026-10-10T10:00:01.100000-05:00'];
    }

    #[DataProvider('exactHourlySuspensions')]
    public function testHourlySuspensionsPreserveElapsedTimeAndMicroseconds(string $filing, string $start, string $end, string $zone, string $expected): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest($filing,
            reglas: self::rules('H', 24, 48, ['zonaHoraria' => $zone]), suspensiones: [new Suspension($start, $end)]));
        self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
        self::assertFalse($result->conCierrePendiente);
    }

    public function testTheExactDeadlineIsAnAllowedStartWithNoRemainingTime(): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules('H', 24, 48),
            suspensiones: [new Suspension('2026-10-10T10:00:00-05:00', '2026-10-15T10:00:00-05:00')]));
        self::assertEquals($result->fechaVencimientoInicial, $result->fechaMaximaRespuesta);
    }

    public static function openCases(): iterable
    {
        yield 'A10–A12 y A18' => ['DH', 15, 30, '2026-10-19T09:00:00-05:00', [
            ['2026-10-22T12:00:00-05:00', '2026-11-06T23:59:59.999999-05:00'],
            ['2026-10-26T15:00:00-05:00', '2026-11-10T23:59:59.999999-05:00'],
        ]];
        yield 'DC cambia al consultar otro día' => ['DC', 5, 10, '2026-10-11T10:00:00-05:00', [
            ['2026-10-15T10:00:00-05:00', '2026-10-18T23:59:59.999999-05:00'],
            ['2026-10-16T10:00:00-05:00', '2026-10-19T23:59:59.999999-05:00'],
        ]];
        yield 'H cambia con precisión de microsegundos' => ['H', 24, 48, '2026-10-09T12:00:00-05:00', [
            ['2026-10-11T12:00:00-05:00', '2026-10-12T10:00:00.000000-05:00'],
            ['2026-10-11T12:00:00.000001-05:00', '2026-10-12T10:00:00.000001-05:00'],
        ]];
    }

    #[DataProvider('openCases')]
    public function testOpenSuspensionsEstimateAtQueryTimeWithoutChangingTheInput(string $unit, int $term, int $extended, string $start, array $queries): void
    {
        $request = new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules($unit, $term, $extended), suspensiones: [new Suspension($start)]);
        $before = serialize($request);
        $calculator = new DeadlineCalculator();
        $results = [];
        foreach ($queries as [$now, $expected]) {
            CarbonImmutable::setTestNow(new CarbonImmutable($now));
            $result = $calculator->calcular($request);
            $results[] = $result;
            self::assertTrue($result->conCierrePendiente);
            self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
            self::assertEquals(new CarbonImmutable($now), $result->fechaConsulta);
            self::assertSame(['SUSPENSION_NO_PREVISTA', 'SUSPENSION_ABIERTA'], array_column($result->avisos, 'codigo'));
            $closed = $calculator->calcular(new CalculationRequest($request->fechaRadicacion, reglas: $request->reglas,
                suspensiones: [new Suspension($start, $result->fechaConsulta)]));
            self::assertEquals($closed->fechaMaximaRespuesta, $result->fechaMaximaRespuesta);
            self::assertEquals($closed->fechaVencimientoInicial, $result->fechaVencimientoInicial);
            self::assertFalse($closed->conCierrePendiente);
            self::assertSame(['SUSPENSION_NO_PREVISTA'], array_column($closed->avisos, 'codigo'));
        }
        self::assertNull($request->suspensiones[0]->fin);
        self::assertSame($before, serialize($request));
        self::assertSame($queries[0][1], $results[0]->toArray()['fechaMaximaRespuesta']);
        self::assertEquals($results[0]->fechaVencimientoInicial, $results[1]->fechaVencimientoInicial);
    }

    public static function openBalanceCases(): iterable
    {
        yield 'A36 aún no inicia el primer hábil' => ['DH', 15, '2026-10-09T11:00:00-05:00', '2026-10-09T12:00:00-05:00', '2026-11-03T23:59:59.999999-05:00'];
        yield 'DH consulta en fin de semana' => ['DH', 15, '2026-10-10T11:00:00-05:00', '2026-10-11T12:00:00-05:00', '2026-11-03T23:59:59.999999-05:00'];
        yield 'A38 último día antes del cierre' => ['DH', 15, '2026-11-03T09:00:00-05:00', '2026-11-03T10:00:00-05:00', '2026-11-03T23:59:59.999999-05:00'];
        yield 'A38 consulta posterior al vencimiento' => ['DH', 15, '2026-11-03T09:00:00-05:00', '2026-11-10T10:00:00-05:00', '2026-11-03T23:59:59.999999-05:00'];
        yield 'DC abre y consulta el mismo día' => ['DC', 5, '2026-10-09T11:00:00-05:00', '2026-10-09T12:00:00-05:00', '2026-10-14T23:59:59.999999-05:00'];
        yield 'DC abre el último día' => ['DC', 5, '2026-10-14T09:00:00-05:00', '2026-10-15T10:00:00-05:00', '2026-10-14T23:59:59.999999-05:00'];
        yield 'H consulta en el instante de apertura' => ['H', 24, '2026-10-09T12:00:00-05:00', '2026-10-09T12:00:00-05:00', '2026-10-10T10:00:00.000000-05:00'];
        yield 'H abre exactamente al agotar el plazo' => ['H', 24, '2026-10-10T10:00:00-05:00', '2026-10-11T10:00:00-05:00', '2026-10-10T10:00:00.000000-05:00'];
    }

    #[DataProvider('openBalanceCases')]
    public function testAnyOpenSuspensionReportsPendingClosureRegardlessOfRemainingTime(string $unit, int $term, string $start, string $now, string $expected): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable($now));
        $request = new CalculationRequest('2026-10-09T10:00:00-05:00',
            reglas: self::rules($unit, $term, 48, ['permiteSuspension' => true]), suspensiones: [new Suspension($start)]);
        $result = (new DeadlineCalculator())->calcular($request);
        self::assertTrue($result->conCierrePendiente);
        self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
        self::assertSame([['codigo' => 'SUSPENSION_ABIERTA', 'detalle' => 'Hay suspensiones sin fecha de cierre. El cálculo utiliza fechaConsulta como cierre supuesto.']], $result->avisos);
        self::assertNull($request->suspensiones[0]->fin);
    }

    public static function mixedOpenSuspensions(): iterable
    {
        yield 'DH' => ['DH', 5, 10, [
            ['2026-10-09T11:00:00-05:00', '2026-10-14T15:00:00-05:00'],
            ['2026-10-13T09:00:00-05:00', null], ['2026-10-15T09:00:00-05:00', null],
        ], '2026-10-20T12:00:00-05:00', '2026-11-04T23:59:59.999999-05:00'];
        yield 'DC' => ['DC', 5, 10, [
            ['2026-10-09T11:00:00-05:00', '2026-10-11T15:00:00-05:00'],
            ['2026-10-10T09:00:00-05:00', null], ['2026-10-12T09:00:00-05:00', null],
        ], '2026-10-15T12:00:00-05:00', '2026-10-25T23:59:59.999999-05:00'];
        yield 'H' => ['H', 24, 48, [
            ['2026-10-09T11:00:00-05:00', '2026-10-09T15:00:00-05:00'],
            ['2026-10-09T12:00:00-05:00', null], ['2026-10-09T14:00:00-05:00', null],
        ], '2026-10-09T18:00:00-05:00', '2026-10-11T17:00:00.000000-05:00'];
    }

    #[DataProvider('mixedOpenSuspensions')]
    public function testSeveralOpenAndClosedSuspensionsCombineWithExtension(string $unit, int $term, int $extended, array $intervals, string $now, string $expected): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable($now));
        $calculator = new DeadlineCalculator();
        $rules = self::rules($unit, $term, $extended);
        $pauses = array_map(static fn (array $pair) => new Suspension(...$pair), $intervals);
        foreach ([$pauses, array_reverse($pauses), [...$pauses, ...$pauses]] as $list) {
            $request = new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules, ampliarPlazo: true, suspensiones: $list);
            $before = serialize($request);
            $result = $calculator->calcular($request);
            self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
            self::assertTrue($result->conCierrePendiente);
            self::assertSame(['AMPLIACION_NO_PREVISTA', 'SUSPENSION_NO_PREVISTA', 'SUSPENSION_ABIERTA'], array_column($result->avisos, 'codigo'));
            self::assertSame($before, serialize($request));
        }
        $partlyClosed = [$pauses[0], $pauses[1], new Suspension($pauses[2]->inicio, $now)];
        $partial = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules, ampliarPlazo: true, suspensiones: $partlyClosed));
        self::assertTrue($partial->conCierrePendiente);
        self::assertSame($expected, $partial->toArray()['fechaMaximaRespuesta']);
        $closed = array_map(static fn (Suspension $pause) => new Suspension($pause->inicio, $pause->fin ?? $now), $pauses);
        $final = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules, ampliarPlazo: true, suspensiones: $closed));
        self::assertSame($expected, $final->toArray()['fechaMaximaRespuesta']);
        self::assertFalse($final->conCierrePendiente);
        self::assertSame(['AMPLIACION_NO_PREVISTA', 'SUSPENSION_NO_PREVISTA'], array_column($final->avisos, 'codigo'));
    }

    public function testAClosedPauseCanLeaveTimeForAnOpenPauseOnTheOriginalLastDay(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-20T12:00:00-05:00'));
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules(), suspensiones: [
            new Suspension('2026-10-09T11:00:00-05:00', '2026-10-13T15:00:00-05:00'),
            new Suspension('2026-10-19T09:00:00-05:00'),
        ]));
        self::assertSame('2026-10-19T23:59:59.999999-05:00', $result->toArray()['fechaVencimientoInicial']);
        self::assertSame('2026-10-21T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertTrue($result->conCierrePendiente);
    }

    public function testAllOpenSuspensionsUseOneClockReadingAcrossMidnight(): void
    {
        $request = new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules('DC'), suspensiones: [
            new Suspension('2026-10-09T12:00:00-05:00'), new Suspension('2026-10-10T12:00:00-05:00'),
        ]);
        $instant = new CarbonImmutable('2026-10-12T23:59:59.999999-05:00');
        $next = $instant->addMicrosecond();
        $reads = 0;
        CarbonImmutable::setTestNow(static function () use (&$reads, $instant, $next): CarbonImmutable {
            return ++$reads === 1 ? $instant : $next;
        });
        $result = (new DeadlineCalculator())->calcular($request);
        self::assertSame(1, $reads);
        self::assertSame('2026-10-12T23:59:59.999999-05:00', $result->toArray()['fechaConsulta']);
        self::assertSame('2026-10-17T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertTrue($result->conCierrePendiente);
    }

    public function testAnOpenPauseAfterTheLastCountedDayStillReportsPendingClosure(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-15T10:00:00-05:00'));
        $rules = self::rules('DC', 3, 6, ['ajustarVencimientoAHabil' => true, 'calendario' => 'COLOMBIA']);
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: $rules,
            suspensiones: [new Suspension('2026-10-12T10:00:00-05:00')]));
        self::assertSame('2026-10-13T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertTrue($result->conCierrePendiente);
        self::assertSame(['SUSPENSION_NO_PREVISTA', 'SUSPENSION_ABIERTA'], array_column($result->avisos, 'codigo'));
    }

    public function testUnrepresentableDateObjectsAlsoProduceControlledErrors(): void
    {
        try {
            (new DeadlineCalculator())->calcular(new CalculationRequest(new \DateTimeImmutable('+10000-01-01T10:00:00Z'), reglas: self::rules()));
            self::fail('Se esperaba un error de representación.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('FECHA_RESULTADO_FUERA_DE_RANGO', $error->code());
        }
    }

    #[DataProvider('units')]
    public function testAQueryBeforeAnOpenSuspensionRequiresAnExplicitEnd(string $unit): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-09T11:00:00-05:00'));
        try {
            (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules($unit, 24, 48),
                suspensiones: [new Suspension('2026-10-09T12:00:00-05:00')]));
            self::fail('No se debe usar un cierre anterior al inicio.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('SUSPENSION_INICIO_POSTERIOR_A_CONSULTA', $error->code());
            self::assertSame(['suspensiones[0].inicio', 'suspensiones[0].fin'], $error->toArray()['campos']);
        }
    }

    public function testHoursAcrossDstAreElapsedTimeWhileCalendarDaysAreCivilDates(): void
    {
        $calculator = new DeadlineCalculator();
        $hours = $calculator->calcular(new CalculationRequest('2026-03-07T12:00:00-05:00', reglas: self::rules('H', 24, 48, ['zonaHoraria' => 'America/New_York'])));
        self::assertSame('2026-03-08T13:00:00.000000-04:00', $hours->toArray()['fechaMaximaRespuesta']);
        $days = $calculator->calcular(new CalculationRequest('2026-03-07T12:00:00-05:00', reglas: self::rules('DC', 1, 2, ['zonaHoraria' => 'America/New_York'])));
        self::assertSame('2026-03-08T23:59:59.999999-04:00', $days->toArray()['fechaMaximaRespuesta']);
    }

    public function testCustomStartClosingTimeAndCalendarAdjustmentAreApplied(): void
    {
        $calculator = new DeadlineCalculator();
        $sameDay = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules('DC', 1, 2,
            ['inicio' => 'MISMO_DIA', 'horaCierre' => '16:00:00'])));
        self::assertSame('2026-10-09T16:00:00.000000-05:00', $sameDay->toArray()['fechaMaximaRespuesta']);
        $adjusted = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules('DC', 3, 6,
            ['ajustarVencimientoAHabil' => true, 'calendario' => 'COLOMBIA'])));
        self::assertSame('2026-10-13T23:59:59.999999-05:00', $adjusted->toArray()['fechaMaximaRespuesta']);
    }

    public static function calendarEventDates(): iterable
    {
        yield 'laborable' => ['2026-10-09'];
        yield 'sábado' => ['2026-10-10'];
        yield 'domingo' => ['2026-10-11'];
        yield 'festivo' => ['2026-10-12'];
    }

    #[DataProvider('calendarEventDates')]
    public function testFilingSuspensionAndResumptionUseTheSameNextBusinessDay(string $event): void
    {
        $calculator = new DeadlineCalculator();
        $rules = self::rules('DH', 1, 2);
        $request = new CalculationRequest($event . 'T10:00:00-05:00', reglas: $rules,
            suspensiones: [new Suspension($event . 'T11:00:00-05:00', '2026-10-13T10:00:00-05:00')]);
        $result = $calculator->calcular($request);
        self::assertSame('2026-10-13T23:59:59.999999-05:00', $result->toArray()['fechaVencimientoInicial']);
        self::assertSame('2026-10-14T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);

        $resumed = $calculator->calcular(new CalculationRequest('2026-10-08T10:00:00-05:00', reglas: self::rules('DH', 3, 6),
            suspensiones: [new Suspension('2026-10-08T11:00:00-05:00', $event . 'T12:00:00-05:00')]));
        self::assertSame('2026-10-15T23:59:59.999999-05:00', $resumed->toArray()['fechaMaximaRespuesta']);
    }

    public function testBusinessDaysNeedNoWeekConfigurationThroughThePublicCalculator(): void
    {
        $calculator = new DeadlineCalculator();
        $request = new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules('DH', 3, 6));
        $result = $calculator->calcular($request);
        self::assertSame('2026-10-15T23:59:59.999999-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertArrayNotHasKey('diasHabiles', get_object_vars($request->reglas));
    }

    public function testTypeAndExplicitRulesProduceTheSameDates(): void
    {
        $calculator = new DeadlineCalculator();
        $type = RequestType::PETICION_GENERAL;
        $typed = $calculator->calcular(new CalculationRequest('1996-02-29T10:00:00-05:00', tipoSolicitud: $type, ampliarPlazo: true));
        $explicit = $calculator->calcular(new CalculationRequest('1996-02-29T15:00:00Z', reglas: $type->reglas(), ampliarPlazo: true));
        self::assertEquals($typed->fechaMaximaRespuesta, $explicit->fechaMaximaRespuesta);
        self::assertSame('1996-03-21', $typed->fechaVencimientoInicial->format('Y-m-d'));
    }

    #[DataProvider('units')]
    public function testUnrepresentableTermsProduceAnErrorInsteadOfOverflowOrEndlessIteration(string $unit): void
    {
        try {
            (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00', reglas: self::rules($unit, PHP_INT_MAX - 1, PHP_INT_MAX)));
            self::fail('Se esperaba un error de representación.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('FECHA_RESULTADO_FUERA_DE_RANGO', $error->code());
        }
    }
}
