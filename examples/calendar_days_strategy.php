<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;
use Micrositios\PqrDeadline\DeadlineWrapper;

echo "=== GUÍA DE USO: ESTRATEGIA DE DÍAS CALENDARIO ===\n\n";

// ========================================
// CASO 1: CÁLCULO BÁSICO - ITERACIÓN DE 15 DÍAS
// ========================================

echo "CASO 1: Cálculo básico con informe_congresistas - Iteración desde 2025-01-01\n";
echo "Mostrando comportamiento de días calendario en diferentes contextos\n\n";

$fechaBase = Carbon::parse('2025-01-01 10:00:00'); // Miércoles - Año Nuevo (festivo)

for ($i = 0; $i < 15; $i++) {
    $fechaInicio = $fechaBase->copy()->addDays($i);

    // Configurar wrapper con el tipo de solicitud
    $wrapper = DeadlineWrapper::setup('informe_congresistas', $fechaInicio);
    $deadline = $wrapper->calculateDeadline();

    // Determinar tipo de día (solo para visualización)
    $tipoDia = '';
    if ($fechaInicio->isWeekend()) {
        $tipoDia = ' (FIN DE SEMANA)';
    } else {
        $tipoDia = ' (DÍA CUALQUIERA)';
    }

    // Calcular duración en días
    $duracionDias = $fechaInicio->diffInDays($deadline);

    echo sprintf(
        "Día %2d: %s%s → %s (%d días)\n",
        $i + 1,
        $fechaInicio->format('Y-m-d D H:i'),
        $tipoDia,
        $deadline->format('Y-m-d H:i:s'),
        $duracionDias
    );
}

echo "\nAnálisis de la iteración:\n";
echo "- Tipo de solicitud: informe_congresistas (5 días calendario)\n";
echo "- Independiente de tipo de día: Siempre exactamente 5 días\n";
echo "- Incluye todos los días: Sin distinción de tipo\n";
echo "- Consistencia perfecta: Mismo intervalo en todos los casos\n";
echo "- Hora final: Siempre al final del día (23:59:59)\n\n";

// ========================================
// CASO 2: DUPLICACIÓN DEL PLAZO - ITERACIÓN DE 15 DÍAS
// ========================================

echo "CASO 2: Duplicación del plazo con informe_congresistas - Iteración desde 2025-01-01\n";
echo "Mostrando comportamiento con duplicación (10 días calendario)\n\n";

for ($i = 0; $i < 15; $i++) {
    $fechaInicio = $fechaBase->copy()->addDays($i);

    // Configurar wrapper con duplicación activada
    $wrapper = DeadlineWrapper::setup('informe_congresistas', $fechaInicio, true);
    $deadline = $wrapper->calculateDeadline();

    // Determinar tipo de día (solo para visualización)
    $tipoDia = '';
    if ($fechaInicio->isWeekend()) {
        $tipoDia = ' (FIN DE SEMANA)';
    } else {
        $tipoDia = ' (DÍA CUALQUIERA)';
    }

    // Calcular duración en días
    $duracionDias = $fechaInicio->diffInDays($deadline);

    echo sprintf(
        "Día %2d: %s%s → %s (%d días)\n",
        $i + 1,
        $fechaInicio->format('Y-m-d D H:i'),
        $tipoDia,
        $deadline->format('Y-m-d H:i:s'),
        $duracionDias
    );
}

echo "\nAnálisis de la duplicación:\n";
echo "- Tipo de solicitud: informe_congresistas con duplicación (10 días calendario)\n";
echo "- Plazo base: 5 días × 2 = 10 días exactos\n";
echo "- Independencia total: Tipo de día no afecta el cálculo\n";
echo "- Duplicación precisa: Exactamente el doble en todos los casos\n";
echo "- Cálculo continuo: 10 días calendario consecutivos\n\n";

// ========================================
// CASO 3: COMPORTAMIENTO CON SUSPENSIONES
// ========================================

echo "CASO 3: Suspensiones en estrategia de días calendario\n";
echo "Demostrando que las suspensiones se suman al plazo con aproximación a días\n\n";

$fechaEjemplo = Carbon::parse('2025-01-02 10:00:00'); // Jueves

// Baseline sin suspensiones
$wrapperBase = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$deadlineBase = $wrapperBase->calculateDeadline();

echo "BASELINE (sin suspensiones):\n";
echo "Fecha: " . $fechaEjemplo->format('Y-m-d H:i (l)') . "\n";
echo "Plazo: " . $deadlineBase->format('Y-m-d H:i:s') . " (5 días después)\n\n";

// Caso con suspensión de 1 día completo
$suspensionDia = [
    ['start_at' => Carbon::parse('2025-01-03 00:00:00'), 'end_at' => Carbon::parse('2025-01-04 00:00:00')] // 1 día completo
];

$wrapperConDia = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$wrapperConDia->injectSuspensions($suspensionDia);
$deadlineConDia = $wrapperConDia->calculateDeadline();

echo "CON SUSPENSIÓN DE 1 DÍA COMPLETO:\n";
echo "Suspensión: Viernes completo (24 horas)\n";
echo "Plazo: " . $deadlineConDia->format('Y-m-d H:i:s') . " (6 días total)\n";
echo "Diferencia: " . $deadlineBase->diffInDays($deadlineConDia) . " día adicional\n";
echo "Resultado: 5 días + 1 día = 6 días exactos\n\n";

// Caso con suspensión corta (4 horas) - característica especial de días calendario
$suspensionCorta = [
    ['start_at' => Carbon::parse('2025-01-03 14:00:00'), 'end_at' => Carbon::parse('2025-01-03 18:00:00')] // 4 horas
];

$wrapperCorta = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$wrapperCorta->injectSuspensions($suspensionCorta);
$deadlineCorta = $wrapperCorta->calculateDeadline();

echo "CON SUSPENSIÓN CORTA (4 horas):\n";
echo "Suspensión: Viernes 14:00-18:00 (4 horas)\n";
echo "Plazo: " . $deadlineCorta->format('Y-m-d H:i:s') . "\n";
echo "Diferencia: " . $deadlineBase->diffInDays($deadlineCorta) . " días\n";
echo "Resultado: Suspensión corta NO cambia el día final (aproximación a final de día)\n\n";

// Caso con suspensión larga (18 horas) - cruzando medianoche
$suspensionLarga = [
    ['start_at' => Carbon::parse('2025-01-03 14:00:00'), 'end_at' => Carbon::parse('2025-01-04 08:00:00')] // 18 horas
];

$wrapperLarga = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$wrapperLarga->injectSuspensions($suspensionLarga);
$deadlineLarga = $wrapperLarga->calculateDeadline();

echo "CON SUSPENSIÓN LARGA (18 horas):\n";
echo "Suspensión: Viernes 14:00 - Sábado 08:00 (18 horas)\n";
echo "Plazo: " . $deadlineLarga->format('Y-m-d H:i:s') . " (6 días total)\n";
echo "Diferencia: " . $deadlineBase->diffInDays($deadlineLarga) . " día adicional\n";
echo "Resultado: Suspensión larga SÍ añade un día completo (cruza medianoche)\n\n";

echo "CONCLUSIÓN: En días calendario, suspensiones cortas pueden no afectar el día final\n\n";

// ========================================
// CASO 4: VALIDACIÓN TEMPORAL DE SUSPENSIONES
// ========================================

echo "CASO 4: Validación temporal de suspensiones\n";
echo "Demostrando que solo las suspensiones válidas se aplican\n\n";

// Suspensión anterior (debe ser ignorada)
$suspensionAnterior = [
    ['start_at' => Carbon::parse('2025-01-01 10:00:00'), 'end_at' => Carbon::parse('2025-01-01 18:00:00')] // Antes de la solicitud
];

$wrapperAnterior = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$wrapperAnterior->injectSuspensions($suspensionAnterior);
$deadlineAnterior = $wrapperAnterior->calculateDeadline();

echo "SUSPENSIÓN ANTERIOR (debe ignorarse):\n";
echo "Suspensión: 2025-01-01 (antes de la solicitud)\n";
echo "Plazo: " . $deadlineAnterior->format('Y-m-d H:i:s') . "\n";
echo "Resultado: Igual al baseline - suspensión ignorada correctamente\n\n";

// Suspensión posterior válida
$suspensionPosterior = [
    ['start_at' => Carbon::parse('2025-01-04 10:00:00'), 'end_at' => Carbon::parse('2025-01-05 10:00:00')] // 1 día dentro del período
];

$wrapperPosterior = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$wrapperPosterior->injectSuspensions($suspensionPosterior);
$deadlinePosterior = $wrapperPosterior->calculateDeadline();

echo "SUSPENSIÓN POSTERIOR VÁLIDA:\n";
echo "Suspensión: Sábado-Domingo (1 día completo)\n";
echo "Plazo: " . $deadlinePosterior->format('Y-m-d H:i:s') . " (6 días total)\n";
echo "Resultado: 5 días + 1 día = 6 días exactos\n\n";

// Suspensión posterior fuera del período (debe ser ignorada)
$suspensionFueraPeriodo = [
    ['start_at' => Carbon::parse('2025-01-08 10:00:00'), 'end_at' => Carbon::parse('2025-01-09 18:00:00')] // Después del plazo base
];

$wrapperFueraPeriodo = DeadlineWrapper::setup('informe_congresistas', $fechaEjemplo);
$wrapperFueraPeriodo->injectSuspensions($suspensionFueraPeriodo);
$deadlineFueraPeriodo = $wrapperFueraPeriodo->calculateDeadline();

echo "SUSPENSIÓN POSTERIOR FUERA DEL PERÍODO (debe ignorarse):\n";
echo "Suspensión: Miércoles-Jueves (después del cierre del plazo)\n";
echo "Plazo: " . $deadlineFueraPeriodo->format('Y-m-d H:i:s') . "\n";
echo "Resultado: Igual al baseline - suspensión fuera del período ignorada\n\n";

echo "CONCLUSIÓN: Solo suspensiones dentro del período de cálculo se aplican\n\n";

// ========================================
// RESUMEN Y CONCLUSIONES
// ========================================

echo "=== RESUMEN DE LA ESTRATEGIA DE DÍAS CALENDARIO ===\n\n";

echo "CARACTERÍSTICAS PRINCIPALES:\n";
echo "1. DÍAS CONSECUTIVOS: Cuenta todos los días sin excepción\n";
echo "2. INCLUSIVO: Incluye cualquier tipo de día\n";
echo "3. APROXIMACIÓN: Redondea al final del día (23:59:59)\n";
echo "4. SIMPLICIDAD: Cálculo por días completos\n";
echo "5. CONSISTENCIA: Mismo comportamiento en cualquier contexto\n\n";

echo "COMPORTAMIENTO OBSERVADO:\n";
echo "- Plazo base: Siempre 5 días exactos para informe_congresistas\n";
echo "- Duplicación: 10 días exactos (5 × 2)\n";
echo "- Suspensiones completas: Se suman como días adicionales\n";
echo "- Suspensiones cortas: Pueden no afectar el día final\n";
echo "- Suspensiones largas: Siempre añaden días completos\n";
echo "- Validación: Solo suspensiones dentro del período se aplican\n\n";

echo "USO BÁSICO:\n";
echo "   DeadlineWrapper::setup('informe_congresistas', \$fecha)\n\n";

echo "CON DUPLICACIÓN:\n";
echo "   DeadlineWrapper::setup('informe_congresistas', \$fecha, true)\n\n";

echo "DIFERENCIAS CON OTRAS ESTRATEGIAS:\n";
echo "- Horas: Tiempo absoluto al minuto\n";
echo "- Días calendario: Tiempo absoluto redondeado a días\n";
echo "- Días hábiles: Solo días laborables + reglas de horario laboral\n\n";

echo "CARACTERÍSTICA DISTINTIVA:\n";
echo "• Factor clave: HORA DE RADICACIÓN + DURACIÓN DE SUSPENSIÓN\n";
echo "• Radicación temprana + suspensión corta = mismo día final\n";
echo "• Radicación tardía + suspensión corta = puede ganar un día\n";
echo "• El factor decisivo es si la suma supera las 24 horas del día de radicación\n\n";

echo "La estrategia 'calendar_days' cuenta días consecutivos con aproximación\n";
