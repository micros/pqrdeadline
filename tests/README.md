# Tests

Este proyecto utiliza PHPUnit para las pruebas automatizadas con una cobertura completa de **42 tests y 59 assertions**.

## Ejecutar Tests

```bash
# Todos los tests
./vendor/bin/phpunit

# Con formato testdox (recomendado)
./vendor/bin/phpunit --testdox

# Solo tests unitarios
./vendor/bin/phpunit --testsuite=Unit

# Test específico
./vendor/bin/phpunit tests/Unit/BusinessDaysDeadlineCalculatorTest.php

# Con cobertura de código (requiere Xdebug)
./vendor/bin/phpunit --coverage-html coverage/
```

## Estructura de Tests

```
tests/
├── Unit/                                      # Tests unitarios (42 tests)
│   ├── BusinessDaysDeadlineCalculatorTest.php # 16 tests - Estrategia días hábiles
│   ├── CalendarDaysDeadlineCalculatorTest.php # 14 tests - Estrategia días calendario
│   └── HoursDeadlineCalculatorTest.php        # 12 tests - Estrategia horas
└── README.md                                  # Esta documentación
```

## Configuración

- **phpunit.xml**: Configuración principal de PHPUnit 10.5+
- **Tests**: Se ejecutan con autoload de `vendor/autoload.php`
- **Namespace**: `Micrositios\PqrDeadline\Tests\`
- **PSR-4**: Autoloading automático de clases de test

### Configuración PHPUnit

El proyecto usa PHPUnit 10.5+ con las siguientes configuraciones:

```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Cobertura de Tests Detallada

### BusinessDaysDeadlineCalculatorTest (16 tests)
**Estrategia de días hábiles**: Excluye fines de semana y festivos colombianos

#### Funcionalidades probadas:
- Cálculo básico desde diferentes días de la semana
- Manejo correcto de festivos colombianos
- Duplicación de plazos (segunda instancia)
- Suspensiones simples (días completos)
- Suspensiones parciales (horas que suman >= 8h)
- Múltiples suspensiones y traslapes
- Validación con tipos de PQR reales
- Casos edge: fines de semana, festivos, suspensiones

**Tipos de PQR validados**: 
- Petición General (15 días)
- Información Pública (10 días)
- Copia Historia Clínica (3 días)
- Reclamo (15 días, duplicado = 30 días)
- Consulta (30 días, duplicado = 60 días)

### CalendarDaysDeadlineCalculatorTest (14 tests)
**Estrategia de días calendario**: Incluye todos los días (laborables, fines de semana, festivos)

#### Funcionalidades probadas:
- Cálculo básico incluyendo fines de semana y festivos
- Manejo de años bisiestos (29 febrero)
- Cálculos que cruzan meses y años
- Suspensiones en días calendario
- Duplicación de plazos
- Integración con DeadlineWrapper

**Tipo de PQR validado**: Informe Congresistas (5 días calendario, duplicado = 10 días)

### HoursDeadlineCalculatorTest (12 tests)
**Estrategia de horas**: Cálculo en horas exactas, independiente de días laborables

#### Funcionalidades probadas:
- Cálculos independientes de días laborables
- Tipos de salud: Riesgo Vital (24h), Priorizado (48h), Simple (72h)
- Duplicación de plazos en horas
- Suspensiones que suman tiempo en horas
- Aproximación al final del día
- Precisión en minutos y segundos

**Tipos de PQR validados**: 
- Salud Riesgo Vital (24h, duplicado = 48h)
- Salud Riesgo Priorizado (48h, duplicado = 96h)
- Salud Riesgo Simple (72h, duplicado = 144h)

## Casos de Prueba Específicos

### Fechas de Referencia
Los tests utilizan fechas específicas para garantizar consistencia:
- **Base principal**: 12 enero 2024 (viernes 10:30)
- **Días alternos**: 15 enero 2024 (lunes), 25 diciembre 2024 (festivo)
- **Festivos**: Navidad 2024, Año Nuevo 2025, Independencia, Boyacá
- **Años bisiestos**: Febrero 2024 (29 días)
- **Rango de validación**: 2023-2025

### Tipos de Suspensión Validados
- **Días completos**: Suspensiones de 24 horas
- **Horas parciales**: Suspensiones que suman >= 8 horas laborales
- **Múltiples suspensiones**: Varias suspensiones en secuencia
- **Suspensiones traslapadas**: Manejo de solapamientos
- **Fines de semana**: Suspensiones que no aportan días hábiles
- **Con festivos**: Suspensiones que incluyen festivos

### Validaciones de Entrada
- Fechas de creación válidas
- Tipos de PQR existentes y sus plazos
- Parámetros de suspensión correctos
- Rangos de fechas válidos
- Duplicación de plazos (segunda instancia)

### Casos Edge Validados
1. **Años Bisiestos**: Manejo correcto del 29 de febrero
2. **Cambios de Mes**: Cálculos que cruzan fin/inicio de mes
3. **Cambios de Año**: Deadlines que cruzan años
4. **Festivos Consecutivos**: Navidad + Año Nuevo
5. **Suspensiones Complejas**: Traslapes y suspensiones contenidas
6. **Horas Exactas**: Precisión en minutos y segundos
7. **Aproximación**: Aproximación al final del día (23:59:59)
8. **Inicio en Festivos**: Cálculos que comienzan en días no hábiles

## Estrategias de Testing

### Días Hábiles
- Exclusión automática de fines de semana y festivos
- Inicio en siguiente día hábil
- Validación con calendario colombiano

### Días Calendario
- Inclusión de todos los días
- Cálculos continuos sin exclusiones
- Manejo de períodos largos

### Horas
- Independencia total de días laborables
- Precisión en cálculos temporales
- Validación de tipos de salud específicos

## Datos de Cobertura

**Total**: 42 tests, 59 assertions

**Distribución por estrategia**:
- Días hábiles: 16 tests (38%)
- Días calendario: 14 tests (33%)  
- Horas: 12 tests (29%)

**Estado**: Todos los tests pasan consistentemente ✅
**Tiempo de ejecución**: ~ 77ms
**Memoria utilizada**: ~ 12MB
**Versión PHPUnit**: 10.5.53
**Versión PHP**: 8.4.1
