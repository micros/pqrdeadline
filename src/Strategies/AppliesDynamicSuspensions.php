<?php

namespace Micrositios\PqrDeadline\Strategies;

use Carbon\Carbon;

/**
 * Suma tiempo de suspensiones al deadline de forma dinámica.
 * Solo aplica suspensiones que inicien antes del deadline vigente.
 * Procesa traslapes para evitar contar tiempo duplicado.
 * Usado por CalendarDays y Hours. BusinessDays tiene lógica propia más compleja.
 */
trait AppliesDynamicSuspensions
{
    /**
     * Suma tiempo de suspensiones al deadline de forma dinámica.
     * Solo aplica suspensiones que inicien antes del deadline vigente.
     * Procesa traslapes para evitar contar tiempo duplicado.
     */
    protected function applyDynamicSuspensions(Carbon $initialDeadline, array $suspensions, Carbon $createdAt): Carbon
    {
        // 1. Filtrar suspensiones inválidas (sin fechas)
        $valid = array_filter($suspensions, function ($s) {
            return isset($s['start_at'], $s['end_at']) &&
                $s['start_at'] instanceof Carbon &&
                $s['end_at'] instanceof Carbon;
        });

        // 2. Ordenar por fecha de inicio ascendente
        usort($valid, function ($a, $b) {
            return $a['start_at']->getTimestamp() <=> $b['start_at']->getTimestamp();
        });

        // 3. Validar que end_at > start_at y que start_at >= createdAt
        $filtered = [];
        foreach ($valid as $s) {
            if ($s['end_at']->lessThanOrEqualTo($s['start_at'])) continue;
            if ($s['start_at']->lessThan($createdAt)) continue;
            $filtered[] = $s;
        }

        // 4. Procesar traslapes y suspensiones contenidas
        $finalSuspensions = [];
        foreach ($filtered as $suspension) {
            $start = $suspension['start_at'];
            $end = $suspension['end_at'];

            // Si la suspensión está contenida completamente en la anterior, ignorar
            if (!empty($finalSuspensions)) {
                $last = end($finalSuspensions);
                if ($start->greaterThanOrEqualTo($last['start_at']) && $end->lessThanOrEqualTo($last['end_at'])) {
                    continue; // contenida
                }
                // Traslape parcial: ajustar inicio al cierre de la anterior
                if ($start->lessThan($last['end_at']) && $end->greaterThan($last['end_at'])) {
                    $start = $last['end_at']->copy();
                }
            }

            // Solo agregar si el intervalo es válido
            if ($end->greaterThan($start)) {
                $finalSuspensions[] = ['start_at' => $start, 'end_at' => $end];
            }
        }

        // 5. Aplicar suspensiones dinámicamente
        $currentDeadline = $initialDeadline->copy();
        foreach ($finalSuspensions as $suspension) {
            $start = $suspension['start_at'];
            $end = $suspension['end_at'];

            // Solo aplicar si la suspensión inicia antes del plazo vigente
            if ($start->lessThan($currentDeadline)) {
                // Calcular duración de la suspensión a sumar
                $suspensionDuration = $start->diffInSeconds($end);
                $currentDeadline->addSeconds($suspensionDuration);
            }
        }

        return $currentDeadline;
    }
}
