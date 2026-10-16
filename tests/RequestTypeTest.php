<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Tests;

use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Enums\TimeUnit;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTypeTest extends TestCase
{
    public static function types(): iterable
    {
        yield ['PETICION_GENERAL', 15, 'DH', 30, true, true, 'Ley 1755 de 2015'];
        yield ['SOLICITUD_INFORMACION', 10, 'DH', 20, true, true, 'Ley 1755 de 2015'];
        yield ['CONSULTA', 30, 'DH', 60, true, true, 'Ley 1755 de 2015'];
        yield ['QUEJA', 15, 'DH', 30, true, true, 'Ley 1755 de 2015'];
        yield ['RECLAMO', 15, 'DH', 30, true, true, 'Ley 1755 de 2015'];
        yield ['SUGERENCIA', 15, 'DH', 30, true, true, 'Ley 1755 de 2015'];
        yield ['FELICITACION', 15, 'DH', 30, true, true, 'Ley 1755 de 2015'];
        yield ['PETICION_INFORMACION_ENTRE_AUTORIDADES', 10, 'DH', 20, false, false, 'Ley 1755 de 2015'];
        yield ['INFORME_CONGRESISTAS', 5, 'DH', 10, false, false, 'Ley 5 de 1992'];
        yield ['RECLAMO_SALUD_SIMPLE', 72, 'H', 144, false, false, 'Circular Externa Supersalud 2023151000000010-5 de 2023'];
        yield ['RECLAMO_SALUD_PRIORIZADO', 48, 'H', 96, false, false, 'Circular Externa Supersalud 2023151000000010-5 de 2023'];
        yield ['RECLAMO_SALUD_VITAL', 24, 'H', 48, false, false, 'Circular Externa Supersalud 2023151000000010-5 de 2023'];
        yield ['RECLAMO_SALUD_VITAL_MENOR', 8, 'H', 16, false, false, 'Circular Externa Supersalud 2026151000000007-5 de 2026'];
        yield ['COPIAS_SALUD', 3, 'DH', 6, false, false, 'Circular Externa Supersalud 2023151000000010-5 de 2023'];
        yield ['PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS', 15, 'DH', 30, false, false, 'Ley 142 de 1994'];
        yield ['PQR_TELECOMUNICACIONES', 15, 'DH', 30, false, true, 'Resolución CRC 5050 de 2016'];
        yield ['PQR_POSTAL', 15, 'DH', 30, false, false, 'Resolución CRC 5050 de 2016'];
        yield ['CONSULTA_DATOS_PERSONALES', 10, 'DH', 15, false, true, 'Ley 1581 de 2012'];
        yield ['RECLAMO_DATOS_PERSONALES', 15, 'DH', 23, false, true, 'Ley 1581 de 2012'];
        yield ['CONSULTA_HABEAS_DATA_FINANCIERO', 10, 'DH', 15, false, true, 'Ley 1266 de 2008'];
        yield ['RECLAMO_HABEAS_DATA_FINANCIERO', 15, 'DH', 23, false, true, 'Ley 1266 de 2008'];
    }

    #[DataProvider('types')]
    public function testApprovedRulesAndSources(string $code, int $term, string $unit, int $extended, bool $suspend, bool $extend, string $law): void
    {
        $type = RequestType::desdeCodigo($code);
        $rules = $type->reglas();
        self::assertSame($code, $type->value);
        self::assertSame($term, $rules->plazo);
        self::assertSame($unit, $rules->unidad->value);
        self::assertSame($extended, $rules->plazoAmpliado);
        self::assertSame($suspend, $rules->permiteSuspension);
        self::assertSame($extend, $rules->permiteAmpliacion);
        self::assertSame($unit === 'H' ? 'INMEDIATO' : 'SIGUIENTE_HABIL', $rules->inicio);
        self::assertSame($unit === 'H' ? 'INMEDIATO' : 'FIN_DEL_DIA', $rules->cierre);
        self::assertSame($unit !== 'H', $rules->ajustarVencimientoAHabil);
        self::assertSame('America/Bogota', $rules->zonaHoraria);
        self::assertSame($unit === 'H' ? null : 'COLOMBIA', $rules->calendario);
        self::assertSame($unit === 'H' ? null : '23:59:59.999999', $rules->horaCierre);
        self::assertNotEmpty($type->descripcion());
        self::assertNotEmpty($type->eventoInicial());
        self::assertNotEmpty($type->observaciones());
        self::assertSame($law, $type->normas()[0]['norma']);
        foreach ($type->normas() as $source) {
            self::assertNotEmpty($source['articulo']);
            self::assertStringStartsWith('https://', $source['url']);
        }
    }

    public function testTheCatalogContainsExactlyTheAdjustedTypes(): void
    {
        self::assertSame(array_column(iterator_to_array(self::types()), 0), array_column(RequestType::cases(), 'value'));
        self::assertSame('general o particular sin un procedimiento especial aplicable.', RequestType::PETICION_GENERAL->descripcion());
        self::assertStringContainsString('PENDIENTE:', RequestType::RECLAMO_DATOS_PERSONALES->observaciones());
    }

    public static function unknownTypes(): iterable
    {
        foreach (['NO_EXISTE', 'PETICION_INFORMACION', 'CONSULTA_JURIDICA', 'DENUNCIA_RESPUESTA_INICIAL', 'CUESTIONARIO_CITACION_CONGRESO', 'ENVIO_RADICADO', 'peticion_general', ''] as $code) {
            yield [$code];
        }
    }

    #[DataProvider('unknownTypes')]
    public function testUnknownAndRemovedCodesProduceAControlledError(string $code): void
    {
        try {
            RequestType::desdeCodigo($code);
            self::fail('Se esperaba un tipo desconocido.');
        } catch (InvalidCalculationRequest $error) {
            self::assertSame('TIPO_SOLICITUD_DESCONOCIDO', $error->code());
            self::assertSame(['tipoSolicitud'], $error->toArray()['campos']);
            self::assertSame(['tipoRecibido' => $code], $error->details());
        }
    }

    public function testMetadataAndConfigurationCannotPolluteOtherCalls(): void
    {
        $type = RequestType::PETICION_GENERAL;
        $norms = $type->normas();
        $norms[0]['norma'] = 'Alterada';
        $custom = $type->reglas('America/New_York');
        self::assertSame('America/New_York', $custom->zonaHoraria);
        self::assertSame('America/Bogota', $type->reglas()->zonaHoraria);
        self::assertSame('Ley 1755 de 2015', $type->normas()[0]['norma']);
    }

    public function testEveryUnitHasADescription(): void
    {
        self::assertSame(['DC', 'DH', 'H'], array_column(TimeUnit::cases(), 'value'));
        self::assertSame('Días calendario.', TimeUnit::DC->descripcion());
        self::assertSame('Días hábiles.', TimeUnit::DH->descripcion());
        self::assertSame('Horas continuas.', TimeUnit::H->descripcion());
    }
}
