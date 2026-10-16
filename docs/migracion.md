# Migración desde 1.x a la nueva API

Esta guía corresponde a la versión prevista **2.0.0, sin publicar**. El nombre Composer sigue siendo `micrositios/pqrdeadline`, el espacio de nombres `Micrositios\PqrDeadline` y el repositorio [micros/pqrdeadline](https://github.com/micros/pqrdeadline).

La nueva versión cambia la API y requiere PHP `^8.4` y extensión `calendar`. Instala primero la implementación local como indica el [README](../README.md#instalación). Cuando se publique, podrás solicitar `micrositios/pqrdeadline:^2.0`. Conserva el lock y las pruebas de tu aplicación al evaluar la migración.

## Correspondencia de llamadas y datos

| API anterior | API nueva |
|---|---|
| `DeadlineWrapper::setup($tipo, $fecha, $doble)` | `new CalculationRequest(fechaRadicacion: $fecha, tipoSolicitud: $tipoEnum, ampliarPlazo: $doble)` y luego `calcular()`. |
| `DeadlineWrapper::setup($params)` | Construir `DeadlineRules` con todas las reglas y pasarlo como `reglas`. |
| `injectSuspensions($pausas)` | Enviar una lista de objetos `Suspension` en la nueva solicitud. |
| `calculateDeadline()` | `(new DeadlineCalculator())->calcular($solicitud)`. |
| Fecha de retorno Carbon mutable | `$resultado->fechaMaximaRespuesta`, una `CarbonImmutable`. |
| `created_at` | `fechaRadicacion`. |
| `base_days` / `base_hours` y `strategy` | `plazo`, `unidad` y reglas de inicio, cierre, calendario y ajuste en `DeadlineRules`. |
| `double_term` | `ampliarPlazo`; usa el total explícito `plazoAmpliado`. |
| `start_at` / `end_at` | `inicio` / `fin` en `Suspension`. |
| `approximate_end_of_day` | Reglas explícitas de `cierre` y `horaCierre`. H exige cierre inmediato; no conserva la antigua opción de redondear horas al final del día. |
| Estrategias públicas y `StrategyFactory` | Una entrada pública de cálculo; la unidad se selecciona por tipo o reglas. |
| `RequestType::getName()` / `getDescription()` | `descripcion()` proporciona una descripción; `value` identifica el código. No existe un segundo método de nombre corto. |
| `RequestType::getTerm()` | `reglas()` devuelve plazo, unidad y demás configuración. |
| `InvalidArgumentException` sin código de dominio | `InvalidCalculationRequest`; usar `code()`, `getMessage()` y `details()` dentro de `catch`. |

Los nombres anteriores se muestran solo como ayuda de migración. No hay clases adaptadoras que emulen la API 1.x.

## Correspondencia de tipos

Los códigos nuevos distinguen mayúsculas. Para resolver una cadena externa usa `RequestType::desdeCodigo()` dentro del `try`.

| Código anterior | Tipo nuevo |
|---|---|
| `peticion_general` | `PETICION_GENERAL` |
| `peticion_autoridades` | `PETICION_INFORMACION_ENTRE_AUTORIDADES` |
| `consulta` | `CONSULTA` |
| `informacion_publica` | `SOLICITUD_INFORMACION` |
| `informe_congresistas` | `INFORME_CONGRESISTAS` — ahora 5 DH para el supuesto del art. 258. |
| `copia_historia_clinica` | `COPIAS_SALUD`, si no se requiere para consulta o urgencia. |
| `reclamo` | `RECLAMO` |
| `queja` | `QUEJA` |
| `salud_riesgo_simple` | `RECLAMO_SALUD_SIMPLE` |
| `salud_riesgo_priorizado` | `RECLAMO_SALUD_PRIORIZADO` |
| `salud_riesgo_vital` | `RECLAMO_SALUD_VITAL` |
| `sugerencia` | `SUGERENCIA`, cuando constituye una petición bajo régimen general. |
| `felicitacion` | `FELICITACION`, con esa misma delimitación. |
| `denuncia` | No tiene equivalencia automática. La aplicación debe clasificar su objeto y régimen, o proporcionar reglas completas. |

Se añaden riesgo vital de menores, servicios públicos domiciliarios, telecomunicaciones, postal, consultas y reclamos de datos personales y de hábeas data financiero. La [tabla normativa](resumen-normativo.md#catálogo-implementado) reúne los 21 perfiles y sus valores.

## Ejemplo de integración nueva

Guarda este programa junto al `vendor/` de tu aplicación. Las variables representan los datos que antes se pasaban al wrapper; el ejemplo adapta una petición general con pausa cerrada.

<!-- ejemplo-ejecutable: migracion -->
```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Input\Suspension;

$fecha = '2026-10-09T10:00:00-05:00';
$ampliar = false;

try {
    $solicitud = new CalculationRequest(
        fechaRadicacion: $fecha,
        tipoSolicitud: RequestType::PETICION_GENERAL,
        ampliarPlazo: $ampliar,
        suspensiones: [new Suspension(
            inicio: '2026-10-19T09:00:00-05:00',
            fin: '2026-10-26T15:00:00-05:00',
        )],
    );
    $resultado = (new DeadlineCalculator())->calcular($solicitud);
    echo $resultado->fechaMaximaRespuesta->format('Y-m-d\TH:i:s.uP') . PHP_EOL;
} catch (InvalidCalculationRequest $error) {
    echo $error->code() . ': ' . $error->getMessage() . PHP_EOL;
}
```

Salida: `2026-11-10T23:59:59.999999-05:00`. El cálculo es nuevo en cada llamada y no acumula estado del wrapper. Para añadir una pausa o cerrarla, construye una nueva solicitud con la lista completa que corresponda.

## Diferencias de comportamiento que debe revisar el consumidor

- **Congresistas:** el tipo anterior usaba 5 DC; el nuevo representa el art. 258 con 5 DH. Un cuestionario sometido a otro artículo necesita una selección distinta o reglas explícitas.
- **Suspensiones diarias:** se excluyen fechas civiles según DH/DC. Ya no se convierten fragmentos de jornada de 8:00 a 17:00 en días de ocho horas. Las pausas en H mantienen duración continua.
- **Inicio admisible de pausas:** se contrasta con el vencimiento original o ampliado sin otras suspensiones. Antes se comparaba con un vencimiento que podía desplazarse al aplicar pausas previas.
- **Datos inválidos:** la implementación anterior podía filtrar o ignorar algunas pausas incompletas o invertidas. La nueva recibe objetos tipados, trata `fin=null` como abierto y rechaza cronologías incoherentes con una excepción identificable. Inicio igual a cierre sigue siendo una duración válida de cero.
- **Pausas abiertas:** se usa el instante de consulta; siempre generan `conCierrePendiente=true` y aviso, incluso sin efecto sobre la fecha. No se introduce un estado de expediente.
- **Ampliación:** se lee el total configurado; no se multiplica automáticamente el plazo. Existen totales especiales 10→15 y 15→23. Un permiso informativo `false` no bloquea el evento enviado.
- **Fechas:** objetos inmutables, zona explícita o aportada por reglas y formato estricto de cadenas. No hay una fecha mínima reciente; la selección del régimen histórico corresponde al cliente.
- **Calendario:** Colombia nacional, lunes a viernes. No hay selección de países mediante una estrategia pública ni configuración de jornadas o excepciones particulares.
- **Resultado:** ya no es solo una fecha. La propiedad `fechaMaximaRespuesta` contiene el vencimiento calculado y `avisos` explica las observaciones; `conCierrePendiente` informa exclusivamente si falta algún cierre.

## Publicación e historial

El reemplazo previsto conservará el repositorio Git de destino y sus etiquetas anteriores. Un único commit incorporará la nueva implementación y eliminará los archivos obsoletos; la etiqueta `v2.0.0` señalará ese commit. No se importa el historial del repositorio de desarrollo ni se conserva código antiguo en una subcarpeta del paquete nuevo.

Packagist seguirá indexando el mismo paquete. Una restricción `^1.1` no selecciona 2.0.0; restricciones abiertas o una rama de desarrollo pueden seleccionar otro contenido. Consulta [versiones de Composer](https://getcomposer.org/doc/articles/versions.md) y [versionado en Packagist](https://packagist.org/about#managing-package-versions). Las etiquetas [v1.1.0](https://github.com/micros/pqrdeadline/tree/v1.1.0) y [v1.0.0](https://github.com/micros/pqrdeadline/tree/v1.0.0) conservan las versiones anteriores.

La instalación y el reemplazo se ensayan antes de publicar. Esta guía documenta el procedimiento previsto y no afirma que el reemplazo o la publicación ya se hayan realizado.

[Volver al índice](README.md)
