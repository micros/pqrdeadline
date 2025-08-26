<?php

namespace Micrositios\PqrDeadline\Support;

use Carbon\Carbon;
use InvalidArgumentException;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Strategies\StrategyFactory;

class DeadlineWrapper
{
    private array $params = [];
    private array $suspensions = [];

    /**
     * Configura el wrapper con dos formas:
     * - Forma básica: tipo, fecha, duplicación
     * - Forma avanzada: array de parámetros
     */
    public static function setup(string|array $typeOrParams, ?Carbon $createdAt = null, bool $doubleTerm = false): self
    {
        // Si es array, se asume forma avanzada
        if (is_array($typeOrParams)) {
            $instance = new self();
            $instance->params = $typeOrParams;
            return $instance;
        }

        // Forma básica
        $type = RequestType::tryFrom($typeOrParams);
        if (!$type) {
            throw new InvalidArgumentException("Unknown request type: {$type}");
        }

        $term = $type->getTerm();
        $params = [
            'created_at' => $createdAt,
            'double_term' => $doubleTerm,
        ];

        if ($term['unit'] === 'days') {
            $params['base_days'] = $term['value'];
            $params['strategy'] = ($term['business'] ?? false) ? 'business_days' : 'calendar_days';
        } elseif ($term['unit'] === 'hours') {
            $params['base_hours'] = $term['value'];
            $params['strategy'] = 'hours';
        } else {
            throw new InvalidArgumentException("Unknown term unit: {$term['unit']}");
        }

        $instance = new self();
        $instance->params = $params;
        return $instance;
    }

    public function injectSuspensions(array $suspensions): self
    {
        $this->suspensions = $suspensions;
        return $this;
    }

    public function calculateDeadline(): Carbon
    {
        $params = $this->params;
        $params['suspensions'] = $this->suspensions;
        $strategy = $params['strategy'];
        $calculator = StrategyFactory::make($strategy);
        return $calculator->calculate($params);
    }
}
