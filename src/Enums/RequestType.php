<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Enums;

use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Rules\DeadlineRules;

enum RequestType: string
{
    case PETICION_GENERAL = 'PETICION_GENERAL';
    case SOLICITUD_INFORMACION = 'SOLICITUD_INFORMACION';
    case CONSULTA = 'CONSULTA';
    case QUEJA = 'QUEJA';
    case RECLAMO = 'RECLAMO';
    case SUGERENCIA = 'SUGERENCIA';
    case FELICITACION = 'FELICITACION';
    case PETICION_INFORMACION_ENTRE_AUTORIDADES = 'PETICION_INFORMACION_ENTRE_AUTORIDADES';
    case INFORME_CONGRESISTAS = 'INFORME_CONGRESISTAS';
    case RECLAMO_SALUD_SIMPLE = 'RECLAMO_SALUD_SIMPLE';
    case RECLAMO_SALUD_PRIORIZADO = 'RECLAMO_SALUD_PRIORIZADO';
    case RECLAMO_SALUD_VITAL = 'RECLAMO_SALUD_VITAL';
    case RECLAMO_SALUD_VITAL_MENOR = 'RECLAMO_SALUD_VITAL_MENOR';
    case COPIAS_SALUD = 'COPIAS_SALUD';
    case PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS = 'PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS';
    case PQR_TELECOMUNICACIONES = 'PQR_TELECOMUNICACIONES';
    case PQR_POSTAL = 'PQR_POSTAL';
    case CONSULTA_DATOS_PERSONALES = 'CONSULTA_DATOS_PERSONALES';
    case RECLAMO_DATOS_PERSONALES = 'RECLAMO_DATOS_PERSONALES';
    case CONSULTA_HABEAS_DATA_FINANCIERO = 'CONSULTA_HABEAS_DATA_FINANCIERO';
    case RECLAMO_HABEAS_DATA_FINANCIERO = 'RECLAMO_HABEAS_DATA_FINANCIERO';

    private const array CONFIGURACION_COMUN = [
        'zonaHoraria' => 'America/Bogota',
        'calendario' => 'COLOMBIA',
        'horaCierre' => '23:59:59.999999',
    ];

    private const array TIPOS = [
        'PETICION_GENERAL' => [
            'descripcion' => 'general o particular sin un procedimiento especial aplicable.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 13, 14 (inciso primero y parágrafo) y 17.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'SOLICITUD_INFORMACION' => [
            'descripcion' => 'existente o documentos bajo el régimen general de petición.',
            'plazo' => 10,
            'unidad' => 'DH',
            'plazoAmpliado' => 20,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 14 (numeral 1 y parágrafo) y 17.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'CONSULTA' => [
            'descripcion' => 'autoridad para obtener orientación o concepto sobre materias a su cargo.',
            'plazo' => 30,
            'unidad' => 'DH',
            'plazoAmpliado' => 60,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 14 (numeral 2 y parágrafo), 17 y 28.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'QUEJA' => [
            'descripcion' => 'conducta o atención, cuya respuesta de petición no tiene un procedimiento especial.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 13, 14 (inciso primero y parágrafo) y 17.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'RECLAMO' => [
            'descripcion' => 'solución a una inconformidad sin un procedimiento especial aplicable.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 13, 14 (inciso primero y parágrafo) y 17.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'SUGERENCIA' => [
            'descripcion' => 'una petición sujeta al término general; otro objeto exige su tipo específico.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 13, 14 (inciso primero y parágrafo) y 17.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'FELICITACION' => [
            'descripcion' => 'una petición sujeta al término general; otro objeto exige su tipo específico.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => true,
            'permiteSuspension' => true,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta de petición sin procedimiento especial. Ampliación única según el criterio de total doble; suspensión art. 17 declarada procedente por la aplicación.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículos 13, 14 (inciso primero y parágrafo) y 17.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'PETICION_INFORMACION_ENTRE_AUTORIDADES' => [
            'descripcion' => 'o documentos entre autoridades en el supuesto del artículo 30 de la Ley 1755.',
            'plazo' => 10,
            'unidad' => 'DH',
            'plazoAmpliado' => 20,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Solo información o documentos entre autoridades; no se habilitan automáticamente ampliación ni subsanación ciudadana. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Ley 1755 de 2015',
                    'articulo' => 'Artículo 1, que sustituye el título II del CPACA: artículo 30.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm',
                ],
            ],
        ],
        'INFORME_CONGRESISTAS' => [
            'descripcion' => 'de un congresista en el supuesto del artículo 258 de la Ley 5.',
            'plazo' => 5,
            'unidad' => 'DH',
            'plazoAmpliado' => 10,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Informe solicitado en el supuesto del artículo 258; no cualquier comunicación de un congresista. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Ley 5 de 1992',
                    'articulo' => 'Artículo 258.',
                    'url' => 'https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=11368',
                ],
            ],
        ],
        'RECLAMO_SALUD_SIMPLE' => [
            'descripcion' => 'clasificado como riesgo simple según el régimen sectorial.',
            'plazo' => 72,
            'unidad' => 'H',
            'plazoAmpliado' => 144,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'INMEDIATO',
            'cierre' => 'INMEDIATO',
            'ajustarVencimientoAHabil' => false,
            'eventoInicial' => 'Radicación original del reclamo, conservada incluso si hay traslado.',
            'observaciones' => 'Máximo continuo según riesgo; la atención inmediata no se pospone hasta el vencimiento. El catálogo no declara previstas la ampliación ni la suspensión; si el cliente las envía, se calculan con aviso. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Circular Externa Supersalud 2023151000000010-5 de 2023',
                    'articulo' => 'Instrucción primera: numeral 3.3.2.1 del título VII de la Circular Externa 047 de 2007.',
                    'url' => 'https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf',
                ],
            ],
        ],
        'RECLAMO_SALUD_PRIORIZADO' => [
            'descripcion' => 'clasificado como riesgo priorizado según el régimen sectorial.',
            'plazo' => 48,
            'unidad' => 'H',
            'plazoAmpliado' => 96,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'INMEDIATO',
            'cierre' => 'INMEDIATO',
            'ajustarVencimientoAHabil' => false,
            'eventoInicial' => 'Radicación original del reclamo, conservada incluso si hay traslado.',
            'observaciones' => 'Máximo continuo según riesgo; la atención inmediata no se pospone hasta el vencimiento. El catálogo no declara previstas la ampliación ni la suspensión; si el cliente las envía, se calculan con aviso. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Circular Externa Supersalud 2023151000000010-5 de 2023',
                    'articulo' => 'Instrucción primera: numeral 3.3.2.2 del título VII de la Circular Externa 047 de 2007.',
                    'url' => 'https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf',
                ],
            ],
        ],
        'RECLAMO_SALUD_VITAL' => [
            'descripcion' => 'con riesgo vital que exige atención inmediata.',
            'plazo' => 24,
            'unidad' => 'H',
            'plazoAmpliado' => 48,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'INMEDIATO',
            'cierre' => 'INMEDIATO',
            'ajustarVencimientoAHabil' => false,
            'eventoInicial' => 'Radicación original del reclamo, conservada incluso si hay traslado.',
            'observaciones' => 'Máximo continuo según riesgo; la atención inmediata no se pospone hasta el vencimiento. El catálogo no declara previstas la ampliación ni la suspensión; si el cliente las envía, se calculan con aviso. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Circular Externa Supersalud 2023151000000010-5 de 2023',
                    'articulo' => 'Instrucción primera: numeral 3.3.2.3 del título VII de la Circular Externa 047 de 2007.',
                    'url' => 'https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf',
                ],
            ],
        ],
        'RECLAMO_SALUD_VITAL_MENOR' => [
            'descripcion' => 'de un niño, niña o adolescente con riesgo vital que exige atención inmediata.',
            'plazo' => 8,
            'unidad' => 'H',
            'plazoAmpliado' => 16,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'INMEDIATO',
            'cierre' => 'INMEDIATO',
            'ajustarVencimientoAHabil' => false,
            'eventoInicial' => 'Radicación original del reclamo, conservada incluso si hay traslado.',
            'observaciones' => 'Máximo continuo según riesgo; la atención inmediata no se pospone hasta el vencimiento. El catálogo no declara previstas la ampliación ni la suspensión; si el cliente las envía, se calculan con aviso. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Circular Externa Supersalud 2026151000000007-5 de 2026',
                    'articulo' => 'Instrucción tercera: adición al numeral 3.3.2.3 del título VII de la Circular Externa 047 de 2007.',
                    'url' => 'https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20externa%20n%C3%BAmero%202026151000000007-5%20de%202026.pdf',
                ],
                [
                    'norma' => 'Circular Externa Supersalud 2023151000000010-5 de 2023',
                    'articulo' => 'Instrucción primera: numeral 3.3.2.3, régimen general de reclamos de riesgo vital.',
                    'url' => 'https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf',
                ],
            ],
        ],
        'COPIAS_SALUD' => [
            'descripcion' => 'bajo el régimen de salud; historias o exámenes necesarios para consulta o urgencia se clasifican como reclamos.',
            'plazo' => 3,
            'unidad' => 'DH',
            'plazoAmpliado' => 6,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Copias no requeridas para consulta o urgencia; en esos casos debe seleccionarse el reclamo de salud pertinente. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Circular Externa Supersalud 2023151000000010-5 de 2023',
                    'articulo' => 'Instrucción primera: numeral 3.3.2.4, literal c del título VII de la Circular Externa 047 de 2007.',
                    'url' => 'https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf',
                ],
            ],
        ],
        'PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS' => [
            'descripcion' => 'PQR de usuario o suscriptor en la relación contractual de servicios públicos domiciliarios.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la PQR contractual.',
            'observaciones' => 'No se incluye el día inicial. Definir efectos de pruebas o demora del usuario. No se presume duplicación ni suspensión del régimen general. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Ley 142 de 1994',
                    'articulo' => 'Artículos 152 y 158.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/ley_0142_1994.htm',
                ],
                [
                    'norma' => 'Decreto 2150 de 1995',
                    'articulo' => 'Artículo 123.',
                    'url' => 'https://cancilleria.gov.co/normograma/compilacion/docs/decreto_2150_1995.htm',
                ],
            ],
        ],
        'PQR_TELECOMUNICACIONES' => [
            'descripcion' => 'PQR de usuario bajo el régimen de telecomunicaciones.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => true,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta al usuario o decisión de reposición; la ampliación exige pruebas y comunicación bajo responsabilidad de la aplicación.',
            'normas' => [
                [
                    'norma' => 'Resolución CRC 5050 de 2016',
                    'articulo' => 'Artículo 2.1.24.3, modificado por la Resolución CRC 7811 de 2025.',
                    'url' => 'https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_5050_2016.htm',
                ],
            ],
        ],
        'PQR_POSTAL' => [
            'descripcion' => 'PQR de usuario bajo el régimen de servicios postales.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 30,
            'permiteAmpliacion' => false,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la solicitud por el responsable del trámite.',
            'observaciones' => 'Respuesta ordinaria. La ampliación probatoria de este artículo no está modelada como permiso en el catálogo. La bandera del cliente se aplica con aviso, sin equipararla a la reposición. El doble se configura como valor de cálculo a petición del cliente, sin afirmar habilitación normativa.',
            'normas' => [
                [
                    'norma' => 'Resolución CRC 5050 de 2016',
                    'articulo' => 'Artículo 2.2.7.10.',
                    'url' => 'https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_5050_2016.htm',
                ],
            ],
        ],
        'CONSULTA_DATOS_PERSONALES' => [
            'descripcion' => 'Consulta del titular sobre sus datos personales bajo la Ley 1581; no es una consulta jurídica.',
            'plazo' => 10,
            'unidad' => 'DH',
            'plazoAmpliado' => 15,
            'permiteAmpliacion' => true,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la consulta de datos personales.',
            'observaciones' => 'No se incluye el día de recepción. La ampliación corresponde a 5 DH adicionales.',
            'normas' => [
                [
                    'norma' => 'Ley 1581 de 2012',
                    'articulo' => 'Artículo 14.',
                    'url' => 'https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981',
                ],
            ],
        ],
        'RECLAMO_DATOS_PERSONALES' => [
            'descripcion' => 'Reclamo sobre tratamiento de datos personales bajo la Ley 1581, incluida su corrección, actualización o supresión.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 23,
            'permiteAmpliacion' => true,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción del reclamo de datos personales.',
            'observaciones' => 'PENDIENTE: Definir efectos de la subsanación especial de la Ley 1581. No importar automáticamente la suspensión del artículo 17. La ampliación corresponde a 8 DH adicionales.',
            'normas' => [
                [
                    'norma' => 'Ley 1581 de 2012',
                    'articulo' => 'Artículo 15, numerales 1 y 3.',
                    'url' => 'https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981',
                ],
            ],
        ],
        'CONSULTA_HABEAS_DATA_FINANCIERO' => [
            'descripcion' => 'Consulta de información del titular bajo el régimen especial de hábeas data de la Ley 1266.',
            'plazo' => 10,
            'unidad' => 'DH',
            'plazoAmpliado' => 15,
            'permiteAmpliacion' => true,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción de la consulta de hábeas data financiero.',
            'observaciones' => 'No se incluye el día de recepción. La ampliación corresponde a 5 DH adicionales.',
            'normas' => [
                [
                    'norma' => 'Ley 1266 de 2008',
                    'articulo' => 'Artículo 16, apartado I (trámite de consultas).',
                    'url' => 'https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=34488',
                ],
            ],
        ],
        'RECLAMO_HABEAS_DATA_FINANCIERO' => [
            'descripcion' => 'Reclamo sobre información del titular bajo el régimen especial de hábeas data de la Ley 1266.',
            'plazo' => 15,
            'unidad' => 'DH',
            'plazoAmpliado' => 23,
            'permiteAmpliacion' => true,
            'permiteSuspension' => false,
            'inicio' => 'SIGUIENTE_HABIL',
            'cierre' => 'FIN_DEL_DIA',
            'ajustarVencimientoAHabil' => true,
            'eventoInicial' => 'Recepción del reclamo de hábeas data financiero.',
            'observaciones' => 'PENDIENTE: Definir efectos de la subsanación de la Ley 1266 y coordinación fuente/operador. No importar automáticamente la suspensión del artículo 17. La ampliación corresponde a 8 DH adicionales.',
            'normas' => [
                [
                    'norma' => 'Ley 1266 de 2008',
                    'articulo' => 'Artículo 16, apartado II, numerales 1 a 6 (trámite de reclamos y coordinación entre fuente y operador).',
                    'url' => 'https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=34488',
                ],
            ],
        ],
    ];

    public static function desdeCodigo(string $codigo): self
    {
        return self::tryFrom($codigo) ?? throw new InvalidCalculationRequest(
            'TIPO_SOLICITUD_DESCONOCIDO', 'El tipo de solicitud no existe en el catálogo.',
            ['tipoSolicitud'], ['tipoRecibido' => $codigo],
        );
    }

    public function descripcion(): string
    {
        return self::TIPOS[$this->value]['descripcion'];
    }

    /** @return list<array{norma: string, articulo: string, url: string}> */
    public function normas(): array
    {
        return self::TIPOS[$this->value]['normas'];
    }

    public function observaciones(): string
    {
        return self::TIPOS[$this->value]['observaciones'];
    }

    public function eventoInicial(): string
    {
        return self::TIPOS[$this->value]['eventoInicial'];
    }

    public function reglas(?string $zonaHoraria = null): DeadlineRules
    {
        $type = self::TIPOS[$this->value];
        $usesCalendar = $type['unidad'] === 'DH' || $type['inicio'] === 'SIGUIENTE_HABIL' || $type['ajustarVencimientoAHabil'];

        return new DeadlineRules(
            plazo: $type['plazo'],
            unidad: $type['unidad'],
            plazoAmpliado: $type['plazoAmpliado'],
            permiteSuspension: $type['permiteSuspension'],
            permiteAmpliacion: $type['permiteAmpliacion'],
            inicio: $type['inicio'],
            cierre: $type['cierre'],
            ajustarVencimientoAHabil: $type['ajustarVencimientoAHabil'],
            zonaHoraria: $zonaHoraria ?? self::CONFIGURACION_COMUN['zonaHoraria'],
            calendario: $usesCalendar ? self::CONFIGURACION_COMUN['calendario'] : null,
            horaCierre: $type['cierre'] === 'FIN_DEL_DIA' ? self::CONFIGURACION_COMUN['horaCierre'] : null,
        );
    }
}
