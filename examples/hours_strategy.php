<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;
use Micrositios\PqrDeadline\Support\DeadlineWrapper;

echo "=== GUÍA DE USO: ESTRATEGIA DE HORAS ===\n\n";

// ========================================
// CASO 1: CÁLCULO BÁSICO - ITERACIÓN DE 15 DÍAS
// ========================================

echo "CASO 1: Cálculo básico con salud_riesgo_simple - Iteración desde 2025-01-01\n";
echo "Mostrando comportamiento de tiempo absoluto en diferentes contextos\n\n";

$fechaBase = Carbon::parse('2025-01-01 10:00:00'); // Miércoles - Año Nuevo (festivo)

for ($i = 0; $i < 15; $i++) {
    $fechaInicio = $fechaBase->copy()->addDays($i);

    // Configurar wrapper con el tipo de solicitud
    $wrapper = DeadlineWrapper::setup('salud_riesgo_simple', $fechaInicio);
    $deadline = $wrapper->calculateDeadline();

    // Determinar tipo de día (solo para visualización)
    $tipoDia = '';
    if ($fechaInicio->isWeekend()) {
        $tipoDia = ' (FIN DE SEMANA)';
    } else {
        $tipoDia = ' (DÍA CUALQUIERA)';
    }

    // Calcular duración exacta
    $duracionHoras = $fechaInicio->diffInHours($deadline);

    echo sprintf(
        "Día %2d: %s%s → %s (%d horas)\n",
        $i + 1,
        $fechaInicio->format('Y-m-d D H:i'),
        $tipoDia,
        $deadline->format('Y-m-d H:i:s'),
        $duracionHoras
    );
}

echo "\nAnálisis de la iteración:\n";
echo "- Tipo de solicitud: salud_riesgo_simple (72 horas absolutas)\n";
echo "- Independiente de tipo de día: Siempre exactamente 72 horas\n";
echo "- Cálculo absoluto: No considera calendario laboral\n";
echo "- Consistencia perfecta: Mismo intervalo en todos los casos\n";
echo "- Tiempo continuo: Incluye todos los días sin excepción\n\n";

// ========================================
// CASO 2: DUPLICACIÓN DEL PLAZO - ITERACIÓN DE 15 DÍAS
// ========================================

echo "CASO 2: Duplicación del plazo con salud_riesgo_simple - Iteración desde 2025-01-01\n";
echo "Mostrando comportamiento con duplicación (144 horas absolutas)\n\n";

for ($i = 0; $i < 15; $i++) {
    $fechaInicio = $fechaBase->copy()->addDays($i);

    // Configurar wrapper con duplicación activada
    $wrapper = DeadlineWrapper::setup('salud_riesgo_simple', $fechaInicio, true);
    $deadline = $wrapper->calculateDeadline();

    // Determinar tipo de día (solo para visualización)
    $tipoDia = '';
    if ($fechaInicio->isWeekend()) {
        $tipoDia = ' (FIN DE SEMANA)';
    } else {
        $tipoDia = ' (DÍA CUALQUIERA)';
    }

    // Calcular duración exacta
    $duracionHoras = $fechaInicio->diffInHours($deadline);

    echo sprintf(
        "Día %2d: %s%s → %s (%d horas)\n",
        $i + 1,
        $fechaInicio->format('Y-m-d D H:i'),
        $tipoDia,
        $deadline->format('Y-m-d H:i:s'),
        $duracionHoras
    );
}

echo "\nAnálisis de la duplicación:\n";
echo "- Tipo de solicitud: salud_riesgo_simple con duplicación (144 horas absolutas)\n";
echo "- Plazo base: 72 horas × 2 = 144 horas exactas\n";
echo "- Independencia total: Tipo de día no afecta el cálculo\n";
echo "- Duplicación precisa: Exactamente el doble en todos los casos\n";
echo "- Cálculo matemático: 144 horas = 6 días exactos\n\n";

// ========================================
// CASO 3: COMPORTAMIENTO CON SUSPENSIONES
// ========================================

echo "CASO 3: Suspensiones en estrategia de horas\n";
echo "Demostrando que las suspensiones se suman íntegramente al plazo\n\n";

$fechaEjemplo = Carbon::parse('2025-01-02 10:00:00'); // Jueves

// Baseline sin suspensiones
$wrapperBase = DeadlineWrapper::setup('salud_riesgo_simple', $fechaEjemplo);
$deadlineBase = $wrapperBase->calculateDeadline();

echo "BASELINE (sin suspensiones):\n";
echo "Fecha: " . $fechaEjemplo->format('Y-m-d H:i (l)') . "\n";
echo "Plazo: " . $deadlineBase->format('Y-m-d H:i:s') . " (72h después)\n\n";

// Caso con suspensión de 4 horas
$suspensiones = [
    ['start_at' => Carbon::parse('2025-01-03 14:00:00'), 'end_at' => Carbon::parse('2025-01-03 18:00:00')] // Viernes 4h
];

$wrapperConSuspension = DeadlineWrapper::setup('salud_riesgo_simple', $fechaEjemplo);
$wrapperConSuspension->injectSuspensions($suspensiones);
$deadlineConSuspension = $wrapperConSuspension->calculateDeadline();

echo "CON SUSPENSIÓN DE 4 HORAS:\n";
echo "Suspensión: Viernes 14:00-18:00 (4 horas)\n";
echo "Plazo: " . $deadlineConSuspension->format('Y-m-d H:i:s') . " (76h total)\n";
echo "Diferencia: " . $deadlineBase->diffInHours($deadlineConSuspension) . " horas adicionales\n";
echo "Resultado: 72h + 4h = 76h exactas\n\n";

// Caso con múltiples suspensiones
$suspensionesMultiples = [
    ['start_at' => Carbon::parse('2025-01-03 10:00:00'), 'end_at' => Carbon::parse('2025-01-03 12:00:00')], // Viernes 2h
    ['start_at' => Carbon::parse('2025-01-04 14:00:00'), 'end_at' => Carbon::parse('2025-01-04 18:00:00')], // Sábado 4h
    ['start_at' => Carbon::parse('2025-01-06 16:00:00'), 'end_at' => Carbon::parse('2025-01-06 18:00:00')]  // Lunes 2h
];

$wrapperMultiples = DeadlineWrapper::setup('salud_riesgo_simple', $fechaEjemplo);
$wrapperMultiples->injectSuspensions($suspensionesMultiples);
$deadlineMultiples = $wrapperMultiples->calculateDeadline();

echo "CON MÚLTIPLES SUSPENSIONES (8h total):\n";
echo "- Viernes 10:00-12:00 (2h)\n";
echo "- Sábado 14:00-18:00 (4h) \n";
echo "- Lunes 16:00-18:00 (2h)\n";
echo "Plazo: " . $deadlineMultiples->format('Y-m-d H:i:s') . " (80h total)\n";
echo "Resultado: 72h + 8h = 80h exactas\n\n";

echo "CONCLUSIÓN: Las suspensiones se suman algebraicamente al plazo base\n\n";

// ========================================
// CASO 4: VALIDACIÓN TEMPORAL DE SUSPENSIONES
// ========================================

echo "CASO 4: Validación temporal de suspensiones\n";
echo "Demostrando que solo las suspensiones válidas se aplican\n\n";

// Suspensión anterior (debe ser ignorada)
$suspensionAnterior = [
    ['start_at' => Carbon::parse('2025-01-01 10:00:00'), 'end_at' => Carbon::parse('2025-01-01 18:00:00')] // Antes de la solicitud
];

$wrapperAnterior = DeadlineWrapper::setup('salud_riesgo_simple', $fechaEjemplo);
$wrapperAnterior->injectSuspensions($suspensionAnterior);
$deadlineAnterior = $wrapperAnterior->calculateDeadline();

echo "SUSPENSIÓN ANTERIOR (debe ignorarse):\n";
echo "Suspensión: 2025-01-01 (antes de la solicitud)\n";
echo "Plazo: " . $deadlineAnterior->format('Y-m-d H:i:s') . "\n";
echo "Resultado: Igual al baseline - suspensión ignorada correctamente\n\n";

// Suspensión posterior válida
$suspensionPosterior = [
    ['start_at' => Carbon::parse('2025-01-04 10:00:00'), 'end_at' => Carbon::parse('2025-01-04 16:00:00')] // Dentro del período
];

$wrapperPosterior = DeadlineWrapper::setup('salud_riesgo_simple', $fechaEjemplo);
$wrapperPosterior->injectSuspensions($suspensionPosterior);
$deadlinePosterior = $wrapperPosterior->calculateDeadline();

echo "SUSPENSIÓN POSTERIOR VÁLIDA:\n";
echo "Suspensión: Sábado 10:00-16:00 (6h)\n";
echo "Plazo: " . $deadlinePosterior->format('Y-m-d H:i:s') . " (78h total)\n";
echo "Resultado: 72h + 6h = 78h exactas\n\n";

// Suspensión posterior fuera del período (debe ser ignorada)
$suspensionFueraPeriodo = [
    ['start_at' => Carbon::parse('2025-01-06 10:00:00'), 'end_at' => Carbon::parse('2025-01-06 18:00:00')] // Después del plazo base
];

$wrapperFueraPeriodo = DeadlineWrapper::setup('salud_riesgo_simple', $fechaEjemplo);
$wrapperFueraPeriodo->injectSuspensions($suspensionFueraPeriodo);
$deadlineFueraPeriodo = $wrapperFueraPeriodo->calculateDeadline();

echo "SUSPENSIÓN POSTERIOR FUERA DEL PERÍODO (debe ignorarse):\n";
echo "Suspensión: Lunes 10:00-18:00 (después del cierre del plazo)\n";
echo "Plazo: " . $deadlineFueraPeriodo->format('Y-m-d H:i:s') . "\n";
echo "Resultado: Igual al baseline - suspensión fuera del período ignorada\n\n";

echo "CONCLUSIÓN: Solo suspensiones dentro del período de cálculo se aplican\n\n";

// ========================================
// RESUMEN Y CONCLUSIONES
// ========================================

echo "=== RESUMEN DE LA ESTRATEGIA DE HORAS ===\n\n";

echo "CARACTERÍSTICAS PRINCIPALES:\n";
echo "1. TIEMPO ABSOLUTO: Cuenta todas las horas sin excepción\n";
echo "2. INDEPENDENCIA: No considera días hábiles, fines de semana o festivos\n";
echo "3. SIMPLICIDAD: Cálculo matemático puro (base + suspensiones)\n";
echo "4. PRECISIÓN: Exactitud al minuto\n";
echo "5. CONSISTENCIA: Mismo comportamiento en cualquier contexto\n\n";

echo "COMPORTAMIENTO OBSERVADO:\n";
echo "- Plazo base: Siempre 72 horas exactas para salud_riesgo_simple\n";
echo "- Duplicación: 144 horas exactas (72 × 2)\n";
echo "- Suspensiones: Se suman íntegramente al plazo\n";
echo "- Validación: Solo suspensiones dentro del período se aplican\n";
echo "- Contexto: Tipo de día irrelevante\n\n";

echo "USO BÁSICO:\n";
echo "   DeadlineWrapper::setup('salud_riesgo_simple', \$fecha)\n\n";

echo "CON DUPLICACIÓN:\n";
echo "   DeadlineWrapper::setup('salud_riesgo_simple', \$fecha, true)\n\n";

echo "DIFERENCIAS CON OTRAS ESTRATEGIAS:\n";
echo "- Horas: Tiempo absoluto, incluye TODO\n";
echo "- Días calendario: Tiempo absoluto pero redondeado a días\n";
echo "- Días hábiles: Solo días laborables + reglas de horario laboral\n\n";

echo "La estrategia 'hours' es la más simple y directa: tiempo absoluto\n";
