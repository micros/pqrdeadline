# Ejemplos ejecutables

El archivo [uso.php](uso.php) reúne nueve casos independientes. Instala primero las dependencias desde la raíz de la librería y ejecuta:

```bash
composer install
php examples/uso.php
```

El archivo localiza el autoload respecto de su propia ubicación, por lo que también puede ejecutarse desde otro directorio. Para usar la librería dentro de tu aplicación sigue el [README](../README.md#instalación).

## Cómo leer el programa

Cada elemento de `$ejemplos` construye una `CalculationRequest`. En el bucle final, el `try` engloba esa construcción y `calcular()`; el `catch` recoge `InvalidCalculationRequest`. El programa sigue con los demás casos si alguno falla.

La librería devuelve objetos PHP. Este programa usa `json_encode()` únicamente para imprimirlos con comodidad. Las claves `por_tipo`, `horas`, etc. pertenecen al programa de demostración y no son campos del resultado de la librería.

## Entradas y resultados

Salvo la pausa abierta, todos los casos parten de `2026-10-09T10:00:00-05:00`. Los vencimientos diarios indicados terminan a las `23:59:59.999999`, hora de Bogotá. `fechaConsulta` siempre refleja el instante real de cada llamada.

| Clave del ejemplo | Entrada | Resultado esperado |
|---|---|---|
| `por_tipo` | `PETICION_GENERAL`, 15 DH. | Vencimiento inicial y máximo: 2026-11-03. |
| `reglas_explicitas` | 10 DC desde el día siguiente, total ampliado 20, sin ajuste del vencimiento. | Inicial y máximo: 2026-10-19. |
| `horas` | `RECLAMO_SALUD_VITAL_MENOR`, 8 H. | Inicial y máximo: 2026-10-09 a las 18:00:00.000000. |
| `ampliacion` | Petición general con `ampliarPlazo=true`. | Inicial: 2026-11-03; máximo: 2026-11-25, total de 30 DH. |
| `suspension_cerrada` | Petición general; pausa del 2026-10-19 a las 09:00 al 2026-10-26 a las 15:00. | Inicial: 2026-11-03; máximo: 2026-11-10. |
| `suspension_abierta` | Radicación ayer, pausa desde esa misma fecha y hora, sin cierre. | `conCierrePendiente=true`, fecha calculada al instante de consulta y aviso `SUSPENSION_ABIERTA`. |
| `escenario_con_cierre` | Petición general; pausa del 2026-10-19 a las 09:00 al 2026-10-22 a las 12:00. | Inicial: 2026-11-03; máximo: 2026-11-06. |
| `modalidades_excluyentes` | Tipo y reglas simultáneamente. | El `catch` recibe `TIPO_Y_REGLAS_MUTUAMENTE_EXCLUYENTES`. |
| `fecha_imposible` | Radicación `2026-02-30T10:00:00-05:00`. | El `catch` recibe `FECHA_RADICACION_IMPOSIBLE`. |

En los seis cálculos con cierres definidos o sin suspensiones, `conCierrePendiente=false` y `avisos=[]`. El ejemplo abierto utiliza una fecha relativa para poder ejecutarse cualquier día; por eso su vencimiento no es una constante.

Como referencia reproducible de las pruebas: si la consulta ocurre el **2026-10-22 a las 12:00** en Bogotá, el ejemplo abierto radica y suspende el **2026-10-21 a las 12:00**. Su vencimiento inicial es **2026-11-12** y el calculado suponiendo cierre en la consulta es **2026-11-13**. Esa fecha de consulta es un reloj fijado por las pruebas, no un parámetro que reciba la librería.

## Acceso normal desde PHP

Después de calcular, consulta `$resultado->fechaMaximaRespuesta`, `$resultado->conCierrePendiente` y `$resultado->avisos`. En caso de error, consulta `$error->code()` y `$error->getMessage()` dentro del `catch`. Los programas completos del [README](../README.md#inicio-rápido-cálculo-por-tipo) muestran este acceso sin convertir la respuesta a JSON.

La pausa abierta conserva `fin=null`. Si necesitas evaluar otro cierre, crea una nueva `Suspension` con ese `fin` y una nueva solicitud. Un cierre futuro se trata como un dato explícito; si todas las pausas tienen cierre, el indicador es `false`.

Las pruebas [AcceptanceTest](../tests/AcceptanceTest.php) comprueban estos nueve escenarios, su ejecución desde otro directorio y su salida. [DocumentationTest](../tests/DocumentationTest.php) ejecuta los programas completos del README.
