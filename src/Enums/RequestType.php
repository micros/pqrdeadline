<?php

namespace Micrositios\PqrDeadline\Enums;

enum RequestType: string
{
    case INFORME_CONGRESISTAS = 'informe_congresistas';
    case PETICION_GENERAL = 'peticion_general';
    case PETICION_AUTORIDADES = 'peticion_autoridades';
    case CONSULTA = 'consulta';
    case INFORMACION_PUBLICA = 'informacion_publica';
    case COPIA_HISTORIA_CLINICA = 'copia_historia_clinica';
    case RECLAMO = 'reclamo';
    case QUEJA = 'queja';
    case SALUD_RIESGO_SIMPLE = 'salud_riesgo_simple';
    case SALUD_RIESGO_PRIORIZADO = 'salud_riesgo_priorizado';
    case SALUD_RIESGO_VITAL = 'salud_riesgo_vital';
    case DENUNCIA = 'denuncia';
    case SUGERENCIA = 'sugerencia';
    case FELICITACION = 'felicitacion';

    public function getName(): string
    {
        return match ($this) {
            self::INFORME_CONGRESISTAS   => 'Petición de informe (Congresistas)',
            self::PETICION_GENERAL       => 'Petición general',
            self::PETICION_AUTORIDADES   => 'Petición entre autoridades',
            self::CONSULTA               => 'Consulta',
            self::INFORMACION_PUBLICA    => 'Solicitud de información pública',
            self::COPIA_HISTORIA_CLINICA => 'Copia de historia clínica',
            self::RECLAMO                => 'Reclamo',
            self::QUEJA                  => 'Queja',
            self::SALUD_RIESGO_SIMPLE    => 'Reclamo en salud, riesgo simple',
            self::SALUD_RIESGO_PRIORIZADO => 'Reclamo en salud, riesgo priorizado',
            self::SALUD_RIESGO_VITAL     => 'Reclamo en salud, riesgo vital',
            self::DENUNCIA               => 'Denuncia',
            self::SUGERENCIA             => 'Sugerencia',
            self::FELICITACION           => 'Felicitación',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::INFORME_CONGRESISTAS   => 'Solicitud de informes realizada por congresistas según Ley 5/1992 Art. 258.',
            self::PETICION_GENERAL       => 'Solicitudes respetuosas de carácter general o particular.',
            self::PETICION_AUTORIDADES   => 'Solicitud de información o documentos entre entidades públicas.',
            self::CONSULTA               => 'Solicitud especializada sobre temas propios de la entidad.',
            self::INFORMACION_PUBLICA    => 'Acceso a documentos, expedientes o datos públicos no reservados.',
            self::COPIA_HISTORIA_CLINICA => 'Solicitud de copias de historia clínica o exámenes médicos.',
            self::RECLAMO                => 'Exigir solución por prestación indebida de un servicio o falta de atención.',
            self::QUEJA                  => 'Manifestación de inconformidad frente a la conducta de un servidor público.',
            self::SALUD_RIESGO_SIMPLE    => 'Situaciones en salud sin riesgo vital inmediato (ej. citas, entrega incompleta de medicamentos).',
            self::SALUD_RIESGO_PRIORIZADO => 'Casos en salud que requieren atención urgente.',
            self::SALUD_RIESGO_VITAL     => 'Casos en salud que comprometen la vida o integridad del paciente.',
            self::DENUNCIA               => 'Informe de presunta irregularidad para investigación disciplinaria, penal o administrativa.',
            self::SUGERENCIA             => 'Propuesta para mejorar la gestión o servicios de la entidad.',
            self::FELICITACION           => 'Manifestación de satisfacción frente a los servicios prestados.',
        };
    }

    public function getTerm(): array
    {
        return match ($this) {
            self::INFORME_CONGRESISTAS   => ['value' => 5,  'unit' => 'days',  'business' => false],
            self::PETICION_GENERAL       => ['value' => 15, 'unit' => 'days',  'business' => true],
            self::PETICION_AUTORIDADES   => ['value' => 10, 'unit' => 'days',  'business' => true],
            self::CONSULTA               => ['value' => 30, 'unit' => 'days',  'business' => true],
            self::INFORMACION_PUBLICA    => ['value' => 10, 'unit' => 'days',  'business' => true],
            self::COPIA_HISTORIA_CLINICA => ['value' => 3,  'unit' => 'days',  'business' => true],
            self::RECLAMO                => ['value' => 15, 'unit' => 'days',  'business' => true],
            self::QUEJA                  => ['value' => 15, 'unit' => 'days',  'business' => true],
            self::SALUD_RIESGO_SIMPLE    => ['value' => 72, 'unit' => 'hours', 'business' => false],
            self::SALUD_RIESGO_PRIORIZADO => ['value' => 48, 'unit' => 'hours', 'business' => false],
            self::SALUD_RIESGO_VITAL     => ['value' => 24, 'unit' => 'hours', 'business' => false],
            self::DENUNCIA               => ['value' => 15, 'unit' => 'days',  'business' => true],
            self::SUGERENCIA             => ['value' => 15, 'unit' => 'days',  'business' => true],
            self::FELICITACION           => ['value' => 15, 'unit' => 'days',  'business' => true],
        };
    }
}
