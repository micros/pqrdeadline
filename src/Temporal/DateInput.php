<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Temporal;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;

/** @internal Conversión estricta; no almacena fechas ni consulta el reloj. */
final class DateInput
{
    public static function parse(
        DateTimeInterface|string $value,
        string $field,
        ?string $zone = null,
    ): CarbonImmutable {
        $timezone = null;
        if ($zone !== null) {
            if (!in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
                throw new InvalidCalculationRequest(
                    'ZONA_HORARIA_DESCONOCIDA', 'La zona horaria no es válida.',
                    ['zonaHoraria'], ['zonaHoraria' => $zone],
                );
            }
            $timezone = new DateTimeZone($zone);
        }
        if ($value instanceof DateTimeInterface) {
            $date = CarbonImmutable::instance(DateTimeImmutable::createFromInterface($value));

            return $timezone === null ? $date : $date->setTimezone($timezone);
        }

        $pattern = '/\A(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})?\z/';
        if (preg_match($pattern, $value, $parts, PREG_UNMATCHED_AS_NULL) !== 1) {
            throw self::error($field, 'FORMATO_INVALIDO', 'Use ISO 8601 con fecha, hora y segundos.', $value);
        }
        [, $year, $month, $day, $hour, $minute, $second, $fraction, $offset] = $parts;
        if (
            !checkdate((int) $month, (int) $day, (int) $year)
            || (int) $hour > 23 || (int) $minute > 59 || (int) $second > 59
        ) {
            throw self::error($field, 'IMPOSIBLE', 'La fecha u hora indicada no existe.', $value);
        }
        if ($offset === '-00:00') {
            throw self::error($field, 'AMBIGUA', 'Indique un desplazamiento horario conocido.', $value);
        }
        if ($offset !== null && $offset !== 'Z') {
            $hours = (int) substr($offset, 1, 2);
            $minutes = (int) substr($offset, 4, 2);
            if ($hours > 14 || $minutes > 59 || ($hours === 14 && $minutes !== 0)) {
                throw self::error($field, 'IMPOSIBLE', 'El desplazamiento horario no es válido.', $value);
            }
        }
        if ($offset === null && $timezone === null) {
            throw self::error($field, 'ZONA_REQUERIDA', 'Indique un desplazamiento o una zona horaria.', $value);
        }

        $local = "$year-$month-{$day}T$hour:$minute:$second." . str_pad($fraction ?? '', 6, '0');
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s.uP', $local . ($offset === null || $offset === 'Z' ? '+00:00' : $offset),
        );
        if ($date === false) {
            throw self::error($field, 'IMPOSIBLE', 'La fecha no se puede representar.', $value);
        }
        if ($offset !== null) {
            $date = CarbonImmutable::instance($date);

            return $timezone === null ? $date : $date->setTimezone($timezone);
        }

        // Una hora local puede no existir o repetirse durante un cambio horario.
        $timestamp = $date->getTimestamp();
        $offsets = [$timezone->getOffset($date)];
        foreach ($timezone->getTransitions($timestamp - 172800, $timestamp + 172800) ?: [] as $transition) {
            $offsets[] = $transition['offset'];
        }
        $matches = [];
        foreach (array_unique($offsets) as $seconds) {
            $candidate = CarbonImmutable::instance($date)->subSeconds($seconds)->setTimezone($timezone);
            if ($candidate->format('Y-m-d\TH:i:s.u') === $local) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) !== 1) {
            throw self::error(
                $field, $matches === [] ? 'IMPOSIBLE' : 'AMBIGUA',
                'La hora local no existe o se repite; indique un desplazamiento válido.', $value,
            );
        }

        return $matches[0];
    }

    private static function error(string $field, string $suffix, string $message, string $value): InvalidCalculationRequest
    {
        $prefix = match ($field) {
            'fechaRadicacion' => 'FECHA_RADICACION',
            'inicio' => 'FECHA_SUSPENSION_INICIO',
            'fin' => 'FECHA_SUSPENSION_FIN',
            default => 'FECHA',
        };

        return new InvalidCalculationRequest($prefix . '_' . $suffix, $message, [$field], ['valorRecibido' => $value]);
    }
}
