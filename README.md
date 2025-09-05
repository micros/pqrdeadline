# PQR Deadline Calculator

Biblioteca PHP para el cálculo simple de fechas de vencimiento de PQR (Peticiones, Quejas y Reclamos) para Colombia.

## Características

- Múltiples estrategias de cálculo: Días hábiles, días calendario y horas
- Soporte para festivos colombianos: Integración con `cmixin/business-day`
- Manejo de suspensiones: Cálculo dinámico con traslapes y validaciones
- Identificadores únicos: Soporte opcional para ULID en suspensiones
- Tipos de PQR predefinidos: 14 tipos según normativa colombiana
- Duplicación de plazos: Soporte para segunda instancia
- Validaciones robustas: Control de errores y casos edge
- Documentación completa: Lógica de negocio detallada en código
- Cobertura total: 42 tests, 59 assertions

## Instalación

```bash
composer require micrositios/pqrdeadline
```

## Requisitos

- PHP >= 8.1
- nesbot/carbon ^3.0
- cmixin/business-day ^1.16

## Uso Básico

```php
use Micrositios\PqrDeadline\DeadlineWrapper;
use Carbon\Carbon;

// Crear PQR
$createdAt = Carbon::now();
$requestType = 'peticion_general';
$doubleTerm = false;

// Calcular deadline
$wrapper = DeadlineWrapper::setup($requestType, $createdAt, $doubleTerm);
$deadline = $wrapper->calculateDeadline();

echo "Vencimiento: " . $deadline->format('Y-m-d H:i:s');
```

## Estrategias de Cálculo

### 1. Días Hábiles (BusinessDaysDeadlineCalculator)
- **Uso**: Mayoría de PQR (peticiones, quejas, reclamos, etc.)
- **Características**: Excluye fines de semana y festivos colombianos
- **Inicio**: Siguiente día hábil a la radicación

### 2. Días Calendario (CalendarDaysDeadlineCalculator)
- **Uso**: Informes de congresistas
- **Características**: Incluye todos los días (laborables, fines de semana, festivos)
- **Inicio**: Día siguiente a la radicación

### 3. Horas (HoursDeadlineCalculator)
- **Uso**: Reclamos de salud de riesgo vital, priorizado y simple
- **Características**: Cálculo en horas exactas, independiente de días laborables
- **Inicio**: Inmediato desde la hora de radicación

## Tipos de PQR Soportados

| Tipo | String | Plazo | Estrategia | Días Hábiles |
|------|--------|-------|------------|--------------|
| Informe Congresistas | `informe_congresistas` | 5 días | Calendario | No |
| Petición General | `peticion_general` | 15 días | Hábiles | Sí |
| Petición Autoridades | `peticion_autoridades` | 10 días | Hábiles | Sí |
| Consulta | `consulta` | 30 días | Hábiles | Sí |
| Información Pública | `informacion_publica` | 10 días | Hábiles | Sí |
| Copia Historia Clínica | `copia_historia_clinica` | 3 días | Hábiles | Sí |
| Reclamo | `reclamo` | 15 días | Hábiles | Sí |
| Queja | `queja` | 15 días | Hábiles | Sí |
| Salud Riesgo Simple | `salud_riesgo_simple` | 72 horas | Horas | No |
| Salud Riesgo Priorizado | `salud_riesgo_priorizado` | 48 horas | Horas | No |
| Salud Riesgo Vital | `salud_riesgo_vital` | 24 horas | Horas | No |
| Denuncia | `denuncia` | 15 días | Hábiles | Sí |
| Sugerencia | `sugerencia` | 15 días | Hábiles | Sí |
| Felicitación | `felicitacion` | 15 días | Hábiles | Sí |

## Tests

Para ejecutar los tests y ver la documentación completa de pruebas, consulta [tests/README.md](tests/README.md).

```bash
# Ejecutar todos los tests
vendor/bin/phpunit

# Con formato legible
vendor/bin/phpunit --testdox
```

**Total**: 42 tests, 59 assertions cubriendo las 3 estrategias de cálculo.

## Ejemplos de Uso

### Forma Automática: Usando String

La forma más simple de usar la librería es con el método `DeadlineWrapper::setup()`:

```php
use Micrositios\PqrDeadline\DeadlineWrapper;
use Carbon\Carbon;

// Petición general (15 días hábiles)
$wrapper = DeadlineWrapper::setup(
    'peticion_general',
    Carbon::create(2024, 1, 15, 10, 30),
    false // no duplicar
);

$deadline = $wrapper->calculateDeadline();
// Resultado: 2024-02-05 23:59:59
```

### Forma Automática: Con Suspensiones

```php
use Micrositios\PqrDeadline\DeadlineWrapper;
use Carbon\Carbon;

// Petición general con suspensiones
$wrapper = DeadlineWrapper::setup(
    'peticion_general',
    Carbon::create(2024, 1, 15, 10, 30),
    false
);

// Agregar suspensiones usando injectSuspensions
$suspensions = [
    [
        'start_at' => Carbon::create(2024, 1, 20, 8, 0),
        'end_at' => Carbon::create(2024, 1, 22, 17, 0)
    ]
];

$deadline = $wrapper->injectSuspensions($suspensions)->calculateDeadline();
```

### Forma Manual: Parámetros Explícitos

Para casos que requieren mayor control, pasa un array de parámetros a `DeadlineWrapper::setup()`:

```php
use Micrositios\PqrDeadline\DeadlineWrapper;
use Carbon\Carbon;

$params = [
    'created_at' => Carbon::create(2024, 1, 15, 9, 0),
    'base_days' => 15,
    'double_term' => false,
    'strategy' => 'business_days'
];

$wrapper = DeadlineWrapper::setup($params);
$deadline = $wrapper->calculateDeadline();
```

### Forma Manual: Con Suspensiones

```php
use Micrositios\PqrDeadline\DeadlineWrapper;
use Carbon\Carbon;

$params = [
    'created_at' => Carbon::create(2024, 1, 15, 9, 0),
    'base_days' => 15,
    'double_term' => false,
    'strategy' => 'business_days'
];

$suspensions = [
    [
        'start_at' => Carbon::create(2024, 1, 20, 8, 0),
        'end_at' => Carbon::create(2024, 1, 22, 17, 0)
    ]
];

$wrapper = DeadlineWrapper::setup($params);
$deadline = $wrapper->injectSuspensions($suspensions)->calculateDeadline();
```

### Forma Avanzada: Estrategias Directas

Para máximo control, usa directamente las estrategias:

```php
use Micrositios\PqrDeadline\Strategies\BusinessDaysDeadlineCalculator;
use Carbon\Carbon;

$calculator = new BusinessDaysDeadlineCalculator();

$params = [
    'created_at' => Carbon::create(2024, 1, 15, 9, 0),
    'base_days' => 15,
    'double_term' => false,
    'approximate_end_of_day' => true
];

$deadline = $calculator->calculate($params);
```

### Forma Avanzada: Con Suspensiones

```php
use Micrositios\PqrDeadline\Strategies\BusinessDaysDeadlineCalculator;
use Carbon\Carbon;

$calculator = new BusinessDaysDeadlineCalculator();

$params = [
    'created_at' => Carbon::create(2024, 1, 15, 9, 0),
    'base_days' => 15,
    'double_term' => false,
    'approximate_end_of_day' => true,
    'suspensions' => [
        [
            'start_at' => Carbon::create(2024, 1, 20, 8, 0),
            'end_at' => Carbon::create(2024, 1, 22, 17, 0)
        ]
    ]
];

$deadline = $calculator->calculate($params);
```

## Arquitectura

```
src/
├── Contracts/
│   └── DeadlineCalculator.php          # Interface principal
├── Enums/
│   └── RequestType.php                 # Tipos de PQR
├── Strategies/
│   ├── BusinessDaysDeadlineCalculator.php     # Días hábiles
│   ├── CalendarDaysDeadlineCalculator.php     # Días calendario
│   ├── HoursDeadlineCalculator.php            # Horas
│   ├── AppliesDynamicSuspensions.php          # Trait suspensiones
│   └── StrategyFactory.php                    # Factory estrategias
└── DeadlineWrapper.php                # Wrapper principal
```

## Contribuir

1. Fork el proyecto
2. Crear branch para feature (`git checkout -b feature/nueva-funcionalidad`)
3. Escribir tests para los cambios
4. Ejecutar tests (`vendor/bin/phpunit`)
5. Commit cambios (`git commit -am 'Agregar nueva funcionalidad'`)
6. Push al branch (`git push origin feature/nueva-funcionalidad`)
7. Crear Pull Request

### Estándares de Código

- PSR-4 autoloading
- PSR-12 coding style
- PHPUnit para testing
- Documentación en español
- Cobertura de tests > 90%

## Licencia

MIT License. Ver archivo [LICENSE](LICENSE) para más detalles.

## Changelog

### v1.1.0 (2025-09-04) 📅
- **Upgrade Carbon 3**: Actualización a nesbot/carbon ^3.0 para mejor rendimiento
- **Compatibilidad mantenida**: Todos los tests siguen pasando (42 tests, 59 assertions)
- **Dependencias actualizadas**: Symfony Clock 7.3 y polyfills actualizados
- **API sin cambios**: Misma interfaz pública, solo mejoras internas

### v1.0.0 (2025-09-04) 🎉
- **Release estable oficial**: Sistema completo de cálculo de deadlines para PQR
- **Documentación de lógica de negocio**: Reglas específicas documentadas en todas las estrategias
- **Identificadores únicos**: Soporte opcional para ULID en suspensiones
- **Cobertura completa**: 42 tests, 59 assertions con 100% de éxito
- **Arquitectura madura**: Tres estrategias consolidadas con manejo avanzado de traslapes
- **Reglas de negocio claras**: BusinessDays (8AM-5PM, 8h=1día), CalendarDays (consecutivos), Hours (continuo)

### v0.2.1
- Reestructuración: DeadlineWrapper movido a directorio raíz
- Eliminación de carpeta Support innecesaria
- Namespace simplificado: `Micrositios\PqrDeadline\DeadlineWrapper`
- Actualización de todas las referencias en documentación y ejemplos

### v0.2.0
- Limpieza y optimización del código base
- Eliminación de clases no utilizadas (ErrorFactory, DomainException, validadores)
- Simplificación de la arquitectura manteniendo funcionalidad completa
- Actualización de documentación y ejemplos
- 42 tests, 59 assertions con 100% de éxito

### v0.1.0
- Implementación inicial con 3 estrategias de cálculo
- 14 tipos de PQR predefinidos
- Soporte para festivos colombianos
- Manejo avanzado de suspensiones
- Suite completa de tests
- Documentación completa

---

**Mantenido por**: Micrositios
**Versión**: 1.1.0
**PHP**: >= 8.1