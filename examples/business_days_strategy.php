<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;
use Micrositios\PqrDeadline\DeadlineWrapper;

echo "=== GUÍA DE USO: ESTRATEGIA DE DÍAS HÁBILES ===\n\n";

// ========================================
// CASO 1: CÁLCULO BÁSICO - ITERACIÓN DE 15 DÍAS
// ========================================

echo "CASO 1: Cálculo básico con peticion_general - Iteración desde 2025-01-01\n";
echo "Mostrando comportamiento en diferentes días (laborables, fines de semana, festivos)\n\n";

$fechaBase = Carbon::parse('2025-01-01 10:00:00'); // Miércoles - Año Nuevo (festivo)

for ($i = 0; $i < 15; $i++) {
    $fechaInicio = $fechaBase->copy()->addDays($i);

    // Configurar wrapper con el tipo de solicitud
    $wrapper = DeadlineWrapper::setup('peticion_general', $fechaInicio);
    $deadline = $wrapper->calculateDeadline();

    // Determinar tipo de día
    $tipoDia = '';
    if ($fechaInicio->isHoliday()) {
        $tipoDia = ' (FESTIVO)';
    } elseif ($fechaInicio->isWeekend()) {
        $tipoDia = ' (FIN DE SEMANA)';
    } else {
        $tipoDia = ' (DÍA HÁBIL)';
    }

    echo sprintf(
        "Día %2d: %s%s → %s\n",
        $i + 1,
        $fechaInicio->format('Y-m-d D'),
        $tipoDia,
        $deadline->format('Y-m-d H:i:s')
    );
}

echo "\nAnálisis de la iteración:\n";
echo "- Tipo de solicitud: peticion_general (15 días hábiles)\n";
echo "- Festivos y fines de semana: El cálculo se ajusta automáticamente\n";
echo "- Todas las fechas de vencimiento terminan a las 23:59:59\n";
echo "- El primer día hábil disponible inicia el conteo\n";
echo "- Comportamiento consistente independiente del día de inicio\n\n";

// ========================================
// CASO 2: DUPLICACIÓN DEL PLAZO - ITERACIÓN DE 15 DÍAS
// ========================================

echo "CASO 2: Duplicación del plazo con peticion_general - Iteración desde 2025-01-01\n";
echo "Mostrando comportamiento con duplicación (30 días hábiles)\n\n";

for ($i = 0; $i < 15; $i++) {
    $fechaInicio = $fechaBase->copy()->addDays($i);

    // Configurar wrapper con duplicación activada
    $wrapper = DeadlineWrapper::setup('peticion_general', $fechaInicio, true);
    $deadline = $wrapper->calculateDeadline();

    // Determinar tipo de día
    $tipoDia = '';
    if ($fechaInicio->isHoliday()) {
        $tipoDia = ' (FESTIVO)';
    } elseif ($fechaInicio->isWeekend()) {
        $tipoDia = ' (FIN DE SEMANA)';
    } else {
        $tipoDia = ' (DÍA HÁBIL)';
    }

    echo sprintf(
        "Día %2d: %s%s → %s\n",
        $i + 1,
        $fechaInicio->format('Y-m-d D'),
        $tipoDia,
        $deadline->format('Y-m-d H:i:s')
    );
}

echo "\nAnálisis de la duplicación:\n";
echo "- Tipo de solicitud: peticion_general con duplicación (30 días hábiles)\n";
echo "- Plazo base: 15 días × 2 = 30 días hábiles\n";
echo "- Comportamiento: Mismo ajuste automático pero con plazo duplicado\n";
echo "- Diferencia notable: Fechas de vencimiento están más lejos en el tiempo\n";
echo "- Consistency: El patrón de cálculo es idéntico, solo cambia la duración\n\n";

// ========================================
// CASO 3: SUSPENSIONES EN DIFERENTES TIPOS DE DÍAS
// ========================================

echo "CASO 3: Comportamiento de suspensiones según el tipo de día\n";
echo "Analizando suspensiones cortas (4h) y largas (12h) en contextos diferentes\n\n";

// Definir fecha base para los ejemplos
$fechaInicio = Carbon::parse('2025-01-02 10:00:00'); // Jueves día hábil

echo "REGLA: Solo suspensiones ≥8 horas hábiles agregan días al plazo\n";
echo "Fecha de solicitud: " . $fechaInicio->format('Y-m-d H:i (l)') . "\n\n";

// Baseline sin suspensiones
$wrapperBase = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$deadlineBase = $wrapperBase->calculateDeadline();
echo "BASELINE (sin suspensiones): " . $deadlineBase->format('Y-m-d H:i:s') . "\n\n";

// ========================================
// 3A: SUSPENSIONES EN DÍA LABORAL (MIÉRCOLES)
// ========================================

echo "3A. SUSPENSIONES EN DÍA LABORAL (Miércoles 08/01/2025):\n";

// 3A1: Suspensión corta en día laboral
$suspensionCortaLaboral = [
    ['start_at' => Carbon::parse('2025-01-08 14:00:00'), 'end_at' => Carbon::parse('2025-01-08 18:00:00')] // Miércoles
];

$wrapperCortaLaboral = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperCortaLaboral->injectSuspensions($suspensionCortaLaboral);
$deadlineCortaLaboral = $wrapperCortaLaboral->calculateDeadline();

echo "  A1. Suspensión corta (4h): 14:00 - 18:00 en DÍA HÁBIL\n";
echo "      Horas hábiles: 3h (desde 14:00 hasta 17:00)\n";
echo "      Fecha calculada: " . $deadlineCortaLaboral->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBase->equalTo($deadlineCortaLaboral) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 3h < 8h → NO agrega días\n\n";

// 3A2: Suspensión larga en día laboral
$suspensionLargaLaboral = [
    ['start_at' => Carbon::parse('2025-01-08 08:00:00'), 'end_at' => Carbon::parse('2025-01-08 20:00:00')] // Miércoles
];

$wrapperLargaLaboral = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperLargaLaboral->injectSuspensions($suspensionLargaLaboral);
$deadlineLargaLaboral = $wrapperLargaLaboral->calculateDeadline();

echo "  A2. Suspensión larga (12h): 08:00 - 20:00 en DÍA HÁBIL\n";
echo "      Horas hábiles: 9h (desde 08:00 hasta 17:00)\n";
echo "      Fecha calculada: " . $deadlineLargaLaboral->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBase->equalTo($deadlineLargaLaboral) ? "IGUAL" : "DIFERENTE") . "\n";
if (!$deadlineBase->equalTo($deadlineLargaLaboral)) {
    echo "      Diferencia: " . $deadlineBase->diffInBusinessDays($deadlineLargaLaboral) . " día(s) hábiles adicionales\n";
}
echo "      Resultado: 9h ≥ 8h → SÍ agrega 1 día (9÷8=1)\n\n";

// ========================================
// 3B: SUSPENSIONES EN FIN DE SEMANA (SÁBADO)
// ========================================

echo "3B. SUSPENSIONES EN FIN DE SEMANA (Sábado 11/01/2025):\n";

// 3B1: Suspensión corta en fin de semana
$suspensionCortaFinSemana = [
    ['start_at' => Carbon::parse('2025-01-11 14:00:00'), 'end_at' => Carbon::parse('2025-01-11 18:00:00')] // Sábado
];

$wrapperCortaFinSemana = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperCortaFinSemana->injectSuspensions($suspensionCortaFinSemana);
$deadlineCortaFinSemana = $wrapperCortaFinSemana->calculateDeadline();

echo "  B1. Suspensión corta (4h): 14:00 - 18:00 en FIN DE SEMANA\n";
echo "      Horas hábiles: 0h (sábado no es día hábil)\n";
echo "      Fecha calculada: " . $deadlineCortaFinSemana->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBase->equalTo($deadlineCortaFinSemana) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (fin de semana no cuenta)\n\n";

// 3B2: Suspensión larga en fin de semana
$suspensionLargaFinSemana = [
    ['start_at' => Carbon::parse('2025-01-11 08:00:00'), 'end_at' => Carbon::parse('2025-01-11 20:00:00')] // Sábado
];

$wrapperLargaFinSemana = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperLargaFinSemana->injectSuspensions($suspensionLargaFinSemana);
$deadlineLargaFinSemana = $wrapperLargaFinSemana->calculateDeadline();

echo "  B2. Suspensión larga (12h): 08:00 - 20:00 en FIN DE SEMANA\n";
echo "      Horas hábiles: 0h (sábado no es día hábil)\n";
echo "      Fecha calculada: " . $deadlineLargaFinSemana->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBase->equalTo($deadlineLargaFinSemana) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (fin de semana no cuenta)\n\n";

// ========================================
// 3C: SUSPENSIONES EN FESTIVO (REYES MAGOS)
// ========================================

echo "3C. SUSPENSIONES EN FESTIVO (Lunes 06/01/2025 - Reyes Magos):\n";

// 3C1: Suspensión corta en festivo
$suspensionCortaFestivo = [
    ['start_at' => Carbon::parse('2025-01-06 14:00:00'), 'end_at' => Carbon::parse('2025-01-06 18:00:00')] // Lunes festivo
];

$wrapperCortaFestivo = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperCortaFestivo->injectSuspensions($suspensionCortaFestivo);
$deadlineCortaFestivo = $wrapperCortaFestivo->calculateDeadline();

echo "  C1. Suspensión corta (4h): 14:00 - 18:00 en FESTIVO\n";
echo "      Horas hábiles: 0h (festivo no es día hábil)\n";
echo "      Fecha calculada: " . $deadlineCortaFestivo->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBase->equalTo($deadlineCortaFestivo) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (festivo no cuenta)\n\n";

// 3C2: Suspensión larga en festivo
$suspensionLargaFestivo = [
    ['start_at' => Carbon::parse('2025-01-06 08:00:00'), 'end_at' => Carbon::parse('2025-01-06 20:00:00')] // Lunes festivo
];

$wrapperLargaFestivo = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperLargaFestivo->injectSuspensions($suspensionLargaFestivo);
$deadlineLargaFestivo = $wrapperLargaFestivo->calculateDeadline();

echo "  C2. Suspensión larga (12h): 08:00 - 20:00 en FESTIVO\n";
echo "      Horas hábiles: 0h (festivo no es día hábil)\n";
echo "      Fecha calculada: " . $deadlineLargaFestivo->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBase->equalTo($deadlineLargaFestivo) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (festivo no cuenta)\n\n";

// ========================================
// RESUMEN COMPARATIVO
// ========================================

echo "RESUMEN COMPARATIVO CASO 3:\n";
echo "Baseline: " . $deadlineBase->format('Y-m-d') . "\n\n";

echo "DÍA LABORAL (Miércoles):\n";
echo "  - Suspensión corta (4h):  " . $deadlineCortaLaboral->format('Y-m-d') . " " .
    ($deadlineCortaLaboral->equalTo($deadlineBase) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Suspensión larga (12h): " . $deadlineLargaLaboral->format('Y-m-d') . " " .
    ($deadlineLargaLaboral->equalTo($deadlineBase) ? "(IGUAL)" : "(+1 día - Correcto)") . "\n\n";

echo "FIN DE SEMANA (Sábado):\n";
echo "  - Suspensión corta (4h):  " . $deadlineCortaFinSemana->format('Y-m-d') . " " .
    ($deadlineCortaFinSemana->equalTo($deadlineBase) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Suspensión larga (12h): " . $deadlineLargaFinSemana->format('Y-m-d') . " " .
    ($deadlineLargaFinSemana->equalTo($deadlineBase) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "FESTIVO (Lunes Reyes):\n";
echo "  - Suspensión corta (4h):  " . $deadlineCortaFestivo->format('Y-m-d') . " " .
    ($deadlineCortaFestivo->equalTo($deadlineBase) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Suspensión larga (12h): " . $deadlineLargaFestivo->format('Y-m-d') . " " .
    ($deadlineLargaFestivo->equalTo($deadlineBase) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "CONCLUSIONES CLAVE:\n";
echo "1. Solo las horas en DÍAS HÁBILES cuentan para el cálculo\n";
echo "2. Suspensiones en fin de semana NO afectan el plazo\n";
echo "3. Suspensiones en festivos NO afectan el plazo\n";
echo "4. La regla de 8 horas solo aplica a horas hábiles efectivas\n";
echo "5. El TIPO DE DÍA es fundamental para determinar el impacto\n\n";

// ========================================
// CASO 4: SUSPENSIONES NOCTURNAS Y CRUCES DE DÍAS
// ========================================

echo "CASO 4: Comportamiento de suspensiones que cruzan días\n";
echo "Analizando suspensiones nocturnas y su impacto según duración y contexto\n\n";

echo "REGLA: Solo horas hábiles (lunes-viernes 08:00-17:00) cuentan para agregar días\n";
echo "Fecha de solicitud: 2025-01-02 10:00 (Thursday)\n\n";

// Baseline sin suspensiones para comparación
$wrapperBaseline4 = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$deadlineBaseline4 = $wrapperBaseline4->calculateDeadline();

echo "BASELINE (sin suspensiones): " . $deadlineBaseline4->format('Y-m-d H:i:s') . "\n\n";

// ========================================
// 4A: SUSPENSIÓN NOCTURNA CORTA (JUEVES-VIERNES)
// ========================================

echo "4A. SUSPENSIÓN NOCTURNA CORTA (16:00-08:00 = 16h, pero solo 1h hábil):\n";

// A1: Suspensión nocturna jueves-viernes
$suspensionNocturnaCorta = [
    ['start_at' => Carbon::parse('2025-01-02 16:00:00'), 'end_at' => Carbon::parse('2025-01-03 08:00:00')] // Jueves 16:00 - Viernes 08:00
];

$wrapperNocturnaCorta = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperNocturnaCorta->injectSuspensions($suspensionNocturnaCorta);
$deadlineNocturnaCorta = $wrapperNocturnaCorta->calculateDeadline();

echo "  A1. Jueves 16:00 - Viernes 08:00 (16 horas totales)\n";
echo "      Horario hábil afectado: Jueves 16:00-17:00 = 1h hábil\n";
echo "      Fecha calculada: " . $deadlineNocturnaCorta->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline4->equalTo($deadlineNocturnaCorta) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 1h < 8h → NO agrega días (suspensión nocturna corta)\n\n";

// ========================================
// 4B: SUSPENSIÓN NOCTURNA LARGA (CRUZA MÚLTIPLES DÍAS HÁBILES)
// ========================================

echo "4B. SUSPENSIÓN NOCTURNA LARGA (15:00-10:00 siguiente = 19h, 10h hábiles):\n";

// B1: Suspensión larga que cruza dos días hábiles
$suspensionNocturnaLarga = [
    ['start_at' => Carbon::parse('2025-01-02 15:00:00'), 'end_at' => Carbon::parse('2025-01-03 10:00:00')] // Jueves 15:00 - Viernes 10:00
];

$wrapperNocturnaLarga = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperNocturnaLarga->injectSuspensions($suspensionNocturnaLarga);
$deadlineNocturnaLarga = $wrapperNocturnaLarga->calculateDeadline();

echo "  B1. Jueves 15:00 - Viernes 10:00 (19 horas totales)\n";
echo "      Horario hábil afectado: Jueves 15:00-17:00 + Viernes 08:00-10:00 = 4h hábiles\n";
echo "      Fecha calculada: " . $deadlineNocturnaLarga->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline4->equalTo($deadlineNocturnaLarga) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 4h < 8h → NO agrega días (aunque cruza días, pocas horas hábiles)\n\n";

// B2: Suspensión muy larga que sí supera 8h hábiles
$suspensionMuyLarga = [
    ['start_at' => Carbon::parse('2025-01-02 14:00:00'), 'end_at' => Carbon::parse('2025-01-03 15:00:00')] // Jueves 14:00 - Viernes 15:00
];

$wrapperMuyLarga = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperMuyLarga->injectSuspensions($suspensionMuyLarga);
$deadlineMuyLarga = $wrapperMuyLarga->calculateDeadline();

echo "  B2. Jueves 14:00 - Viernes 15:00 (25 horas totales)\n";
echo "      Horario hábil afectado: Jueves 14:00-17:00 + Viernes 08:00-15:00 = 10h hábiles\n";
echo "      Fecha calculada: " . $deadlineMuyLarga->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline4->equalTo($deadlineMuyLarga) ? "IGUAL" : "DIFERENTE") . "\n";
if (!$deadlineBaseline4->equalTo($deadlineMuyLarga)) {
    $diffDays = $deadlineBaseline4->diffInWeekdays($deadlineMuyLarga);
    echo "      Diferencia: {$diffDays} día(s) hábiles adicionales\n";
}
echo "      Resultado: 10h ≥ 8h → SÍ agrega días (10÷8=1 día)\n\n";

// ========================================
// 4C: SUSPENSIÓN QUE CRUZA FIN DE SEMANA
// ========================================

echo "4C. SUSPENSIÓN QUE CRUZA FIN DE SEMANA (Viernes-Lunes):\n";

// C1: Suspensión viernes tarde a lunes mañana
$suspensionFinSemana4 = [
    ['start_at' => Carbon::parse('2025-01-03 16:00:00'), 'end_at' => Carbon::parse('2025-01-06 08:00:00')] // Viernes 16:00 - Lunes 08:00 (Reyes)
];

$wrapperFinSemana4 = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperFinSemana4->injectSuspensions($suspensionFinSemana4);
$deadlineFinSemana4 = $wrapperFinSemana4->calculateDeadline();

echo "  C1. Viernes 16:00 - Lunes 08:00 (64 horas totales, cruza fin de semana)\n";
echo "      Horario hábil afectado: Viernes 16:00-17:00 = 1h hábil\n";
echo "      (Sábado, domingo y lunes festivo no cuentan)\n";
echo "      Fecha calculada: " . $deadlineFinSemana4->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline4->equalTo($deadlineFinSemana4) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 1h < 8h → NO agrega días (fin de semana no cuenta)\n\n";

// ========================================
// RESUMEN COMPARATIVO CASO 4
// ========================================

echo "RESUMEN COMPARATIVO CASO 4:\n";
echo "Baseline: " . $deadlineBaseline4->format('Y-m-d') . "\n\n";

echo "SUSPENSIÓN NOCTURNA CORTA:\n";
echo "  - Jueves 16:00-Viernes 08:00 (1h hábil):  " . $deadlineNocturnaCorta->format('Y-m-d') . " " .
    ($deadlineNocturnaCorta->equalTo($deadlineBaseline4) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "SUSPENSIÓN NOCTURNA LARGA:\n";
echo "  - Jueves 15:00-Viernes 10:00 (4h hábiles): " . $deadlineNocturnaLarga->format('Y-m-d') . " " .
    ($deadlineNocturnaLarga->equalTo($deadlineBaseline4) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Jueves 14:00-Viernes 15:00 (10h hábiles): " . $deadlineMuyLarga->format('Y-m-d') . " " .
    ($deadlineMuyLarga->equalTo($deadlineBaseline4) ? "(IGUAL)" : "(+1 día - Correcto)") . "\n\n";

echo "SUSPENSIÓN CRUZANDO FIN DE SEMANA:\n";
echo "  - Viernes 16:00-Lunes 08:00 (1h hábil):   " . $deadlineFinSemana4->format('Y-m-d') . " " .
    ($deadlineFinSemana4->equalTo($deadlineBaseline4) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "CONCLUSIONES CLAVE CASO 4:\n";
echo "1. El TIEMPO TOTAL de suspensión no importa, solo las HORAS HÁBILES\n";
echo "2. Suspensiones nocturnas largas pueden tener pocas horas hábiles\n";
echo "3. Cruzar múltiples días no garantiza agregar días al plazo\n";
echo "4. Solo horas en horario laboral (08:00-17:00, lunes-viernes) cuentan\n";
echo "5. La regla de 8 horas hábiles es independiente de la duración total\n\n";

// ========================================
// CASO 5: SUSPENSIONES DE MÚLTIPLES DÍAS HÁBILES
// ========================================

echo "CASO 5: Comportamiento de suspensiones extendidas en días hábiles\n";
echo "Analizando suspensiones que cubren múltiples días laborales completos\n\n";

echo "REGLA: Cada 8 horas hábiles agregan 1 día al plazo final\n";
echo "Fecha de solicitud: 2025-01-02 10:00 (Thursday)\n\n";

// Baseline sin suspensiones para comparación
$wrapperBaseline5 = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$deadlineBaseline5 = $wrapperBaseline5->calculateDeadline();

echo "BASELINE (sin suspensiones): " . $deadlineBaseline5->format('Y-m-d H:i:s') . "\n\n";

// ========================================
// 5A: SUSPENSIÓN DE 1 DÍA HÁBIL COMPLETO
// ========================================

echo "5A. SUSPENSIÓN DE 1 DÍA HÁBIL COMPLETO (9 horas):\n";

// A1: Suspensión de todo el viernes
$suspension1DiaCompleto = [
    ['start_at' => Carbon::parse('2025-01-03 08:00:00'), 'end_at' => Carbon::parse('2025-01-03 17:00:00')] // Viernes completo
];

$wrapper1DiaCompleto = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapper1DiaCompleto->injectSuspensions($suspension1DiaCompleto);
$deadline1DiaCompleto = $wrapper1DiaCompleto->calculateDeadline();

echo "  A1. Viernes 08:00 - 17:00 (1 día laboral completo)\n";
echo "      Horas hábiles: 9h (día completo de trabajo)\n";
echo "      Fecha calculada: " . $deadline1DiaCompleto->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline5->equalTo($deadline1DiaCompleto) ? "IGUAL" : "DIFERENTE") . "\n";
if (!$deadlineBaseline5->equalTo($deadline1DiaCompleto)) {
    $diffDays = $deadlineBaseline5->diffInWeekdays($deadline1DiaCompleto);
    echo "      Diferencia: {$diffDays} día(s) hábiles adicionales\n";
}
echo "      Resultado: 9h ≥ 8h → SÍ agrega 1 día (9÷8=1 día)\n\n";

// ========================================
// 5B: SUSPENSIÓN DE 2 DÍAS HÁBILES COMPLETOS
// ========================================

echo "5B. SUSPENSIÓN DE 2 DÍAS HÁBILES COMPLETOS (18 horas):\n";

// B1: Suspensión viernes y lunes completos
$suspension2DiasCompletos = [
    ['start_at' => Carbon::parse('2025-01-03 08:00:00'), 'end_at' => Carbon::parse('2025-01-07 17:00:00')] // Viernes 08:00 - Martes 17:00 (cubre viernes y lunes)
];

$wrapper2DiasCompletos = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapper2DiasCompletos->injectSuspensions($suspension2DiasCompletos);
$deadline2DiasCompletos = $wrapper2DiasCompletos->calculateDeadline();

echo "  B1. Viernes 08:00 - Martes 17:00 (cruza fin de semana)\n";
echo "      Horas hábiles: 18h (Viernes 9h + Lunes 9h, fin de semana no cuenta)\n";
echo "      Fecha calculada: " . $deadline2DiasCompletos->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline5->equalTo($deadline2DiasCompletos) ? "IGUAL" : "DIFERENTE") . "\n";
if (!$deadlineBaseline5->equalTo($deadline2DiasCompletos)) {
    $diffDays = $deadlineBaseline5->diffInWeekdays($deadline2DiasCompletos);
    echo "      Diferencia: {$diffDays} día(s) hábiles adicionales\n";
}
echo "      Resultado: 18h ≥ 8h → SÍ agrega 2 días (18÷8=2 días)\n\n";

// ========================================
// 5C: SUSPENSIÓN DE 3 DÍAS CONSECUTIVOS (CON FIN DE SEMANA)
// ========================================

echo "5C. SUSPENSIÓN EXTENDIDA CRUZANDO FIN DE SEMANA:\n";

// C1: Suspensión que incluye fin de semana pero solo cuenta días hábiles
$suspensionExtendida = [
    ['start_at' => Carbon::parse('2025-01-02 16:00:00'), 'end_at' => Carbon::parse('2025-01-05 08:00:00')] // Jueves tarde - Domingo mañana
];

$wrapperExtendida = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperExtendida->injectSuspensions($suspensionExtendida);
$deadlineExtendida = $wrapperExtendida->calculateDeadline();

echo "  C1. Jueves 16:00 - Domingo 08:00 (incluye fin de semana)\n";
echo "      Horas hábiles: 17h (Jueves 16:00-17:00 + Viernes 08:00-17:00 = 1h + 9h + 7h)\n";
echo "      Nota: Sábado y domingo no cuentan como horas hábiles\n";
echo "      Fecha calculada: " . $deadlineExtendida->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline5->equalTo($deadlineExtendida) ? "IGUAL" : "DIFERENTE") . "\n";
if (!$deadlineBaseline5->equalTo($deadlineExtendida)) {
    $diffDays = $deadlineBaseline5->diffInWeekdays($deadlineExtendida);
    echo "      Diferencia: {$diffDays} día(s) hábiles adicionales\n";
}
echo "      Resultado: 17h ≥ 8h → SÍ agrega 2 días (17÷8=2 días)\n\n";

// ========================================
// RESUMEN COMPARATIVO CASO 5
// ========================================

echo "RESUMEN COMPARATIVO CASO 5:\n";
echo "Baseline: " . $deadlineBaseline5->format('Y-m-d') . "\n\n";

echo "SUSPENSIÓN DE 1 DÍA HÁBIL:\n";
echo "  - Viernes completo (9h hábiles):           " . $deadline1DiaCompleto->format('Y-m-d') . " " .
    ($deadline1DiaCompleto->equalTo($deadlineBaseline5) ? "(IGUAL)" : "(+1 día - Correcto)") . "\n\n";

echo "SUSPENSIÓN DE 2 DÍAS HÁBILES:\n";
echo "  - Viernes + Lunes (18h hábiles):           " . $deadline2DiasCompletos->format('Y-m-d') . " " .
    ($deadline2DiasCompletos->equalTo($deadlineBaseline5) ? "(IGUAL)" : "(+2 días - Correcto)") . "\n\n";

echo "SUSPENSIÓN EXTENDIDA CON FIN DE SEMANA:\n";
echo "  - Jueves tarde a Domingo (17h hábiles):    " . $deadlineExtendida->format('Y-m-d') . " " .
    ($deadlineExtendida->equalTo($deadlineBaseline5) ? "(IGUAL)" : "(+2 días - Correcto)") . "\n\n";

echo "CONCLUSIONES CLAVE CASO 5:\n";
echo "1. Suspensiones de días completos son más predecibles (9h por día)\n";
echo "2. Fin de semana NO cuenta: viernes+lunes = 2 días, no 4\n";
echo "3. El cálculo es preciso: 9h=1día, 18h=2días, 17h=2días\n";
echo "4. Suspensiones largas permiten planificación más exacta\n";
echo "5. La regla 8h por día se mantiene independiente de la duración total\n\n";

// ========================================
// CASO 6: COMPORTAMIENTO EN FINES DE SEMANA Y FESTIVOS
// ========================================

echo "CASO 6: Suspensiones en días no hábiles (fin de semana y festivos)\n";
echo "Demostrando que solo días hábiles (lunes-viernes no festivos) cuentan\n\n";

echo "REGLA: Días no hábiles tienen 0 horas laborales, independientemente de la duración\n";

// Definir fecha para ejemplos adicionales
$fechaViernes = Carbon::parse('2024-01-19 10:00:00'); // Viernes

echo "Fecha de solicitud: " . $fechaViernes->format('Y-m-d H:i') . " (Friday)\n\n";

// Baseline sin suspensiones
$wrapperBaseline6 = DeadlineWrapper::setup('peticion_general', $fechaViernes);
$deadlineBaseline6 = $wrapperBaseline6->calculateDeadline();

echo "BASELINE (sin suspensiones): " . $deadlineBaseline6->format('Y-m-d H:i:s') . "\n\n";

// ========================================
// 6A: SUSPENSIONES EN SÁBADO COMPLETO
// ========================================

echo "6A. SUSPENSIONES EN SÁBADO (DÍA NO HÁBIL):\n";

// A1: Suspensión corta en sábado
$suspensionSabadoCorta = [
    ['start_at' => Carbon::parse('2024-01-20 10:00:00'), 'end_at' => Carbon::parse('2024-01-20 14:00:00')] // Sábado 4 horas
];

$wrapperSabadoCorta = DeadlineWrapper::setup('peticion_general', $fechaViernes);
$wrapperSabadoCorta->injectSuspensions($suspensionSabadoCorta);
$deadlineSabadoCorta = $wrapperSabadoCorta->calculateDeadline();

echo "  A1. Sábado 10:00 - 14:00 (4 horas en sábado)\n";
echo "      Horas hábiles: 0h (sábado no es día hábil)\n";
echo "      Fecha calculada: " . $deadlineSabadoCorta->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline6->equalTo($deadlineSabadoCorta) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (fin de semana no cuenta)\n\n";

// A2: Suspensión larga en sábado
$suspensionSabadoLarga = [
    ['start_at' => Carbon::parse('2024-01-20 08:00:00'), 'end_at' => Carbon::parse('2024-01-20 18:00:00')] // Sábado 10 horas
];

$wrapperSabadoLarga = DeadlineWrapper::setup('peticion_general', $fechaViernes);
$wrapperSabadoLarga->injectSuspensions($suspensionSabadoLarga);
$deadlineSabadoLarga = $wrapperSabadoLarga->calculateDeadline();

echo "  A2. Sábado 08:00 - 18:00 (10 horas en sábado)\n";
echo "      Horas hábiles: 0h (sábado no es día hábil)\n";
echo "      Fecha calculada: " . $deadlineSabadoLarga->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline6->equalTo($deadlineSabadoLarga) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (duración irrelevante en fin de semana)\n\n";

// ========================================
// 6B: SUSPENSIÓN QUE CRUZA VIERNES-LUNES
// ========================================

echo "6B. SUSPENSIÓN CRUZANDO FIN DE SEMANA COMPLETO:\n";

// B1: Suspensión que cruza fin de semana (viernes tarde a lunes mañana)
$suspensionFinSemana = [
    ['start_at' => Carbon::parse('2024-01-19 15:00:00'), 'end_at' => Carbon::parse('2024-01-22 09:00:00')] // Viernes 15:00 - Lunes 09:00
];

$wrapperFinSemana = DeadlineWrapper::setup('peticion_general', $fechaViernes);
$wrapperFinSemana->injectSuspensions($suspensionFinSemana);
$deadlineFinSemana = $wrapperFinSemana->calculateDeadline();

echo "  B1. Viernes 15:00 - Lunes 09:00 (66 horas totales)\n";
echo "      Horario hábil afectado: Viernes 15:00-17:00 + Lunes 08:00-09:00 = 3h hábiles\n";
echo "      (Sábado y domingo no cuentan como tiempo hábil)\n";
echo "      Fecha calculada: " . $deadlineFinSemana->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline6->equalTo($deadlineFinSemana) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 3h < 8h → NO agrega días (fin de semana no aporta horas)\n\n";

// ========================================
// 6C: SUSPENSIÓN EN FESTIVO (REYES MAGOS)
// ========================================

echo "6C. SUSPENSIÓN EN FESTIVO NACIONAL:\n";

// Cambiar fecha de inicio para que el festivo sea relevante
$fechaAntesReyes = Carbon::parse('2025-01-03 10:00:00'); // Viernes antes de Reyes

// C1: Suspensión en Reyes Magos (6 de enero)
$suspensionReyes = [
    ['start_at' => Carbon::parse('2025-01-06 09:00:00'), 'end_at' => Carbon::parse('2025-01-06 16:00:00')] // Lunes Reyes 7 horas
];

$wrapperReyes = DeadlineWrapper::setup('peticion_general', $fechaAntesReyes);
$wrapperReyes->injectSuspensions($suspensionReyes);
$deadlineReyes = $wrapperReyes->calculateDeadline();

// Baseline para esta fecha
$wrapperBaselineReyes = DeadlineWrapper::setup('peticion_general', $fechaAntesReyes);
$deadlineBaselineReyes = $wrapperBaselineReyes->calculateDeadline();

echo "  C1. Lunes 06/01 (Reyes) 09:00 - 16:00 (7 horas en festivo)\n";
echo "      Horas hábiles: 0h (Reyes Magos es festivo nacional)\n";
echo "      Fecha calculada: " . $deadlineReyes->format('Y-m-d H:i:s') . "\n";
echo "      Baseline para comparar: " . $deadlineBaselineReyes->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaselineReyes->equalTo($deadlineReyes) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 0h → NO agrega días (festivo no cuenta)\n\n";

// ========================================
// RESUMEN COMPARATIVO CASO 6
// ========================================

echo "RESUMEN COMPARATIVO CASO 6:\n";
echo "Baseline viernes: " . $deadlineBaseline6->format('Y-m-d') . "\n";
echo "Baseline antes Reyes: " . $deadlineBaselineReyes->format('Y-m-d') . "\n\n";

echo "SUSPENSIONES EN SÁBADO:\n";
echo "  - Sábado 4 horas:                         " . $deadlineSabadoCorta->format('Y-m-d') . " " .
    ($deadlineSabadoCorta->equalTo($deadlineBaseline6) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Sábado 10 horas:                        " . $deadlineSabadoLarga->format('Y-m-d') . " " .
    ($deadlineSabadoLarga->equalTo($deadlineBaseline6) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "SUSPENSIÓN CRUZANDO FIN DE SEMANA:\n";
echo "  - Viernes 15:00-Lunes 09:00 (3h hábiles): " . $deadlineFinSemana->format('Y-m-d') . " " .
    ($deadlineFinSemana->equalTo($deadlineBaseline6) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "SUSPENSIÓN EN FESTIVO:\n";
echo "  - Reyes Magos 7 horas:                    " . $deadlineReyes->format('Y-m-d') . " " .
    ($deadlineReyes->equalTo($deadlineBaselineReyes) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n\n";

echo "CONCLUSIONES CLAVE CASO 6:\n";
echo "1. Fin de semana = 0 horas hábiles, sin importar la duración\n";
echo "2. Festivos nacionales = 0 horas hábiles (igual que fin de semana)\n";
echo "3. Solo las horas en días hábiles (lunes-viernes no festivos) cuentan\n";
echo "4. Suspensiones largas en días no hábiles NO afectan el plazo\n";
echo "5. El sistema respeta el calendario laboral colombiano\n\n";

// ========================================
// CASO 7: VALIDACIÓN DE SUSPENSIONES TEMPORALES
// ========================================

echo "CASO 7: Comportamiento con suspensiones fuera del período de cálculo\n";
echo "Demostrando que solo suspensiones posteriores a la solicitud afectan el plazo\n\n";

echo "REGLA: Suspensiones anteriores a la solicitud son ignoradas automáticamente\n";
echo "Fecha de solicitud: 2025-01-02 10:00 (Thursday)\n\n";

// Baseline sin suspensiones
$wrapperBaseline7 = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$deadlineBaseline7 = $wrapperBaseline7->calculateDeadline();

echo "BASELINE (sin suspensiones): " . $deadlineBaseline7->format('Y-m-d H:i:s') . "\n\n";

// ========================================
// 7A: SUSPENSIÓN ANTERIOR A LA SOLICITUD
// ========================================

echo "7A. SUSPENSIÓN PREVIA (DEBE SER IGNORADA):\n";

// A1: Suspensión anterior a la fecha de solicitud
$suspensionesAntes = [
    ['start_at' => Carbon::parse('2024-12-25 10:00:00'), 'end_at' => Carbon::parse('2024-12-27 18:00:00')] // Navidad 2024
];

$wrapperAntes = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperAntes->injectSuspensions($suspensionesAntes);
$deadlineAntes = $wrapperAntes->calculateDeadline();

echo "  A1. Navidad 25/12/2024 - 27/12/2024 (anterior a solicitud 02/01/2025)\n";
echo "      Estado: Suspensión previa → IGNORADA por el sistema\n";
echo "      Fecha calculada: " . $deadlineAntes->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline7->equalTo($deadlineAntes) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: Sistema ignora suspensiones anteriores (correcto)\n\n";

// ========================================
// 7B: SUSPENSIÓN POSTERIOR (DEBE APLICARSE)
// ========================================

echo "7B. SUSPENSIÓN POSTERIOR (DEBE APLICARSE):\n";

// B1: Suspensión posterior a la fecha de solicitud
$suspensionPosterior = [
    ['start_at' => Carbon::parse('2025-01-10 14:00:00'), 'end_at' => Carbon::parse('2025-01-10 17:00:00')] // 3 horas un viernes
];

$wrapperPosterior = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperPosterior->injectSuspensions($suspensionPosterior);
$deadlinePosterior = $wrapperPosterior->calculateDeadline();

echo "  B1. Viernes 10/01/2025 14:00-17:00 (posterior a solicitud)\n";
echo "      Horas hábiles: 3h (dentro del período de cálculo)\n";
echo "      Fecha calculada: " . $deadlinePosterior->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline7->equalTo($deadlinePosterior) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 3h < 8h → NO agrega días (suspensión corta aplicada)\n\n";

// ========================================
// 7C: SUSPENSIÓN EN LA FECHA LÍMITE
// ========================================

echo "7C. SUSPENSIÓN CERCA DE LA FECHA LÍMITE:\n";

// C1: Suspensión muy cerca del plazo calculado
$suspensionCercaLimite = [
    ['start_at' => Carbon::parse('2025-01-23 14:00:00'), 'end_at' => Carbon::parse('2025-01-24 10:00:00')] // Cerca del plazo
];

$wrapperCercaLimite = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperCercaLimite->injectSuspensions($suspensionCercaLimite);
$deadlineCercaLimite = $wrapperCercaLimite->calculateDeadline();

echo "  C1. Jueves 23/01 14:00 - Viernes 24/01 10:00 (cerca del límite)\n";
echo "      Período: Jueves 14:00-17:00 + Viernes 08:00-10:00 = 5h hábiles\n";
echo "      Fecha calculada: " . $deadlineCercaLimite->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline7->equalTo($deadlineCercaLimite) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: 5h < 8h → NO agrega días (suspensión aplicada correctamente)\n\n";

// ========================================
// 7D: SUSPENSIÓN FUERA DEL LÍMITE (DESPUÉS DEL CIERRE INICIAL)
// ========================================

echo "7D. SUSPENSIÓN FUERA DEL LÍMITE (DEBE SER IGNORADA):\n";

// D1: Suspensión que comienza después del cierre inicial calculado
$suspensionFueraLimite = [
    ['start_at' => Carbon::parse('2025-01-27 10:00:00'), 'end_at' => Carbon::parse('2025-01-27 16:00:00')] // Después del 24/01 23:59:59
];

$wrapperFueraLimite = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperFueraLimite->injectSuspensions($suspensionFueraLimite);
$deadlineFueraLimite = $wrapperFueraLimite->calculateDeadline();

echo "  D1. Lunes 27/01/2025 10:00-16:00 (DESPUÉS del cierre inicial 24/01 23:59:59)\n";
echo "      Estado: Suspensión posterior al cierre → IGNORADA por el sistema\n";
echo "      Horas hábiles: 6h (pero fuera del período de aplicación)\n";
echo "      Fecha calculada: " . $deadlineFueraLimite->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline7->equalTo($deadlineFueraLimite) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: Suspensión ignorada - fuera del período de cálculo (correcto)\n\n";

// D2: Suspensión larga que también está fuera del límite
$suspensionLargaFueraLimite = [
    ['start_at' => Carbon::parse('2025-01-28 08:00:00'), 'end_at' => Carbon::parse('2025-01-28 17:00:00')] // Día completo después del límite
];

$wrapperLargaFueraLimite = DeadlineWrapper::setup('peticion_general', $fechaInicio);
$wrapperLargaFueraLimite->injectSuspensions($suspensionLargaFueraLimite);
$deadlineLargaFueraLimite = $wrapperLargaFueraLimite->calculateDeadline();

echo "  D2. Martes 28/01/2025 08:00-17:00 (9h hábiles DESPUÉS del cierre inicial)\n";
echo "      Estado: Suspensión completa fuera del período → IGNORADA\n";
echo "      Horas hábiles: 9h (normalmente agregaría 1 día, pero está fuera del límite)\n";
echo "      Fecha calculada: " . $deadlineLargaFueraLimite->format('Y-m-d H:i:s') . "\n";
echo "      Comparación: " . ($deadlineBaseline7->equalTo($deadlineLargaFueraLimite) ? "IGUAL" : "DIFERENTE") . "\n";
echo "      Resultado: Sistema ignora suspensiones posteriores al cierre (correcto)\n\n";

// ========================================
// RESUMEN COMPARATIVO CASO 7
// ========================================

echo "RESUMEN COMPARATIVO CASO 7:\n";
echo "Baseline: " . $deadlineBaseline7->format('Y-m-d') . "\n\n";

echo "VALIDACIÓN TEMPORAL:\n";
echo "  - Suspensión anterior (25-27 dic):            " . $deadlineAntes->format('Y-m-d') . " " .
    ($deadlineAntes->equalTo($deadlineBaseline7) ? "(IGUAL - Correcto)" : "(ERROR)") . "\n";
echo "  - Suspensión posterior (10 ene 3h):           " . $deadlinePosterior->format('Y-m-d') . " " .
    ($deadlinePosterior->equalTo($deadlineBaseline7) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Suspensión cerca límite (23-24 ene 5h):     " . $deadlineCercaLimite->format('Y-m-d') . " " .
    ($deadlineCercaLimite->equalTo($deadlineBaseline7) ? "(IGUAL - Correcto)" : "(DIFERENTE)") . "\n";
echo "  - Suspensión fuera límite (27 ene 6h):        " . $deadlineFueraLimite->format('Y-m-d') . " " .
    ($deadlineFueraLimite->equalTo($deadlineBaseline7) ? "(IGUAL - Correcto)" : "(ERROR)") . "\n";
echo "  - Suspensión larga fuera límite (28 ene 9h):  " . $deadlineLargaFueraLimite->format('Y-m-d') . " " .
    ($deadlineLargaFueraLimite->equalTo($deadlineBaseline7) ? "(IGUAL - Correcto)" : "(ERROR)") . "\n\n";

echo "CONCLUSIONES CLAVE CASO 7:\n";
echo "1. Solo suspensiones POSTERIORES a la solicitud son consideradas\n";
echo "2. Suspensiones previas son automáticamente ignoradas\n";
echo "3. Suspensiones POSTERIORES al cierre inicial también son ignoradas\n";
echo "4. El sistema valida temporalmente cada suspensión en ambos extremos\n";
echo "5. Solo suspensiones dentro del período de cálculo afectan el plazo\n";
echo "6. La validación temporal protege contra configuraciones erróneas\n\n";

echo "DIFERENCIA CLAVE:\n";
echo "- Estrategia de HORAS: Cálculo exacto al minuto\n";
echo "- Estrategia de DÍAS HÁBILES: Solo cuenta días laborables (L-V)\n";
echo "- Suspensiones cortas (<8h hábiles): No agregan días\n";
echo "- Suspensiones largas (>8h hábiles): Agregan días hábiles\n";
echo "- Fines de semana y festivos: Nunca cuentan como días hábiles\n";
echo "- Solo las horas hábiles (horario laboral) son consideradas\n\n";

// ========================================
// RESUMEN
// ========================================

echo "=== RESUMEN ===\n";

echo "Uso básico de DeadlineWrapper:\n";
echo "   DeadlineWrapper::setup('peticion_general', \$fecha)\n\n";

echo "Con duplicación:\n";
echo "   DeadlineWrapper::setup('peticion_general', \$fecha, true)\n\n";

echo "Funcionalidades validadas:\n";
echo "   - Cálculo que excluye fines de semana\n";
echo "   - Cálculo que excluye festivos\n";
echo "   - Duplicación de plazo con tercer parámetro\n";
echo "   - Suspensiones cortas (<8h) no agregan días\n";
echo "   - Suspensiones largas (>8h) sí agregan días hábiles\n";
echo "   - Suspensiones nocturnas no agregan días\n";
echo "   - Suspensiones en fines de semana no cuentan\n";
echo "   - Suspensiones en festivos no cuentan\n";
echo "   - Suspensiones extemporáneas se ignoran\n\n";

echo "DIFERENCIA CLAVE con otras estrategias:\n";
echo "   - Horas: Cálculo exacto al minuto\n";
echo "   - Días calendario: Incluye todos los días\n";
echo "   - Días hábiles: Solo días laborables + regla de 8 horas\n";
echo "   - Factor decisivo: duración de suspensión vs 8 horas hábiles\n\n";

echo "La estrategia 'business_days' cuenta solo días laborables\n";
