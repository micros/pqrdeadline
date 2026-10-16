# PQR Deadline

Librería PHP de Micrositios para calcular fechas máximas de respuesta de solicitudes PQR. Recibe una radicación, un tipo de solicitud o reglas explícitas, y las ampliaciones y suspensiones que indique la aplicación. Devuelve un objeto con las fechas calculadas y los avisos correspondientes.

PHP puro, sin dependencia de Laravel, persistencia, datos personales ni conexiones de red durante el cálculo. Las fechas de entrada se copian a `CarbonImmutable`; los objetos de entrada y salida son inmutables.

**Esta documentación describe la API 2.x implementada en este repositorio.** El paquete mantiene el nombre `micrositios/pqrdeadline` y el repositorio [micros/pqrdeadline](https://github.com/micros/pqrdeadline). Para adaptar una integración con la API 1.x, consulta la [guía de migración](docs/migracion.md).

## Información disponible en este repositorio

| Recurso | Qué encontrarás |
|---|---|
| [Ejemplos y resultados](examples/README.md) | Cómo ejecutar nueve escenarios: tipos, reglas, horas, ampliación, suspensiones y errores. |
| [Archivo ejecutable de ejemplos](examples/uso.php) | Programas pequeños reunidos en un solo archivo PHP. |
| [Índice de documentación](docs/README.md) | Recorrido de lectura y enlaces a las guías del paquete. |
| [Resumen normativo y catálogo](docs/resumen-normativo.md) | Los 21 tipos, sus plazos, indicadores, fuentes oficiales y decisiones de implementación. |
| [Migración desde 1.x](docs/migracion.md) | Correspondencia de clases, parámetros y tipos; diferencias de comportamiento. |
| [Historial de cambios](CHANGELOG.md) | Cambios de la nueva versión y referencias a las versiones anteriores. |
| [Contribución y pruebas](CONTRIBUTING.md) | Instalación para desarrollo, ejecución de pruebas y formato de commits. |
| [Pruebas automatizadas](tests/) | Casos de calendario, fechas, reglas, ampliaciones, suspensiones, resultados y documentación. |
| [Licencia MIT](LICENSE) | Condiciones de uso y autoría. |

En este README están la instalación, el inicio rápido y el contrato completo de entrada y salida.

## Requisitos

- PHP `^8.4` y extensión `calendar`.
- Composer para instalar el paquete y sus dependencias.
- `nesbot/carbon ^3.14.2` para fechas inmutables.
- `cmixin/business-day ^1.21` para obtener los festivos nacionales de Colombia.

Composer instala las dependencias transitivas y comprueba sus requisitos. PHPUnit solo se utiliza durante el desarrollo. Las versiones PHP verificadas se registran en [contribución](CONTRIBUTING.md).

## Instalación

### Instalación local

Sitúa las carpetas `deadline/` y `aplicacion/` una junto a la otra. Guarda este `composer.json` en `aplicacion/`; si la carpeta de la librería tiene otro nombre, ajusta `url`.

<!-- composer-consumidor -->
```json
{
    "name": "ejemplo/aplicacion-pqr",
    "repositories": [
        {
            "type": "path",
            "url": "../deadline",
            "options": {
                "symlink": false,
                "versions": {"micrositios/pqrdeadline": "dev-local"}
            }
        }
    ],
    "require": {"micrositios/pqrdeadline": "dev-local"}
}
```

La carpeta fuente de este ensayo debe ser una **copia limpia**: copia el código, ejemplos, documentación y configuración, excluyendo `.git/`, `vendor/`, `composer.lock`, cachés y otros archivos generados. Composer puede copiar también esos archivos si están presentes en un repositorio local de tipo `path`. Conserva tu carpeta original de desarrollo; la aplicación creará su propio `vendor/` y su propio lock.

Desde `aplicacion/`:

```bash
composer install --no-dev
composer check-platform-reqs
```

`dev-local` identifica exclusivamente esta instalación de ensayo. `symlink=false` instala una copia del paquete. Véase la [documentación de repositorios locales de Composer](https://getcomposer.org/doc/05-repositories.md#path).

### Instalación desde Packagist

En la aplicación consumidora:

```bash
composer require micrositios/pqrdeadline:^2.0
```

Este comando requiere una versión 2.x publicada en Packagist. Si aún no está disponible, utiliza la instalación local anterior. La [guía de migración](docs/migracion.md) explica los cambios desde 1.x.

## Inicio rápido: cálculo por tipo

Guarda cada programa completo de este README en la raíz de la aplicación, junto a `vendor/`, y ejecútalo con PHP. Los constructores se incluyen dentro del `try`, pues también pueden detectar datos inválidos.

<!-- ejemplo-ejecutable: por-tipo -->
```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;

try {
    $resultado = (new DeadlineCalculator())->calcular(new CalculationRequest(
        fechaRadicacion: '2026-10-09T10:00:00-05:00',
        tipoSolicitud: RequestType::PETICION_GENERAL,
    ));

    echo $resultado->fechaMaximaRespuesta->format('Y-m-d\TH:i:s.uP') . PHP_EOL;
    echo 'Cierre pendiente: ' . ($resultado->conCierrePendiente ? 'sí' : 'no') . PHP_EOL;
} catch (InvalidCalculationRequest $error) {
    echo $error->code() . ': ' . $error->getMessage() . PHP_EOL;
}
```

Salida:

```text
2026-11-03T23:59:59.999999-05:00
Cierre pendiente: no
```

El tipo aporta 15 DH. El calendario excluye el día de radicación, fines de semana y festivos; el primer día contado es el 13 de octubre. Los plazos del catálogo se consultan en el [resumen normativo](docs/resumen-normativo.md#catálogo-implementado).

## Entrada principal

```text
DeadlineCalculator::calcular(CalculationRequest $solicitud): DeadlineResult
```

Se invoca sobre una instancia creada con `new DeadlineCalculator()`.

### CalculationRequest

| Parámetro | Tipo aceptado | Uso |
|---|---|---|
| `fechaRadicacion` | `DateTimeInterface|string` | Obligatorio; fecha y hora del evento inicial del trámite. |
| `tipoSolicitud` | `RequestType|null` | Selecciona el catálogo. Por defecto `null`. |
| `reglas` | `DeadlineRules|null` | Alternativa al tipo. Por defecto `null`. |
| `ampliarPlazo` | `bool` | Por defecto `false`; usa el total `plazoAmpliado` cuando vale `true`. |
| `suspensiones` | Lista de `Suspension` | Por defecto `[]`; admite pausas cerradas y abiertas. |
| `zonaHoraria` | `string|null` | Opcional. Con tipo, sustituye `America/Bogota`. Con reglas explícitas, debe omitirse o coincidir exactamente con la zona de esas reglas. |

Se exige **exactamente una modalidad: tipo o reglas**. No se recibe una fecha de ampliación, fecha de referencia, configuración semanal ni identificadores de personas o solicitudes.

### Fechas y zonas

Se aceptan objetos `DateTimeInterface` y cadenas `AAAA-MM-DDTHH:MM:SS`, con hasta seis decimales y desplazamiento `-05:00`, `+00:00` o `Z`. Los ejemplos incluyen desplazamiento para evitar ambigüedades. Las fechas imposibles se rechazan; no se corrige automáticamente un 30 de febrero.

Una cadena sin desplazamiento necesita una zona IANA. `CalculationRequest` toma la zona del tipo o de las reglas. En `Suspension` debes proporcionar su propio `zonaHoraria` si usas cadenas sin desplazamiento; no hereda automáticamente la zona de una solicitud que aún no se ha construido. Una hora local inexistente o repetida durante un cambio horario se rechaza si no queda definida inequívocamente.

El cálculo diario usa las fechas civiles de la zona de las reglas; la salida conserva esa zona y la precisión. Las horas representan duración continua incluso durante cambios de horario. No se utiliza la zona predeterminada del servidor para interpretar una entrada sin configuración.

## Reglas explícitas

La modalidad manual permite DC, DH y H. No hereda las políticas de ningún tipo.

<!-- ejemplo-ejecutable: reglas-dc -->
```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Rules\DeadlineRules;

try {
    $reglas = new DeadlineRules(
        plazo: 10,
        unidad: 'DC',
        plazoAmpliado: 20,
        permiteSuspension: true,
        inicio: 'DIA_SIGUIENTE',
        cierre: 'FIN_DEL_DIA',
        ajustarVencimientoAHabil: false,
        zonaHoraria: 'America/Bogota',
        horaCierre: '23:59:59.999999',
    );
    $resultado = (new DeadlineCalculator())->calcular(new CalculationRequest(
        fechaRadicacion: '2026-10-09T10:00:00-05:00',
        reglas: $reglas,
    ));
    echo $resultado->fechaMaximaRespuesta->format('Y-m-d\TH:i:s.uP') . PHP_EOL;
} catch (InvalidCalculationRequest $error) {
    echo $error->code() . ': ' . $error->getMessage() . PHP_EOL;
}
```

Salida: `2026-10-19T23:59:59.999999-05:00`.

| Parámetro de `DeadlineRules` | Regla |
|---|---|
| `plazo` | Entero positivo. |
| `unidad` | `TimeUnit` o cadena exacta `DC`, `DH` o `H`. |
| `plazoAmpliado` | Total entero mayor que `plazo`, obligatorio incluso si no se activa la ampliación. No es una cantidad adicional. |
| `permiteSuspension` | Booleano obligatorio, informativo. |
| `inicio` | `SIGUIENTE_HABIL`, `DIA_SIGUIENTE`, `MISMO_DIA` o `INMEDIATO`, según unidad. |
| `cierre` | `FIN_DEL_DIA` para días; `INMEDIATO` para horas. |
| `ajustarVencimientoAHabil` | Booleano obligatorio; desplaza un vencimiento no hábil al siguiente hábil cuando corresponde. |
| `zonaHoraria` | Zona IANA obligatoria, por ejemplo `America/Bogota`. |
| `calendario` | Por defecto `null`; exige `COLOMBIA` si la unidad es DH, el inicio es `SIGUIENTE_HABIL` o se ajusta el vencimiento a hábil. En los demás casos debe ser `null`. |
| `horaCierre` | Por defecto `null`; obligatoria para días. Formato `HH:MM:SS` con hasta seis decimales. |
| `permiteAmpliacion` | Por defecto `true`, informativo. |

**DH:** cuenta lunes a viernes excepto festivos nacionales de Colombia. No se configuran jornadas, días de la semana, cierres adicionales ni habilitaciones por fecha. `MISMO_DIA` incluye la fecha de radicación solo si es hábil; `DIA_SIGUIENTE` parte de la siguiente fecha civil y cuenta los hábiles desde allí.

**DC:** cuenta todas las fechas. `SIGUIENTE_HABIL` afecta solo el inicio; `ajustarVencimientoAHabil` afecta solo el vencimiento final. Ninguna de esas opciones convierte los días intermedios en DH.

**H:** exige inicio y cierre `INMEDIATO`, ajuste a hábil `false`, calendario y hora de cierre `null`. Son horas continuas. No existe la unidad «horas hábiles».

`FIN_DEL_DIA` utiliza exactamente `horaCierre`; si configuras `17:00:00`, esa será la hora de salida. En el catálogo se usa `23:59:59.999999`.

## Tipos y metadatos

El catálogo tiene 21 tipos en [RequestType](src/Enums/RequestType.php). Sus códigos son cadenas en mayúsculas. Todos los perfiles actuales usan DH o H; DC se ofrece mediante reglas explícitas.

| Operación | Resultado |
|---|---|
| `RequestType::cases()` | Todos los tipos. |
| `RequestType::desdeCodigo('PETICION_GENERAL')` | Tipo o excepción `TIPO_SOLICITUD_DESCONOCIDO`. |
| `$tipo->value` | Código del tipo. |
| `$tipo->descripcion()` | Descripción consultable. |
| `$tipo->reglas()` | Objeto `DeadlineRules` con toda su configuración. |
| `$tipo->reglas('America/Bogota')` | Configuración con la zona indicada. |
| `$tipo->normas()` | Lista de `norma`, `articulo` y `url`. |
| `$tipo->observaciones()` | Precisiones sobre ámbito, decisiones y limitaciones. |
| `$tipo->eventoInicial()` | Evento cuya fecha debe suministrarse como radicación. |
| `TimeUnit::DH->descripcion()` | Descripción de la unidad; también disponible en DC y H. |

Para convertir un código externo con errores controlados usa `desdeCodigo()` dentro del `try`. El método nativo del enum `from()` puede lanzar `ValueError` y `tryFrom()` devuelve `null`.

## Ampliación

`ampliarPlazo=true` selecciona literalmente `plazoAmpliado`, desde el mismo origen del plazo inicial. Repetir la consulta no acumula ampliaciones. El motor no calcula ese total multiplicando ni recibe una fecha de ampliación. Algunos perfiles pasan de 10 a 15 DH o de 15 a 23 DH.

Si `permiteAmpliacion=false`, la ampliación enviada se aplica y genera `AMPLIACION_NO_PREVISTA`. El cliente determina su procedencia y cumple las comunicaciones del trámite.

## Suspensiones

Cada pausa es `new Suspension(inicio: ..., fin: ..., zonaHoraria: ...)`. El inicio es obligatorio, el cierre es opcional y su valor predeterminado es `null`. La zona es opcional si las fechas ya incluyen desplazamiento.

- El inicio real debe estar entre la radicación y el vencimiento sin suspensiones, ambos inclusive. Ese vencimiento usa el plazo ampliado si la bandera está activa. Una pausa anterior no extiende el intervalo permitido para iniciar otra.
- El cierre puede superar ese vencimiento, pero no preceder al inicio. Inicio y cierre iguales aportan cero.
- En DH, tanto el inicio efectivo como la reanudación se sitúan en el siguiente hábil de cada evento. En DC se sitúan en el siguiente día calendario. En H se usan los instantes exactos.
- Una pausa cerrada dentro de la misma fecha civil aporta cero días en DH/DC; en H cuenta la duración transcurrida.
- Las pausas superpuestas o duplicadas se unen para no descontar tiempo dos veces. El cliente no tiene que ordenarlas.
- Una pausa que comienza a surtir efecto después de agotar el plazo no desplaza el vencimiento.
- Si `permiteSuspension=false`, las pausas enviadas se aplican y generan un aviso informativo.

### Cierre pendiente

`fin=null` indica una suspensión abierta. El cálculo toma una sola lectura del reloj y usa `fechaConsulta` como cierre supuesto de todas las abiertas, sin alterar las entradas.

**`conCierrePendiente=true` siempre que haya al menos una suspensión sin cierre**, aunque no modifique el vencimiento o no quede saldo. Siempre acompaña el aviso `SUSPENSION_ABIERTA`. Si no hay suspensiones abiertas, es `false`.

El cliente puede enviar un cierre normal, incluso futuro, para calcular otro escenario. Si todas tienen cierre, el indicador es `false`; no certifica que esos eventos ya hayan ocurrido. Una suspensión abierta que empieza después del instante de consulta produce un error, porque el cierre supuesto sería anterior al inicio.

<!-- ejemplo-ejecutable: suspension-cerrada -->
```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;
use Micrositios\PqrDeadline\Input\Suspension;

try {
    $resultado = (new DeadlineCalculator())->calcular(new CalculationRequest(
        fechaRadicacion: '2026-10-09T10:00:00-05:00',
        tipoSolicitud: RequestType::PETICION_GENERAL,
        suspensiones: [new Suspension(
            inicio: '2026-10-19T09:00:00-05:00',
            fin: '2026-10-26T15:00:00-05:00',
        )],
    ));
    echo $resultado->fechaMaximaRespuesta->format('Y-m-d\TH:i:s.uP') . PHP_EOL;
} catch (InvalidCalculationRequest $error) {
    echo $error->code() . ': ' . $error->getMessage() . PHP_EOL;
}
```

Salida: `2026-11-10T23:59:59.999999-05:00`. Los ejemplos de pausa abierta, ampliación y horas están en [examples/uso.php](examples/uso.php), explicados en su [guía](examples/README.md).

## Resultado y avisos

Una llamada correcta devuelve `DeadlineResult`; no devuelve una respuesta HTTP ni obliga a utilizar JSON.

| Propiedad | Tipo | Significado |
|---|---|---|
| `fechaVencimientoInicial` | `CarbonImmutable` | Plazo original sin ampliación ni suspensiones. |
| `fechaMaximaRespuesta` | `CarbonImmutable` | Plazo aplicable después de las pausas. Siempre contiene una fecha si el cálculo termina correctamente. |
| `fechaConsulta` | `CarbonImmutable` | Instante de cálculo; cierre supuesto de las pausas abiertas. |
| `conCierrePendiente` | `bool` | Hay al menos una suspensión con `fin=null`. |
| `avisos` | Lista de arrays | Cada elemento tiene `codigo` y `detalle`; vacío significa `[]`. |

Puedes acceder a las propiedades directamente, llamar a `toArray()` o serializar mediante `json_encode()` si tu aplicación lo necesita. Las exportaciones conservan los cinco campos y representan las fechas con desplazamiento y seis decimales. Modificar la exportación no altera el objeto original.

| Código de aviso | Cuándo aparece |
|---|---|
| `AMPLIACION_NO_PREVISTA` | Se activó la ampliación con `permiteAmpliacion=false`. |
| `SUSPENSION_NO_PREVISTA` | Se enviaron suspensiones con `permiteSuspension=false`. |
| `SUSPENSION_ABIERTA` | Existe algún `fin=null`. Detalle: «Hay suspensiones sin fecha de cierre. El cálculo utiliza fechaConsulta como cierre supuesto.». |

Los avisos pueden coexistir; no impiden el cálculo. No se informa estado de expediente, mora ni autorización jurídica. Una fecha pasada sigue siendo un resultado válido.

## Errores y try/catch

Una entrada incoherente lanza `InvalidCalculationRequest`, que extiende `InvalidArgumentException`. **No se devuelve un resultado parcial ni un objeto de éxito con un campo de error.** El cliente identifica el fallo al entrar en `catch`. Sin captura, la excepción puede terminar la ejecución.

`$error->code()` devuelve el código de dominio; `getMessage()`, la explicación; `details()`, los datos adicionales. `toArray()` expone `codigo`, `mensaje`, `campos` y `detalles`. Utiliza `code()` para identificar el problema; el método heredado `getCode()` no devuelve ese código textual. Se informa el primer error encontrado. Los campos detectados al construir una `Suspension` se llaman `inicio`/`fin`; los detectados dentro de la solicitud pueden incluir el índice, por ejemplo `suspensiones[0].inicio`.

<!-- ejemplo-ejecutable: error-fecha -->
```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Micrositios\PqrDeadline\DeadlineCalculator;
use Micrositios\PqrDeadline\Enums\RequestType;
use Micrositios\PqrDeadline\Exceptions\InvalidCalculationRequest;
use Micrositios\PqrDeadline\Input\CalculationRequest;

try {
    $resultado = (new DeadlineCalculator())->calcular(new CalculationRequest(
        fechaRadicacion: '2026-02-30T10:00:00-05:00',
        tipoSolicitud: RequestType::PETICION_GENERAL,
    ));
} catch (InvalidCalculationRequest $error) {
    echo $error->code() . PHP_EOL;
    echo $error->getMessage() . PHP_EOL;
}
```

Salida:

```text
FECHA_RADICACION_IMPOSIBLE
La fecha u hora indicada no existe.
```

| Grupo | Códigos y condición |
|---|---|
| Modalidad | `TIPO_Y_REGLAS_MUTUAMENTE_EXCLUYENTES`, `TIPO_O_REGLAS_REQUERIDOS`, `TIPO_SOLICITUD_DESCONOCIDO`. |
| Fechas | Prefijos `FECHA_RADICACION`, `FECHA_SUSPENSION_INICIO`, `FECHA_SUSPENSION_FIN` o `FECHA`, seguidos de `_FORMATO_INVALIDO`, `_IMPOSIBLE`, `_AMBIGUA` o `_ZONA_REQUERIDA`, según el problema. |
| Zona | `ZONA_HORARIA_DESCONOCIDA`; `CONFIGURACION_INCOMPATIBLE` si la zona de la solicitud contradice reglas explícitas. |
| Plazos | `PLAZO_INVALIDO`, `PLAZO_AMPLIADO_INVALIDO`, `UNIDAD_DE_PLAZO_NO_SOPORTADA`. |
| Reglas | `REGLA_INICIO_NO_SOPORTADA`, `REGLA_CIERRE_NO_SOPORTADA`, `REGLAS_INCOMPATIBLES`, `REGLAS_INCOMPLETAS`, `HORA_CIERRE_INVALIDA`. |
| Calendario | `CALENDARIO_NO_SOPORTADO`, `CALENDARIO_NO_DISPONIBLE`. |
| Suspensiones | `SUSPENSIONES_FORMATO_INVALIDO`, `SUSPENSION_INVALIDA`, `SUSPENSION_FIN_ANTERIOR_A_INICIO`, `SUSPENSION_INICIO_ANTERIOR_A_RADICACION`, `SUSPENSION_INICIO_FUERA_DE_PLAZO`, `SUSPENSION_INICIO_POSTERIOR_A_CONSULTA`. |
| Representación | `FECHA_RESULTADO_FUERA_DE_RANGO` si las fechas del cálculo no caben en años de cuatro dígitos. |

La API utiliza argumentos PHP tipados. Omitir un argumento obligatorio o pasar un tipo incompatible puede generar `ArgumentCountError` o `TypeError`; no se convierten en errores de negocio. Construir manualmente un resultado con avisos mal formados genera `InvalidArgumentException`. Las explicaciones no se imprimen automáticamente: los ejemplos eligen mostrarlas en consola.

## Alcance y límites

- Años técnicos admitidos: 0001–9999. No hay una fecha mínima reciente de radicación ni selección automática de la legislación vigente en la fecha histórica del caso.
- El catálogo aplica las reglas configuradas. El cliente determina el tipo correcto y la procedencia de los eventos; puede utilizar reglas explícitas para otro plazo o régimen.
- No se calculan plazos en meses, desistimientos, trámites de notificación ni efectos jurídicos del silencio. Los festivos nacionales se obtienen de la dependencia instalada, sin consulta de red.
- No hay un máximo artificial de suspensiones; el tiempo y la memoria disponibles limitan el trabajo. Las pruebas incluyen 10.000 pausas superpuestas.
- Los indicadores normativos son informativos. Los valores ampliados que aparecen en tipos con permiso `false` son valores de cálculo y no acreditan una autorización legal.

## Desarrollo, pruebas y licencia

Desde la raíz de la librería:

```bash
composer install
composer validate --strict
composer check-platform-reqs
php vendor/bin/phpunit --do-not-cache-result
php examples/uso.php
```

Consulta [CONTRIBUTING.md](CONTRIBUTING.md) para ampliar las pruebas y [CHANGELOG.md](CHANGELOG.md) para los cambios. La estructura pública está en `src/Input`, `src/Rules`, `src/Enums`, `src/Output`, `src/Exceptions` y `src/DeadlineCalculator.php`; calendario y conversión temporal son detalles internos del motor.

Mantenida por **Micrositios**. Licencia [MIT](LICENSE), con autoría de Leonardo Forero Sandoval.
