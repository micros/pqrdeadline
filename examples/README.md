# Ejemplos de Estrategias de Cálculo de Fechas Límite

Este directorio contiene ejemplos prácticos que demuestran las **tres estrategias** implementadas en el sistema para el cálculo de fechas límite de respuesta a PQR (Peticiones, Quejas y Reclamos).

## Estrategia de Horas

**Archivo**: `hours_strategy.php`

La estrategia más **sencilla** del sistema. Funciona de la siguiente manera:

- Toma la fecha/hora exacta de radicación
- Suma las horas correspondientes según el tipo de solicitud
- **No aplica ninguna aproximación** - mantiene la hora exacta

### Características:
- Precisión exacta: El resultado conserva horas, minutos y segundos
- Simplicidad: No depende de calendarios laborales o festivos
- Rapidez: Cálculo directo sin validaciones adicionales

### Casos de uso típicos:
- Solicitudes de salud con urgencia (24h, 48h, 72h)
- Procesos que requieren tiempos exactos
- Sistemas que operan 24/7

---

## Estrategia de Días Calendario

**Archivo**: `calendar_days_strategy.php`

Una estrategia **muy sencilla** que incluye todos los días (laborables, fines de semana y festivos):

- Toma la fecha/hora de radicación
- Suma el plazo en días calendario según el tipo de solicitud
- **Aproxima el resultado al final del día** (23:59:59)

### Características:
- Inclusiva: Cuenta todos los días sin excepciones
- Aproximación estándar: Siempre termina a las 23:59:59
- Predictible: Fácil de calcular manualmente

### Diferencia clave con estrategia de horas:
> **Importante**: A diferencia de la estrategia de horas, esta estrategia **SÍ aplica aproximación** al final del día. Si una solicitud se radica el lunes a las 10:30 AM con plazo de 5 días calendario, el resultado será el sábado a las 23:59:59, no el sábado a las 10:30 AM.

### Casos de uso típicos:
- Informes congresistas (5 días calendario)
- Procesos administrativos simples
- Cuando se quiere incluir fines de semana

---

## Estrategia de Días Laborales (Días Hábiles)

**Archivo**: `business_days_strategy.php`

La estrategia **más compleja** que excluye fines de semana y días festivos:

- Toma la fecha/hora de radicación
- Suma el plazo en días laborales, **excluyendo sábados, domingos y festivos**
- Aproxima el resultado al final del día (23:59:59)
- Requiere configuración previa del calendario de festivos colombiano

### Características:
- Compleja: Requiere librería especializada `cmixin/business-day`
- Calendario colombiano: Días festivos configurados para Colombia
- Aproximación al final del día: Aunque teóricamente debería aproximarse al final de la jornada laboral (ej: 17:59:59), por **uzanza y costumbre** se aproxima al final real del día (23:59:59)
- Inteligente: Si se inicia en fin de semana o festivo, comienza el cálculo desde el siguiente día hábil

### Dependencias técnicas:
```php
// Requiere configuración previa del calendario
BusinessDay::enable(Carbon::class);
BusinessDay::setHolidaysRegion('CO'); // Colombia
```

### Casos de uso típicos:
- Peticiones generales (15 días hábiles)
- Información pública (10 días hábiles)
- Reclamos (15 días hábiles)
- Consultas (30 días hábiles)

---

## Archivos de Ejemplo

| Archivo | Estrategia | Líneas | Descripción |
|---------|------------|--------|-------------|
| `hours_strategy.php` | Horas | ~170 | Ejemplos con tipos de salud (24h, 48h, 72h) |
| `calendar_days_strategy.php` | Días calendario | ~233 | Ejemplos con informes congresistas (5 días) |
| `business_days_strategy.php` | Días laborales | ~282 | Ejemplos con PQR generales (15, 10, 3 días) |

## Cómo ejecutar los ejemplos

```bash
# Estrategia de horas
php examples/hours_strategy.php

# Estrategia de días calendario
php examples/calendar_days_strategy.php

# Estrategia de días laborales
php examples/business_days_strategy.php
```

## Resumen Comparativo

| Aspecto | Horas | Días Calendario | Días Laborales |
|---------|-------|-----------------|----------------|
| **Complejidad** | Muy simple | Simple | Compleja |
| **Aproximación** | No aplica | Final del día | Final del día |
| **Fines de semana** | Incluye | Incluye | Excluye |
| **Festivos** | Incluye | Incluye | Excluye |
| **Precisión temporal** | Exacta | Día completo | Día completo |
| **Dependencias** | Ninguna | Ninguna | Calendar CO |

---

> **Tip**: Cada archivo de ejemplo incluye casos detallados con suspensiones, duplicación de plazos y situaciones edge para que puedas entender completamente cómo funciona cada estrategia.
