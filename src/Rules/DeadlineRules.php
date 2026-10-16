<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Rules;

use DateTimeZone;
use Micrositios\PqrDeadline\Enums\TimeUnit;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;

final readonly class DeadlineRules
{
    public int $plazo;
    public TimeUnit $unidad;
    public int $plazoAmpliado;
    public ?string $horaCierre;

    public function __construct(
        mixed $plazo,
        TimeUnit|string $unidad,
        mixed $plazoAmpliado,
        public bool $permiteSuspension,
        public string $inicio,
        public string $cierre,
        public bool $ajustarVencimientoAHabil,
        public string $zonaHoraria,
        public ?string $calendario = null,
        ?string $horaCierre = null,
        public bool $permiteAmpliacion = true,
    ) {
        if (!is_int($plazo) || $plazo < 1) {
            throw self::error('PLAZO_INVALIDO', 'El plazo debe ser un entero positivo.', ['plazo']);
        }
        $this->plazo = $plazo;
        $resolvedUnit = is_string($unidad) ? TimeUnit::tryFrom($unidad) : $unidad;
        if ($resolvedUnit === null) {
            throw self::error('UNIDAD_DE_PLAZO_NO_SOPORTADA', 'Las unidades permitidas son DC, DH y H.', ['unidad']);
        }
        $this->unidad = $resolvedUnit;
        if (!is_int($plazoAmpliado) || $plazoAmpliado <= $plazo) {
            throw self::error('PLAZO_AMPLIADO_INVALIDO', 'El plazo ampliado debe ser un total entero mayor al original.', ['plazoAmpliado']);
        }
        $this->plazoAmpliado = $plazoAmpliado;
        if (!in_array($inicio, ['SIGUIENTE_HABIL', 'DIA_SIGUIENTE', 'MISMO_DIA', 'INMEDIATO'], true)) {
            throw self::error('REGLA_INICIO_NO_SOPORTADA', 'La regla de inicio no está soportada.', ['inicio']);
        }
        if (!in_array($cierre, ['FIN_DEL_DIA', 'INMEDIATO'], true)) {
            throw self::error('REGLA_CIERRE_NO_SOPORTADA', 'La regla de cierre no está soportada.', ['cierre']);
        }
        if (!in_array($zonaHoraria, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw self::error('ZONA_HORARIA_DESCONOCIDA', 'La zona horaria no es válida.', ['zonaHoraria']);
        }

        if ($this->unidad === TimeUnit::H) {
            if ($inicio !== 'INMEDIATO' || $cierre !== 'INMEDIATO' || $ajustarVencimientoAHabil
                || $calendario !== null || $horaCierre !== null) {
                throw self::error('REGLAS_INCOMPATIBLES', 'Las horas son continuas, con inicio y cierre inmediatos, sin calendario ni cierre diario.',
                    ['unidad', 'inicio', 'cierre', 'ajustarVencimientoAHabil', 'calendario', 'horaCierre']);
            }
        } elseif ($inicio === 'INMEDIATO' || $cierre !== 'FIN_DEL_DIA') {
            throw self::error('REGLAS_INCOMPATIBLES', 'Los plazos diarios requieren un inicio diario y cierre FIN_DEL_DIA.', ['unidad', 'inicio', 'cierre']);
        }

        $usesCalendar = $this->unidad === TimeUnit::DH || $inicio === 'SIGUIENTE_HABIL' || $ajustarVencimientoAHabil;
        $missing = [];
        if ($usesCalendar && $calendario === null) {
            $missing[] = 'calendario';
        }
        if ($cierre === 'FIN_DEL_DIA' && $horaCierre === null) {
            $missing[] = 'horaCierre';
        }
        if ($missing !== []) {
            throw self::error('REGLAS_INCOMPLETAS', 'Falta configuración necesaria para estas reglas.', $missing);
        }
        if (!$usesCalendar && $calendario !== null) {
            throw self::error('REGLAS_INCOMPATIBLES', 'Estas reglas no utilizan calendario de días hábiles.', ['calendario']);
        }
        if ($usesCalendar && $calendario !== 'COLOMBIA') {
            throw self::error('CALENDARIO_NO_SOPORTADO', 'El calendario disponible es COLOMBIA.', ['calendario']);
        }
        if ($horaCierre !== null) {
            if (preg_match('/\A([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])(?:\.([0-9]{1,6}))?\z/', $horaCierre, $parts) !== 1) {
                throw self::error('HORA_CIERRE_INVALIDA', 'Use HH:MM:SS con hasta seis decimales.', ['horaCierre']);
            }
            $horaCierre = substr($horaCierre, 0, 8) . '.' . str_pad($parts[4] ?? '', 6, '0');
        }
        $this->horaCierre = $horaCierre;
    }

    private static function error(string $code, string $message, array $fields): InvalidCalculationRequest
    {
        return new InvalidCalculationRequest($code, $message, array_map(static fn (string $field): string => 'reglas.' . $field, $fields));
    }
}
