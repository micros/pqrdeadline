<?php

namespace Micrositios\PqrDeadline\Contracts;

use Carbon\Carbon;

interface DeadlineCalculator
{
    /**
     * Calculates the deadline for a request.
     * @param array $params All required parameters for calculation
     * @return Carbon
     */
    public function calculate(array $params): Carbon;
}
