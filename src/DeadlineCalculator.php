<?php

declare(strict_types=1);

namespace Micrositios\PqrDeadline;

use Carbon\CarbonImmutable;
use Micrositios\PqrDeadline\Calendar\BusinessCalendar;
use Micrositios\PqrDeadline\Enums\TimeUnit;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Output\DeadlineResult;
use Micrositios\PqrDeadline\Rules\DeadlineRules;
use Micrositios\PqrDeadline\Temporal\DateInput;

final class DeadlineCalculator
{
    public function calcular(CalculationRequest $solicitud): DeadlineResult
    {
        $this->checkYear($solicitud->fechaRadicacion);
        $rules = $solicitud->reglas;
        $now = CarbonImmutable::now($rules->zonaHoraria);
        $calendar = new BusinessCalendar();
        $hours = $rules->unidad === TimeUnit::H;
        $first = $hours ? $solicitud->fechaRadicacion : $this->firstDay($solicitud->fechaRadicacion, $rules, $calendar);
        $term = $solicitud->ampliarPlazo ? $rules->plazoAmpliado : $rules->plazo;
        $initial = $hours ? $this->addHours($first, $rules->plazo) : $this->dailyDeadline($first, $rules->plazo, $rules, $calendar);
        $limit = $term === $rules->plazo ? $initial : ($hours ? $this->addHours($first, $term) : $this->dailyDeadline($first, $term, $rules, $calendar));
        $notices = [];
        if ($solicitud->ampliarPlazo && !$rules->permiteAmpliacion) {
            $notices[] = ['codigo' => 'AMPLIACION_NO_PREVISTA', 'detalle' => 'Se aplicó el plazo ampliado enviado, aunque permiteAmpliacion=false. La procedencia corresponde al cliente.'];
        }
        if ($solicitud->suspensiones !== [] && !$rules->permiteSuspension) {
            $notices[] = ['codigo' => 'SUSPENSION_NO_PREVISTA', 'detalle' => 'Se aplicaron las suspensiones enviadas, aunque permiteSuspension=false. La procedencia corresponde al cliente.'];
        }

        if ($solicitud->suspensiones === []) {
            return new DeadlineResult($initial, $limit, $now, false, $notices);
        }

        $intervals = [];
        $pendingClosure = false;
        foreach ($solicitud->suspensiones as $index => $pause) {
            // Este límite excluye otras suspensiones: solo contempla el plazo base o ampliado.
            if ($pause->inicio > $limit) {
                throw new InvalidCalculationRequest('SUSPENSION_INICIO_FUERA_DE_PLAZO', 'El inicio de la suspensión supera el vencimiento original o ampliado.', ["suspensiones[$index].inicio"], ['limite' => $limit->format('Y-m-d\TH:i:s.uP')]);
            }
            $end = $pause->fin ?? $now;
            if ($end < $pause->inicio) {
                throw new InvalidCalculationRequest('SUSPENSION_INICIO_POSTERIOR_A_CONSULTA', 'Una suspensión abierta no puede estimarse con un cierre anterior a su inicio. Envíe un cierre explícito para un escenario futuro.', ["suspensiones[$index].inicio", "suspensiones[$index].fin"]);
            }
            $this->checkYear($pause->inicio);
            $this->checkYear($end);
            // Una pausa cerrada sin tiempo descontable no necesita un día de reanudación.
            if ($pause->fin !== null && ($hours
                ? $pause->inicio->equalTo($end)
                : $this->civilDay($pause->inicio, $rules)->equalTo($this->civilDay($end, $rules)))) {
                continue;
            }
            $start = $hours ? $pause->inicio : $this->pauseBoundary($pause->inicio, $rules, $calendar);
            $end = $hours ? $end : $this->pauseBoundary($end, $rules, $calendar);
            if ($pause->fin === null) {
                $pendingClosure = true;
            }
            if ($start < $end) {
                $intervals[] = [$start, $end];
            }
        }
        $intervals = $this->merge($intervals);
        if ($hours) {
            $maximum = $limit;
            foreach ($intervals as [$start, $end]) {
                if ($start < $maximum) {
                    $maximum = $this->addDuration($maximum, $end->getTimestamp() - $start->getTimestamp(),
                        (int) $end->format('u') - (int) $start->format('u'));
                }
            }
        } else {
            $maximum = $this->dailyDeadline($first, $term, $rules, $calendar, $intervals);
        }
        $this->checkYear($maximum);
        if ($pendingClosure) {
            $notices[] = ['codigo' => 'SUSPENSION_ABIERTA', 'detalle' => 'Hay suspensiones sin fecha de cierre. El cálculo utiliza fechaConsulta como cierre supuesto.'];
        }

        return new DeadlineResult($initial, $maximum, $now, $pendingClosure, $notices);
    }

    private function addHours(CarbonImmutable $date, int $hours): CarbonImmutable
    {
        $last = new CarbonImmutable('9999-12-31T23:59:59.999999', $date->getTimezone());
        if ($hours > intdiv($last->getTimestamp() - $date->getTimestamp(), 3600)) {
            throw new InvalidCalculationRequest('FECHA_RESULTADO_FUERA_DE_RANGO', 'El resultado excede las fechas representables con cuatro dígitos de año.', ['reglas.plazo', 'reglas.plazoAmpliado']);
        }

        return $this->addDuration($date, $hours * 3600);
    }

    private function addDuration(CarbonImmutable $date, int $seconds, int $microseconds = 0): CarbonImmutable
    {
        return $date->setTimezone('UTC')->addSeconds($seconds)->addMicroseconds($microseconds)->setTimezone($date->getTimezone());
    }

    private function civilDay(CarbonImmutable $date, DeadlineRules $rules): CarbonImmutable
    {
        // UTC se usa solo para enumerar fechas civiles; los resultados conservan la zona de las reglas.
        return new CarbonImmutable($date->setTimezone($rules->zonaHoraria)->format('Y-m-d') . 'T00:00:00Z');
    }

    private function firstDay(CarbonImmutable $filing, DeadlineRules $rules, BusinessCalendar $calendar): CarbonImmutable
    {
        $day = $this->civilDay($filing, $rules);

        return match ($rules->inicio) {
            'SIGUIENTE_HABIL' => $calendar->nextBusinessDay($day),
            'DIA_SIGUIENTE' => $day->addDay(),
            default => $day,
        };
    }

    private function pauseBoundary(CarbonImmutable $event, DeadlineRules $rules, BusinessCalendar $calendar): CarbonImmutable
    {
        $day = $this->civilDay($event, $rules);

        return $rules->unidad === TimeUnit::DH ? $calendar->nextBusinessDay($day) : $day->addDay();
    }

    private function dailyDeadline(CarbonImmutable $day, int $remaining, DeadlineRules $rules, BusinessCalendar $calendar, array $intervals = []): CarbonImmutable
    {
        $last = new CarbonImmutable('9999-12-31T00:00:00Z');
        if ($remaining > (int) $day->diffInDays($last, false) + 1) {
            throw new InvalidCalculationRequest('FECHA_RESULTADO_FUERA_DE_RANGO', 'El plazo no cabe en las fechas representables con cuatro dígitos de año.', ['reglas.plazo', 'reglas.plazoAmpliado']);
        }
        while (true) {
            $this->checkYear($day);
            foreach ($intervals as [$start, $end]) {
                if ($day >= $start && $day < $end) {
                    $day = $end;
                }
            }
            $this->checkYear($day);
            if ($rules->unidad !== TimeUnit::DH || $calendar->isBusinessDay($day)) {
                if (--$remaining === 0) {
                    break;
                }
            }
            $day = $day->addDay();
        }
        if ($rules->ajustarVencimientoAHabil && !$calendar->isBusinessDay($day)) {
            $day = $calendar->nextBusinessDay($day);
        }

        return DateInput::parse($day->format('Y-m-d') . 'T' . $rules->horaCierre, 'fechaMaximaRespuesta', $rules->zonaHoraria);
    }

    /** @return list<array{CarbonImmutable, CarbonImmutable}> Intervalos [inicio, fin), sin doble descuento. */
    private function merge(array $intervals): array
    {
        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                if ($end > $merged[$last][1]) {
                    $merged[$last][1] = $end;
                }
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    private function checkYear(CarbonImmutable $date): void
    {
        if ($date->year < 1 || $date->year > 9999) {
            throw new InvalidCalculationRequest('FECHA_RESULTADO_FUERA_DE_RANGO', 'El resultado excede las fechas representables con cuatro dígitos de año.', ['reglas.plazo', 'suspensiones']);
        }
    }
}
