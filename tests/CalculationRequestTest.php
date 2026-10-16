<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Carbon\CarbonImmutable;
use DateTime;
use Error;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Input\Suspension;
use Micrositios\PqrDeadline\Rules\DeadlineRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalculationRequestTest extends TestCase
{
    public function testDatesAndSuspensionListsAreIndependentOfMutableInputs(): void
    {
        $date = new DateTime('2010-01-15T10:00:00.123456-05:00');
        $suspension = new Suspension($date, $date);
        $list = [&$suspension];
        $request = new CalculationRequest($date, tipoSolicitud: RequestType::PETICION_GENERAL, suspensiones: $list);
        $date->modify('+10 days');
        $suspension = new Suspension('2010-02-01T10:00:00-05:00');
        $list[] = $suspension;

        foreach ([$request->fechaRadicacion,
            $request->suspensiones[0]->inicio, $request->suspensiones[0]->fin] as $copy) {
            self::assertInstanceOf(CarbonImmutable::class, $copy);
            self::assertSame('2010-01-15T10:00:00.123456-05:00', $copy->format('Y-m-d\TH:i:s.uP'));
        }
        self::assertCount(1, $request->suspensiones);
        $request->fechaRadicacion->addDay();
        self::assertSame('2010-01-15', $request->fechaRadicacion->format('Y-m-d'));
    }

    public static function invalidRequests(): iterable
    {
        yield 'suspensión anterior' => [['suspensiones' => [new Suspension('2026-01-15T14:59:59Z')]], 'SUSPENSION_INICIO_ANTERIOR_A_RADICACION'];
        yield 'elemento inválido' => [['suspensiones' => ['2026-01-16']], 'SUSPENSION_INVALIDA'];
        yield 'lista asociativa' => [['suspensiones' => ['pausa' => new Suspension('2026-01-16T10:00:00Z')]], 'SUSPENSIONES_FORMATO_INVALIDO'];
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidEventsProduceTheExpectedCode(array $options, string $code): void
    {
        try {
            new CalculationRequest('2026-01-15T10:00:00-05:00', ...$options, tipoSolicitud: RequestType::PETICION_GENERAL);
            self::fail('Se esperaba un error del evento.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame($code, $error->code());
            self::assertNotEmpty($error->toArray()['campos']);
        }
    }

    public static function invalidSuspensions(): iterable
    {
        yield 'inicio mal formado' => ['ayer', null, 'FECHA_SUSPENSION_INICIO_FORMATO_INVALIDO'];
        yield 'fin imposible' => ['2026-01-15T10:00:00Z', '2026-02-30T10:00:00Z', 'FECHA_SUSPENSION_FIN_IMPOSIBLE'];
        yield 'fin anterior' => ['2026-01-15T10:00:00-05:00', '2026-01-15T14:59:59Z', 'SUSPENSION_FIN_ANTERIOR_A_INICIO'];
    }

    #[DataProvider('invalidSuspensions')]
    public function testInvalidSuspensionsAreRejected(string $start, ?string $end, string $code): void
    {
        try {
            new Suspension($start, $end);
            self::fail('Se esperaba un error de suspensión.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame($code, $error->code());
        }
    }

    public function testZeroDurationIsAccepted(): void
    {
        $suspension = new Suspension('2010-01-15T10:00:00-05:00', '2010-01-15T15:00:00Z');
        $request = new CalculationRequest('2010-01-15T15:00:00Z', tipoSolicitud: RequestType::PETICION_GENERAL, suspensiones: [$suspension]);
        self::assertTrue($suspension->inicio->equalTo($suspension->fin));
        self::assertSame($suspension, $request->suspensiones[0]);
    }

    public static function extensionFlags(): iterable
    {
        yield 'omitida' => [[], false];
        yield 'desactivada' => [['ampliarPlazo' => false], false];
        yield 'activada' => [['ampliarPlazo' => true], true];
    }

    #[DataProvider('extensionFlags')]
    public function testExtensionRequiresOnlyTheFlagAndPreservesTheFilingDate(array $options, bool $expected): void
    {
        $request = new CalculationRequest('2010-01-15T10:00:00-05:00', ...$options, tipoSolicitud: RequestType::PETICION_GENERAL);

        self::assertSame($expected, $request->ampliarPlazo);
        self::assertSame('2010-01-15T10:00:00.000000-05:00', $request->fechaRadicacion->format('Y-m-d\TH:i:s.uP'));
    }

    public function testAnOpenSuspensionIsClosedOnlyWithTheSuppliedEnd(): void
    {
        $open = new Suspension('2010-01-20T10:00:00-05:00');
        $original = new CalculationRequest('2010-01-15T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL, suspensiones: [$open]);
        $closed = new Suspension(inicio: $open->inicio, fin: '2010-01-30T16:00:00-05:00');
        $simulation = new CalculationRequest('2010-01-15T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL, suspensiones: [$closed]);

        self::assertNull($original->suspensiones[0]->fin);
        self::assertSame('2010-01-30T16:00:00.000000-05:00', $simulation->suspensiones[0]->fin->format('Y-m-d\TH:i:s.uP'));
        self::assertTrue($original->suspensiones[0]->inicio->equalTo($simulation->suspensiones[0]->inicio));
    }

    public function testFutureEventsDoNotRequireAnAdditionalDate(): void
    {
        $request = new CalculationRequest('2080-01-15T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL,
            suspensiones: [new Suspension('2080-01-20T10:00:00-05:00')]);

        self::assertSame('2080-01-15', $request->fechaRadicacion->format('Y-m-d'));
        self::assertNull($request->suspensiones[0]->fin);
    }

    public static function removedArguments(): iterable
    {
        yield 'fecha de referencia' => ['fechaReferencia'];
        yield 'fecha de ampliación' => ['fechaAmpliacion'];
    }

    #[DataProvider('removedArguments')]
    public function testRemovedArgumentIsRejectedInsteadOfSilentlyIgnored(string $field): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown named parameter $' . $field);
        new CalculationRequest('2026-01-15T10:00:00Z', ...[$field => '2026-01-20T10:00:00Z'], tipoSolicitud: RequestType::PETICION_GENERAL);
    }

    public function testReadonlyRequestCannotBeChanged(): void
    {
        $request = new CalculationRequest('2026-01-15T10:00:00Z', tipoSolicitud: RequestType::PETICION_GENERAL);
        $this->expectException(Error::class);
        $request->ampliarPlazo = true;
    }

    public static function invalidModalities(): iterable
    {
        yield [[], 'TIPO_O_REGLAS_REQUERIDOS'];
        yield [['tipoSolicitud' => RequestType::PETICION_GENERAL, 'reglas' => RequestType::PETICION_GENERAL->reglas()], 'TIPO_Y_REGLAS_MUTUAMENTE_EXCLUYENTES'];
        yield [['reglas' => self::calendarRules(), 'zonaHoraria' => 'America/New_York'], 'CONFIGURACION_INCOMPATIBLE'];
    }

    #[DataProvider('invalidModalities')]
    public function testInvalidModalitiesAreRejected(array $options, string $code): void
    {
        try {
            new CalculationRequest('2026-01-15T10:00:00Z', ...$options);
            self::fail('Se esperaba un error de solicitud.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame($code, $error->code());
            self::assertNotEmpty($error->toArray()['campos']);
        }
    }

    public function testExplicitRulesDoNotInheritGeneralPetitionDefaults(): void
    {
        $rules = self::calendarRules();
        $request = new CalculationRequest('2026-01-15T10:00:00', reglas: $rules);
        self::assertNull($request->tipoSolicitud);
        self::assertSame($rules, $request->reglas);
        self::assertSame('2026-01-15T10:00:00.000000+01:00', $request->fechaRadicacion->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(20, $request->reglas->plazoAmpliado);
        self::assertFalse($request->reglas->permiteSuspension);
    }

    public function testTypeAndEquivalentExplicitRulesResolveToTheSameInput(): void
    {
        $rules = new DeadlineRules(
            plazo: 15, unidad: 'DH', plazoAmpliado: 30, permiteSuspension: true,
            inicio: 'SIGUIENTE_HABIL', cierre: 'FIN_DEL_DIA', ajustarVencimientoAHabil: true,
            zonaHoraria: 'America/Bogota', calendario: 'COLOMBIA',
            horaCierre: '23:59:59.999999',
        );
        $typed = new CalculationRequest('1996-02-29T15:00:00Z', tipoSolicitud: RequestType::PETICION_GENERAL);
        $explicit = new CalculationRequest('1996-02-29T10:00:00', reglas: $rules);
        self::assertEquals($explicit->reglas, $typed->reglas);
        self::assertTrue($explicit->fechaRadicacion->equalTo($typed->fechaRadicacion));
    }

    public function testExtensionFlagDoesNotModifyTheOriginalRules(): void
    {
        $type = RequestType::RECLAMO_DATOS_PERSONALES;
        $first = new CalculationRequest('2010-01-15T10:00:00Z', tipoSolicitud: $type, ampliarPlazo: true);
        $second = new CalculationRequest('2010-01-15T10:00:00Z', tipoSolicitud: $type, ampliarPlazo: true);
        $original = new CalculationRequest('2010-01-15T10:00:00Z', tipoSolicitud: $type);
        self::assertTrue($first->ampliarPlazo);
        self::assertFalse($original->ampliarPlazo);
        self::assertSame(15, $first->reglas->plazo);
        self::assertSame(23, $first->reglas->plazoAmpliado);
        self::assertEquals($first->reglas, $second->reglas);
        self::assertEquals($first->reglas, $original->reglas);
    }

    public function testClientEventsAreAcceptedDespiteInformationalFlags(): void
    {
        $pause = new Suspension('2026-01-15T11:00:00Z', '2026-01-16T11:00:00Z');
        foreach ([RequestType::RECLAMO_SALUD_VITAL, RequestType::PQR_POSTAL,
            RequestType::PQR_TELECOMUNICACIONES, RequestType::RECLAMO_DATOS_PERSONALES,
            RequestType::PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS] as $type) {
            $request = new CalculationRequest('2026-01-15T10:00:00Z', tipoSolicitud: $type,
                ampliarPlazo: true, suspensiones: [$pause]);
            self::assertTrue($request->ampliarPlazo);
            self::assertFalse($request->reglas->permiteSuspension);
            self::assertSame([$pause], $request->suspensiones);
        }
        $request = new CalculationRequest('2026-01-15T10:00:00Z', reglas: self::calendarRules(),
            ampliarPlazo: true, suspensiones: [$pause]);
        self::assertSame([$pause], $request->suspensiones);
    }

    private static function calendarRules(): DeadlineRules
    {
        return new DeadlineRules(10, 'DC', 20, false, 'DIA_SIGUIENTE', 'FIN_DEL_DIA', false, 'Europe/Madrid', horaCierre: '18:00:00');
    }
}
