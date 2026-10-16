# Historial de cambios

## 2.0.0 — Sin publicar

Nueva implementación de la librería. Esta sección describe la versión prevista; todavía no existe una publicación 2.0.0 derivada de este desarrollo.

### Cambios incompatibles

- Entrada principal `DeadlineCalculator::calcular(CalculationRequest)` y salida `DeadlineResult` con cinco campos.
- PHP mínimo 8.4 y extensión `calendar`; entradas y fechas de salida inmutables.
- Catálogo de 21 tipos con códigos en mayúsculas y metadatos consultables; reglas explícitas mediante `DeadlineRules`.
- Total ampliado configurado explícitamente, incluidos los totales 10→15 y 15→23. Los indicadores de permiso generan avisos sin impedir el cálculo solicitado.
- Suspensiones DH/DC por fechas civiles y H por duración continua; unión de superposiciones, cierres explícitos y pausas abiertas.
- `conCierrePendiente=true` siempre que se envíe alguna suspensión sin cierre. Se utiliza el instante de consulta como cierre supuesto y se informa en `avisos`.
- Errores de datos mediante `InvalidCalculationRequest`, capturable con `try/catch` y con códigos consultables.
- Calendario colombiano de lunes a viernes, sin configuración semanal ni excepciones particulares por fecha.
- Documentación completa de uso, fuentes, ejemplos y migración. Véase la [guía desde 1.x](docs/migracion.md).

## Versiones anteriores

El historial Git y las etiquetas [v1.1.0](https://github.com/micros/pqrdeadline/tree/v1.1.0) y [v1.0.0](https://github.com/micros/pqrdeadline/tree/v1.0.0) conservan su código y documentación.

Según el README anterior, 1.1.0 incorporó Carbon 3 manteniendo aquella API y 1.0.0 correspondió a su primera publicación estable. Los detalles de esas versiones se consultan en sus etiquetas; no describen el comportamiento de 2.0.0.
