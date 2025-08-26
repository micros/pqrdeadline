<?php

namespace Micrositios\PqrDeadline\Errors;

class ErrorFactory
{
    public static function make(string $code, string $message, ?string $suspensionId = null): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'suspension_id' => $suspensionId,
        ];
    }
}
