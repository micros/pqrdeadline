# Documentación de PQR Deadline

La documentación corresponde a la API destinada a 2.0.0. La publicación está pendiente; la instalación local se explica en el README.

1. [README principal](../README.md): requisitos, instalación, inicio rápido, entradas, reglas, suspensiones, resultados y errores.
2. [Ejemplos y resultados](../examples/README.md): ejecución y explicación de los nueve escenarios de [uso.php](../examples/uso.php).
3. [Resumen normativo](resumen-normativo.md): catálogo completo, fuentes oficiales, decisiones funcionales y límites de cobertura.
4. [Migración desde 1.x](migracion.md): adaptación de las aplicaciones que utilizaban la API anterior.
5. [Contribución y pruebas](../CONTRIBUTING.md): herramientas, convenciones y comandos de verificación.
6. [Historial de cambios](../CHANGELOG.md) y [licencia MIT](../LICENSE).

El resultado habitual es un objeto PHP inmutable. Los fallos de datos se capturan con `try/catch`. Las representaciones JSON de los ejemplos son solo una forma de visualizar los valores.
