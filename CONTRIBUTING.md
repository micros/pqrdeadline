# Contribuir y ejecutar pruebas

## Preparación

Usa PHP `^8.4`, la extensión `calendar` y Composer. Desde la raíz de la librería:

```bash
composer install
composer validate --strict --no-check-version
composer check-platform-reqs
composer test
php examples/uso.php
```

La instalación de desarrollo incluye PHPUnit 10. La API y los ejemplos se verifican con PHP 8.4 y 8.5. Los requisitos exactos están en [composer.json](composer.json).

El paquete no versiona `vendor/`, `composer.lock`, cachés, cobertura ni archivos generados. Una aplicación consumidora debe gestionar su propio lock. Los ensayos de instalación limpia y distribución se hacen en directorios temporales.

Para generar un archivo local del paquete:

```bash
composer archive --format=zip --dir=dist
```

Las exclusiones de `archive` en `composer.json` retiran dependencias instaladas, lock, cachés, cobertura, directorios de salida, configuración del editor, registros y `.github/`. Se conservan código, documentación, ejemplos, pruebas y reglas de contribución para mantener disponibles los recursos enlazados desde el README. `.gitattributes` excluye también `.github/` de los archivos de Git. Para una instalación local de tipo `path`, prepara la copia limpia indicada en el README. Los archivos generados también están excluidos del control de versiones mediante `.gitignore`.

## Forma de trabajar

1. Crea una rama descriptiva, por ejemplo `codex/ajuste-calendario`.
2. Añade un caso que reproduzca el comportamiento esperado cuando cambies el cálculo o la API.
3. Implementa el cambio mínimo necesario, preservando las entradas y salidas inmutables.
4. Ejecuta las pruebas y actualiza el README, el catálogo documentado o los ejemplos afectados.
5. Registra el cambio con un mensaje en español y abre una revisión en [GitHub](https://github.com/micros/pqrdeadline).

Utiliza PSR-4 y el estilo del código existente: `strict_types`, tipos explícitos e indentación de cuatro espacios. No incorpores persistencia, llamadas HTTP ni datos personales al motor. Los cambios normativos deben identificar su fuente oficial y diferenciarla de una convención de implementación.

## Pruebas

- `DateInputTest`, `CalculationRequestTest` y `DeadlineRulesTest`: formatos, inmutabilidad, modalidades y errores.
- `RequestTypeTest`: catálogo, metadatos y configuración.
- `BusinessCalendarTest` y `DependencyIntegrationTest`: calendario colombiano e integración con dependencias.
- `DeadlineCalculatorTest` y `DeadlineResultTest`: DH/DC/H, ampliaciones, pausas, cierre pendiente, avisos y exportaciones.
- `AcceptanceTest`: ejemplos, independencia entre consultas y límites representativos.
- `DocumentationTest`: programas ejecutables del README en procesos PHP independientes.
- `DistributionTest`: archivo real de Composer con documentos y ejemplos incluidos y archivos generados excluidos. Requiere el ejecutable `composer` en `PATH`; se ejecuta sin acceso a red y sin plugins ni scripts.

Para una comprobación específica, usa `php vendor/bin/phpunit --filter NombreDelMetodo`. Los casos con suspensiones abiertas fijan el reloj en las pruebas y lo restablecen después. La API no recibe una fecha de referencia.

`composer test` ejecuta la suite con el mismo PHP que utiliza Composer. El workflow `.github/workflows/tests.yml` valida el paquete, comprueba plataforma y PSR-4, y ejecuta la suite en PHP 8.4 y 8.5; añade una combinación PHP 8.4 con las dependencias mínimas estables admitidas. Se activa en pushes, pull requests y ejecución manual cuando esté incorporado a GitHub. Usa permisos de lectura y acciones fijadas a commits; no publica versiones. Revisa periódicamente las versiones indicadas en sus comentarios antes de actualizar esos commits.

Para reproducir la combinación mínima, usa una copia de trabajo independiente:

```bash
composer update --prefer-lowest --prefer-stable --prefer-dist
composer check-platform-reqs
composer test
```

La cobertura puede medirse con PCOV o Xdebug compatibles con el PHP de prueba; estas extensiones no son dependencias del paquete. El objetivo del proyecto es superar el 90 % de líneas, junto con pruebas de escenarios relevantes. Con el controlador disponible:

```bash
php vendor/bin/phpunit --coverage-filter src --coverage-text
```

## Mensajes de commit

Formato: `tipo(ambito): descripción`, con la descripción en español, verbo inicial en infinitivo, sin punto final y hasta 72 caracteres en el encabezado. Tipos permitidos: `feat`, `fix`, `chore`, `refactor`, `test` y `docs`. El cuerpo opcional se separa con una línea vacía.

Para cambios incompatibles, añade `!` antes de `:` y un pie `BREAKING CHANGE:` que explique la adaptación necesaria. Ejemplo:

```text
feat(api)!: sustituir la interfaz pública del calculador

BREAKING CHANGE: utilizar CalculationRequest y DeadlineResult en las integraciones.
```

Las reglas completas del repositorio están en [.ai/rules/general.md](.ai/rules/general.md).

## Documentación

Mantén los ejemplos completos del README dentro de los marcadores `ejemplo-ejecutable`; sus identificadores permiten verificar las salidas. Comprueba los enlaces relativos y evita rutas absolutas de tu equipo. La documentación pública está en español y puede recorrerse desde el [índice](docs/README.md).

## Versiones y publicación

Conserva el nombre `micrositios/pqrdeadline` y declara expresamente `version` en `composer.json`. Su valor debe coincidir con la etiqueta de publicación: `"version": "2.0.0"` corresponde a `v2.0.0`. Los cambios incompatibles requieren una versión mayor. Actualiza conjuntamente la versión y el changelog, verifica las pruebas del commit que se etiquetará y conserva las etiquetas publicadas; una corrección posterior utiliza una etiqueta nueva. La [guía de Packagist](https://packagist.org/about#managing-package-versions) explica el versionado y la sincronización con GitHub.

La validación utiliza `--strict --no-check-version` para aceptar esta decisión explícita. Solo se omite la advertencia sobre la presencia del campo `version`; los demás controles de validación se mantienen.

Durante la publicación se comprobará la sincronización del paquete existente con GitHub y se verificará la versión indexada. El workflow de pruebas no necesita un token de Packagist ni publica automáticamente. El reemplazo y su único commit siguen el procedimiento acordado en la guía de migración.
