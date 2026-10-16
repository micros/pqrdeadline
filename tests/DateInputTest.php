<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Temporal\DateInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateInputTest extends TestCase
{
    public static function invalidDates(): iterable
    {
        yield 'regional' => ['09/10/2026 10:00', 'FORMATO_INVALIDO'];
        yield 'relativa' => ['tomorrow', 'FORMATO_INVALIDO'];
        yield 'sin hora' => ['2026-10-09', 'FORMATO_INVALIDO'];
        yield 'sin segundos' => ['2026-10-09T10:00Z', 'FORMATO_INVALIDO'];
        yield 'precisión excesiva' => ['2026-10-09T10:00:00.1234567Z', 'FORMATO_INVALIDO'];
        yield 'salto de línea' => ["2026-10-09T10:00:00Z\n", 'FORMATO_INVALIDO'];
        yield 'febrero imposible' => ['2026-02-30T10:00:00Z', 'IMPOSIBLE'];
        yield 'no bisiesto' => ['2025-02-29T10:00:00Z', 'IMPOSIBLE'];
        yield 'mes imposible' => ['2026-13-01T10:00:00Z', 'IMPOSIBLE'];
        yield 'hora imposible' => ['2026-01-01T24:00:00Z', 'IMPOSIBLE'];
        yield 'minuto imposible' => ['2026-01-01T10:60:00Z', 'IMPOSIBLE'];
        yield 'segundo imposible' => ['2026-01-01T10:00:60Z', 'IMPOSIBLE'];
        yield 'offset imposible' => ['2026-01-01T10:00:00+14:01', 'IMPOSIBLE'];
        yield 'offset minuto imposible' => ['2026-01-01T10:00:00+05:60', 'IMPOSIBLE'];
        yield 'offset desconocido' => ['2026-01-01T10:00:00-00:00', 'AMBIGUA'];
        yield 'zona ausente' => ['2026-01-01T10:00:00', 'ZONA_REQUERIDA'];
    }

    #[DataProvider('invalidDates')]
    public function testInvalidDatesProduceStructuredErrors(string $value, string $suffix): void
    {
        try {
            if ($suffix === 'ZONA_REQUERIDA') {
                DateInput::parse($value, 'fechaRadicacion');
            } else {
                new CalculationRequest($value, tipoSolicitud: RequestType::PETICION_GENERAL);
            }
            self::fail('Se esperaba un error de fecha.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('FECHA_RADICACION_' . $suffix, $error->code());
            self::assertSame(['fechaRadicacion'], $error->toArray()['campos']);
            self::assertSame(['valorRecibido' => $value], $error->details());
            self::assertSame($error->toArray(), json_decode(json_encode($error, JSON_THROW_ON_ERROR), true));
        }
    }

    public function testOldDatesLeapDaysFractionsAndEquivalentOffsetsAreAccepted(): void
    {
        $old = new CalculationRequest('1996-02-29T10:00:00.12-05:00', tipoSolicitud: RequestType::PETICION_GENERAL);
        $utc = new CalculationRequest('1996-02-29T15:00:00.120000Z', tipoSolicitud: RequestType::PETICION_GENERAL);
        self::assertSame('1996-02-29T10:00:00.120000-05:00', $old->fechaRadicacion->format('Y-m-d\TH:i:s.uP'));
        self::assertTrue($old->fechaRadicacion->equalTo($utc->fechaRadicacion));
    }

    public function testAnExplicitZoneResolvesLocalDatesWithoutUsingTheServerZone(): void
    {
        $previous = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Apia');
            $request = new CalculationRequest('2026-01-15T10:00:00', tipoSolicitud: RequestType::PETICION_GENERAL, zonaHoraria: 'America/Bogota');
            self::assertSame('2026-01-15T10:00:00.000000-05:00', $request->fechaRadicacion->format('Y-m-d\TH:i:s.uP'));
            $converted = new CalculationRequest('2026-01-15T15:00:00Z', tipoSolicitud: RequestType::PETICION_GENERAL, zonaHoraria: 'America/Bogota');
            self::assertTrue($request->fechaRadicacion->equalTo($converted->fechaRadicacion));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public static function localDateErrors(): iterable
    {
        yield 'zona desconocida' => ['2026-01-01T10:00:00Z', 'Zona/Inventada', 'ZONA_HORARIA_DESCONOCIDA'];
        yield 'hora omitida' => ['2026-03-08T02:30:00', 'America/New_York', 'FECHA_RADICACION_IMPOSIBLE'];
        yield 'hora repetida' => ['2026-11-01T01:30:00', 'America/New_York', 'FECHA_RADICACION_AMBIGUA'];
    }

    #[DataProvider('localDateErrors')]
    public function testZoneAndLocalTimeErrorsAreControlled(string $value, string $zone, string $code): void
    {
        try {
            new CalculationRequest($value, tipoSolicitud: RequestType::PETICION_GENERAL, zonaHoraria: $zone);
            self::fail('Se esperaba un error temporal.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame($code, $error->code());
        }
    }

    public function testAnExplicitOffsetDistinguishesRepeatedLocalHours(): void
    {
        $first = DateInput::parse('2026-11-01T01:30:00-04:00', 'fechaRadicacion', 'America/New_York');
        $second = DateInput::parse('2026-11-01T01:30:00-05:00', 'fechaRadicacion', 'America/New_York');
        self::assertSame(3600, $second->getTimestamp() - $first->getTimestamp());
    }
}
