<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Carbon\CarbonImmutable;
use DateTime;
use Error;
use InvalidArgumentException;
use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Input\Suspension;
use Micrositios\PqrDeadline\Output\DeadlineResult;
use Micrositios\PqrDeadline\Rules\DeadlineRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeadlineResultTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public static function calculatedResults(): iterable
    {
        $health = ['tipoSolicitud' => RequestType::RECLAMO_SALUD_VITAL];
        $initial = '2026-10-10T10:00:00.123456-05:00';
        $closed = [new Suspension('2026-10-09T12:00:00.123456-05:00', '2026-10-09T14:00:00.654321-05:00')];
        $open = [new Suspension('2026-10-09T12:00:00.123456-05:00')];

        yield 'vencimiento pasado sin avisos' => [$health, $initial, $initial, false, []];
        yield 'solo ampliación informativa' => [[...$health, 'ampliarPlazo' => true], $initial,
            '2026-10-11T10:00:00.123456-05:00', false, ['AMPLIACION_NO_PREVISTA']];
        yield 'solo suspensión informativa' => [[...$health, 'suspensiones' => $closed], $initial,
            '2026-10-10T12:00:00.654321-05:00', false, ['SUSPENSION_NO_PREVISTA']];
        yield 'ampliación y suspensión cerrada' => [[...$health, 'ampliarPlazo' => true, 'suspensiones' => $closed], $initial,
            '2026-10-11T12:00:00.654321-05:00', false, ['AMPLIACION_NO_PREVISTA', 'SUSPENSION_NO_PREVISTA']];
        yield 'abierta sin saldo pendiente' => [[...$health, 'suspensiones' => [new Suspension($initial)]], $initial,
            $initial, true, ['SUSPENSION_NO_PREVISTA', 'SUSPENSION_ABIERTA']];
        yield 'solo estimación de petición general' => [['tipoSolicitud' => RequestType::PETICION_GENERAL, 'suspensiones' => $open],
            '2026-11-03T23:59:59.999999-05:00', '2026-11-05T23:59:59.999999-05:00', true, ['SUSPENSION_ABIERTA']];
        yield 'suspensión informativa y estimación' => [[...$health, 'suspensiones' => $open], $initial,
            '2026-10-15T10:00:00.654321-05:00', true, ['SUSPENSION_NO_PREVISTA', 'SUSPENSION_ABIERTA']];
        yield 'ampliación informativa y estimación con reglas explícitas' => [[
            'reglas' => new DeadlineRules(24, 'H', 48, true, 'INMEDIATO', 'INMEDIATO', false, 'America/Bogota', permiteAmpliacion: false),
            'ampliarPlazo' => true, 'suspensiones' => $open,
        ], $initial, '2026-10-16T10:00:00.654321-05:00', true, ['AMPLIACION_NO_PREVISTA', 'SUSPENSION_ABIERTA']];
        yield 'los tres avisos' => [[...$health, 'ampliarPlazo' => true, 'suspensiones' => $open], $initial,
            '2026-10-16T10:00:00.654321-05:00', true, ['AMPLIACION_NO_PREVISTA', 'SUSPENSION_NO_PREVISTA', 'SUSPENSION_ABIERTA']];
    }

    #[DataProvider('calculatedResults')]
    public function testCalculatedResultExportsOnlyTheApprovedFieldsAndExplainsItsNotices(array $options, string $initial, string $maximum, bool $pendingClosure, array $codes): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-14T12:00:00.654321-05:00'));
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00.123456-05:00', ...$options));
        self::assertInstanceOf(DeadlineResult::class, $result);
        self::assertSame($codes, array_column($result->avisos, 'codigo'));
        foreach ($result->avisos as $notice) {
            self::assertSame(['codigo', 'detalle'], array_keys($notice));
            self::assertStringContainsString(match ($notice['codigo']) {
                'AMPLIACION_NO_PREVISTA' => 'permiteAmpliacion=false',
                'SUSPENSION_NO_PREVISTA' => 'permiteSuspension=false',
                'SUSPENSION_ABIERTA' => 'fechaConsulta',
            }, $notice['detalle']);
        }
        $expected = [
            'conCierrePendiente' => $pendingClosure,
            'fechaConsulta' => '2026-10-14T12:00:00.654321-05:00',
            'fechaVencimientoInicial' => $initial,
            'fechaMaximaRespuesta' => $maximum,
            'avisos' => $result->avisos,
        ];
        self::assertSame($expected, $result->toArray());
        self::assertSame($expected, $result->jsonSerialize());
        self::assertSame($expected, json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        foreach ([$result->fechaVencimientoInicial, $result->fechaMaximaRespuesta, $result->fechaConsulta] as $date) {
            self::assertInstanceOf(CarbonImmutable::class, $date);
            self::assertSame('America/Bogota', $date->getTimezone()->getName());
            $date->addDay();
        }
        $export = $result->toArray();
        $export['fechaMaximaRespuesta'] = 'cambiada';
        $export['conCierrePendiente'] = !$pendingClosure;
        if ($export['avisos'] !== []) {
            $export['avisos'][0]['detalle'] = 'Cambiado por el consumidor';
        }
        self::assertSame($expected, $result->toArray());
    }

    public function testCalculatedJsonPreservesTheOffsetOfEachInstantAcrossDaylightSaving(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-24T23:59:59.654321+02:00'));
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-24T18:30:00.123456+02:00',
            tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL, zonaHoraria: 'Europe/Madrid', ampliarPlazo: true));
        $json = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('2026-10-24T23:59:59.654321+02:00', $json['fechaConsulta']);
        self::assertSame('2026-10-25T17:30:00.123456+01:00', $json['fechaVencimientoInicial']);
        self::assertSame('2026-10-26T17:30:00.123456+01:00', $json['fechaMaximaRespuesta']);
        self::assertFalse($json['conCierrePendiente']);
        self::assertSame(['AMPLIACION_NO_PREVISTA'], array_column($json['avisos'], 'codigo'));
    }

    public function testALogicalErrorDoesNotBecomeANoticeOrReturnAPartialResult(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-14T12:00:00-05:00'));
        $calculator = new DeadlineCalculator();
        $result = null;
        try {
            $result = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00.123456-05:00',
                tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL, ampliarPlazo: true,
                suspensiones: [new Suspension('2026-10-11T10:00:00.123457-05:00')]));
            self::fail('El inicio fuera de plazo debe impedir el cálculo, aunque haya avisos informativos.');
        } catch (InvalidCalculationRequest $error) {
            self::assertNull($result);
            self::assertNotEmpty($error->getMessage());
            self::assertSame([
                'codigo' => 'SUSPENSION_INICIO_FUERA_DE_PLAZO',
                'mensaje' => $error->getMessage(),
                'campos' => ['suspensiones[0].inicio'],
                'detalles' => ['limite' => '2026-10-11T10:00:00.123456-05:00'],
            ], $error->toArray());
            self::assertSame($error->toArray(), json_decode(json_encode($error, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        }
        $valid = $calculator->calcular(new CalculationRequest('2026-10-09T10:00:00.123456-05:00', tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL));
        self::assertSame([], $valid->avisos);
        self::assertSame('2026-10-10T10:00:00.123456-05:00', $valid->toArray()['fechaMaximaRespuesta']);
    }

    public function testResultWithoutPendingClosureCopiesDatesAndSerializesExplicitFields(): void
    {
        $date = new DateTime('2026-02-05T23:59:59.123456-05:00');
        $result = new DeadlineResult($date, $date, $date);
        $date->modify('+1 year');

        self::assertSame([
            'conCierrePendiente' => false,
            'fechaConsulta' => '2026-02-05T23:59:59.123456-05:00',
            'fechaVencimientoInicial' => '2026-02-05T23:59:59.123456-05:00',
            'fechaMaximaRespuesta' => '2026-02-05T23:59:59.123456-05:00',
            'avisos' => [],
        ], json_decode(json_encode($result, JSON_THROW_ON_ERROR), true));
    }

    public function testResultWithPendingClosureOwnsItsDatesAndNoticeValues(): void
    {
        $date = '2026-02-05T23:59:59-05:00';
        $code = 'SUSPENSION_ABIERTA';
        $notice = ['codigo' => &$code, 'detalle' => 'Falta el cierre de una suspensión.'];
        $result = new DeadlineResult($date, $date, $date, conCierrePendiente: true, avisos: [&$notice]);
        $code = 'CAMBIADO';
        $notice['detalle'] = 'Cambiado';
        $export = $result->toArray();
        $export['avisos'][0]['detalle'] = 'Cambiado desde la salida';

        self::assertTrue($result->conCierrePendiente);
        self::assertSame($date, $result->fechaMaximaRespuesta->format('Y-m-d\TH:i:sP'));
        self::assertSame([
            'conCierrePendiente' => true,
            'fechaConsulta' => '2026-02-05T23:59:59.000000-05:00',
            'fechaVencimientoInicial' => '2026-02-05T23:59:59.000000-05:00',
            'fechaMaximaRespuesta' => '2026-02-05T23:59:59.000000-05:00',
            'avisos' => [['codigo' => 'SUSPENSION_ABIERTA', 'detalle' => 'Falta el cierre de una suspensión.']],
        ], json_decode(json_encode($result, JSON_THROW_ON_ERROR), true));
    }

    public static function invalidNotices(): iterable
    {
        yield 'asociativos' => [['aviso' => ['codigo' => 'CODIGO', 'detalle' => 'Texto']]];
        yield 'sin detalle' => [[['codigo' => 'CODIGO']]];
        yield 'objeto mutable' => [[new \stdClass()]];
    }

    #[DataProvider('invalidNotices')]
    public function testNoticesMustHaveTheDeclaredShape(array $notices): void
    {
        $this->expectException(InvalidArgumentException::class);
        $date = '2026-02-05T10:00:00Z';
        new DeadlineResult($date, $date, $date, avisos: $notices);
    }

    public function testReadonlyResultCannotBeChanged(): void
    {
        $date = '2026-02-05T10:00:00Z';
        $result = new DeadlineResult($date, $date, $date);
        $this->expectException(Error::class);
        $result->conCierrePendiente = true;
    }

    public function testErrorPayloadDoesNotKeepExternalReferences(): void
    {
        $field = 'fechaRadicacion';
        $value = '2026-02-30T10:00:00Z';
        $error = new InvalidCalculationRequest('FECHA_RADICACION_IMPOSIBLE', 'La fecha no existe.', [&$field], ['valorRecibido' => &$value]);
        $field = 'cambiado';
        $value = 'cambiado';
        $details = $error->details();
        $details['valorRecibido'] = 'cambiado desde la salida';

        self::assertSame(['fechaRadicacion'], $error->toArray()['campos']);
        self::assertSame(['valorRecibido' => '2026-02-30T10:00:00Z'], $error->details());
        self::assertSame('La fecha no existe.', $error->getMessage());
    }
}
