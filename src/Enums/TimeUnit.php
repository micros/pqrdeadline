<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline\Enums;

enum TimeUnit: string
{
    case DC = 'DC';
    case DH = 'DH';
    case H = 'H';

    public function descripcion(): string
    {
        return match ($this) {
            self::DC => 'Días calendario.',
            self::DH => 'Días hábiles.',
            self::H => 'Horas continuas.',
        };
    }
}
