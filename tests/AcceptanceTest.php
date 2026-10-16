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

final class AcceptanceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function testExecutableExamplesMatchTheApprovedScenarios(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-22T12:00:00-05:00'));
        ob_start();
        try {
            (static function (): void { require __DIR__ . '/../examples/uso.php'; })();
            $json = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $results = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['por_tipo', 'reglas_explicitas', 'horas', 'ampliacion', 'suspension_cerrada',
            'suspension_abierta', 'escenario_con_cierre', 'modalidades_excluyentes', 'fecha_imposible'], array_keys($results));
        foreach ([
            'por_tipo' => '2026-11-03T23:59:59.999999-05:00',
            'reglas_explicitas' => '2026-10-19T23:59:59.999999-05:00',
            'horas' => '2026-10-09T18:00:00.000000-05:00',
            'ampliacion' => '2026-11-25T23:59:59.999999-05:00',
            'suspension_cerrada' => '2026-11-10T23:59:59.999999-05:00',
            'suspension_abierta' => '2026-11-13T23:59:59.999999-05:00',
            'escenario_con_cierre' => '2026-11-06T23:59:59.999999-05:00',
        ] as $name => $expected) {
            self::assertSame($expected, $results[$name]['fechaMaximaRespuesta']);
            self::assertSame($name === 'suspension_abierta', $results[$name]['conCierrePendiente']);
            self::assertSame('2026-10-22T12:00:00.000000-05:00', $results[$name]['fechaConsulta']);
        }
        self::assertSame(['SUSPENSION_ABIERTA'], array_column($results['suspension_abierta']['avisos'], 'codigo'));
        self::assertSame('TIPO_Y_REGLAS_MUTUAMENTE_EXCLUYENTES', $results['modalidades_excluyentes']['codigo']);
        self::assertSame('FECHA_RADICACION_IMPOSIBLE', $results['fecha_imposible']['codigo']);
    }

    public function testExamplesRunFromAnotherDirectoryWithoutNetworkFunctions(): void
    {
        $process = proc_open([PHP_BINARY, '-d', 'allow_url_fopen=0', '-d',
            'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client',
            '-d', 'date.timezone=Pacific/Apia', dirname(__DIR__) . '/examples/uso.php'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, sys_get_temp_dir());
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        $results = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(9, $results);
        self::assertSame('2026-11-03T23:59:59.999999-05:00', $results['por_tipo']['fechaMaximaRespuesta']);
        self::assertTrue($results['suspension_abierta']['conCierrePendiente']);
        self::assertSame('FECHA_RADICACION_IMPOSIBLE', $results['fecha_imposible']['codigo']);
    }

    public function testInterleavedRequestsAndCalculatorsKeepTheirOwnRulesAndDates(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2026-10-22T17:00:00Z'));
        $a = new CalculationRequest('2026-10-09T10:00:00-05:00', tipoSolicitud: RequestType::PETICION_GENERAL,
            ampliarPlazo: true, suspensiones: [new Suspension('2026-10-19T09:00:00-05:00', '2026-10-26T15:00:00-05:00')]);
        $b = new CalculationRequest('2026-10-09T15:00:00Z', reglas: new DeadlineRules(10, 'DC', 20, false,
            'DIA_SIGUIENTE', 'FIN_DEL_DIA', false, 'Europe/Madrid', horaCierre: '17:00:00'));
        $c = new CalculationRequest('2026-10-09T15:00:00Z', tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL_MENOR, zonaHoraria: 'Pacific/Auckland');
        $before = serialize([$a, $b, $c]);
        $calculator = new DeadlineCalculator();
        $serverZone = date_default_timezone_get();
        try {
            foreach (['UTC', 'Europe/Madrid', 'Pacific/Apia'] as $zone) {
                date_default_timezone_set($zone);
                $first = $calculator->calcular($a);
                self::assertSame('2026-12-02T23:59:59.999999-05:00', $first->toArray()['fechaMaximaRespuesta']);
                self::assertSame('2026-10-19T17:00:00.000000+02:00', $calculator->calcular($b)->toArray()['fechaMaximaRespuesta']);
                self::assertSame('2026-10-10T12:00:00.000000+13:00', $calculator->calcular($c)->toArray()['fechaMaximaRespuesta']);
                self::assertSame($first->toArray(), $calculator->calcular($a)->toArray());
                self::assertSame($first->toArray(), (new DeadlineCalculator())->calcular($a)->toArray());
            }
        } finally {
            date_default_timezone_set($serverZone);
        }
        self::assertSame($before, serialize([$a, $b, $c]));
    }

    public function testTenThousandOverlappingSuspensionsAreNotTruncatedOrDoubleCounted(): void
    {
        $start = new CarbonImmutable('2026-10-09T12:00:00-05:00');
        $pauses = [];
        for ($i = 0; $i < 10000; ++$i) {
            $pauses[] = new Suspension($start->addSeconds($i), $start->addSeconds($i + 2));
        }
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest('2026-10-09T10:00:00-05:00',
            tipoSolicitud: RequestType::RECLAMO_SALUD_VITAL, suspensiones: array_reverse($pauses)));
        self::assertSame('2026-10-10T12:46:41.000000-05:00', $result->toArray()['fechaMaximaRespuesta']);
        self::assertSame(['SUSPENSION_NO_PREVISTA'], array_column($result->avisos, 'codigo'));
        self::assertFalse($result->conCierrePendiente);
    }

    private static function lastYearRules(string $unit): DeadlineRules
    {
        return new DeadlineRules(1, $unit, 2, true, $unit === 'H' ? 'INMEDIATO' : 'DIA_SIGUIENTE',
            $unit === 'H' ? 'INMEDIATO' : 'FIN_DEL_DIA', false, 'UTC',
            calendario: $unit === 'DH' ? 'COLOMBIA' : null, horaCierre: $unit === 'H' ? null : '23:59:59.999999');
    }

    public static function representableLastDay(): iterable
    {
        yield 'H cero' => ['H', '9999-12-31T22:00:00Z', '9999-12-31T22:00:00Z', '9999-12-31T22:00:00Z', '9999-12-31T23:00:00.000000+00:00'];
        yield 'H media hora' => ['H', '9999-12-31T22:00:00Z', '9999-12-31T22:00:00Z', '9999-12-31T22:30:00Z', '9999-12-31T23:30:00.000000+00:00'];
        foreach (['DC', 'DH'] as $unit) {
            yield "$unit cero" => [$unit, '9999-12-30T10:00:00Z', '9999-12-31T12:00:00Z', '9999-12-31T12:00:00Z', '9999-12-31T23:59:59.999999+00:00'];
            yield "$unit mismo día" => [$unit, '9999-12-30T10:00:00Z', '9999-12-31T12:00:00Z', '9999-12-31T13:00:00Z', '9999-12-31T23:59:59.999999+00:00'];
        }
    }

    #[DataProvider('representableLastDay')]
    public function testARepresentableDeadlineDoesNotNeedADayBeyondTheSupportedRange(string $unit, string $filing, string $start, string $end, string $expected): void
    {
        $result = (new DeadlineCalculator())->calcular(new CalculationRequest($filing, reglas: self::lastYearRules($unit),
            suspensiones: [new Suspension($start, $end)]));
        self::assertSame($expected, $result->toArray()['fechaMaximaRespuesta']);
        self::assertFalse($result->conCierrePendiente);
    }

    public static function units(): iterable
    {
        yield ['H'];
        yield ['DC'];
        yield ['DH'];
    }

    #[DataProvider('units')]
    public function testASuspensionThatExceedsTheDateRangeProducesAControlledError(string $unit): void
    {
        try {
            (new DeadlineCalculator())->calcular(new CalculationRequest('9999-12-29T10:00:00Z', reglas: self::lastYearRules($unit),
                suspensiones: [new Suspension('9999-12-29T10:00:00Z', '9999-12-31T23:59:59Z')]));
            self::fail('El resultado no cabe en un año de cuatro dígitos.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('FECHA_RESULTADO_FUERA_DE_RANGO', $error->code());
        }
    }

    public static function invalidLocalClosings(): iterable
    {
        yield 'hora que no existe' => ['2026-03-07T12:00:00-05:00', '02:30:00', 'FECHA_IMPOSIBLE'];
        yield 'hora que se repite' => ['2026-10-31T12:00:00-04:00', '01:30:00', 'FECHA_AMBIGUA'];
    }

    #[DataProvider('invalidLocalClosings')]
    public function testAnImpossibleOrAmbiguousClosingHourIsAControlledError(string $filing, string $closing, string $code): void
    {
        try {
            (new DeadlineCalculator())->calcular(new CalculationRequest($filing, reglas: new DeadlineRules(1, 'DC', 2, false,
                'DIA_SIGUIENTE', 'FIN_DEL_DIA', false, 'America/New_York', horaCierre: $closing)));
            self::fail('No debe inventarse una hora de cierre durante el cambio horario.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame($code, $error->code());
            self::assertSame(['fechaMaximaRespuesta'], $error->toArray()['campos']);
        }
    }

    public function testAnUnknownSuspensionTimezoneIsAControlledError(): void
    {
        try {
            new Suspension('2026-10-09T12:00:00', zonaHoraria: 'Zona/Inventada');
            self::fail('La suspensión necesita una zona interpretable.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('ZONA_HORARIA_DESCONOCIDA', $error->code());
            self::assertSame(['zonaHoraria'], $error->toArray()['campos']);
        }
    }
}
