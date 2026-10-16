# Resumen normativo y catálogo

**Revisión documental:** 16 de octubre de 2026. **Alcance:** fuentes y decisiones utilizadas por la API destinada a 2.0.0. El catálogo implementado en [RequestType](../src/Enums/RequestType.php) es la referencia de los valores que calcula esta versión; `normas()`, `observaciones()` y `eventoInicial()` permiten consultar sus metadatos.

Este documento separa el fundamento de cada perfil de las convenciones del motor. La aplicación selecciona el régimen aplicable, acredita los eventos y realiza comunicaciones, notificaciones y demás actuaciones. La librería solo calcula con los datos suministrados.

## Catálogo implementado

`Ampliado` es el **total** que usa `ampliarPlazo=true`, en la misma unidad del plazo base. Las columnas A y S corresponden a `permiteAmpliacion` y `permiteSuspension`: son informativas. Un valor «No» no impide aplicar el evento enviado; genera un aviso. En esos perfiles, el total ampliado es una decisión de cálculo y no una autorización normativa.

| Código | Ámbito | Base | Unidad | Ampliado | A | S | Fuente |
|---|---|---:|---|---:|---|---|---|
| `PETICION_GENERAL` | Petición general o particular | 15 | DH | 30 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `SOLICITUD_INFORMACION` | Información o documentos | 10 | DH | 20 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `CONSULTA` | Concepto de autoridad | 30 | DH | 60 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `QUEJA` | Queja bajo régimen general | 15 | DH | 30 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `RECLAMO` | Reclamo bajo régimen general | 15 | DH | 30 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `SUGERENCIA` | Sugerencia que constituye petición | 15 | DH | 30 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `FELICITACION` | Felicitación que constituye petición | 15 | DH | 30 | Sí | Sí | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `PETICION_INFORMACION_ENTRE_AUTORIDADES` | Información entre autoridades | 10 | DH | 20 | No | No | [G](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm) |
| `INFORME_CONGRESISTAS` | Informe del art. 258 | 5 | DH | 10 | No | No | [C](https://cancilleria.gov.co/normograma/compilacion/docs/ley_0005_1992.htm) |
| `RECLAMO_SALUD_SIMPLE` | Riesgo simple | 72 | H | 144 | No | No | [S](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf) |
| `RECLAMO_SALUD_PRIORIZADO` | Riesgo priorizado | 48 | H | 96 | No | No | [S](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf) |
| `RECLAMO_SALUD_VITAL` | Riesgo vital | 24 | H | 48 | No | No | [S](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf) |
| `RECLAMO_SALUD_VITAL_MENOR` | Riesgo vital de niños, niñas y adolescentes | 8 | H | 16 | No | No | [N](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20externa%20n%C3%BAmero%202026151000000007-5%20de%202026.pdf) |
| `COPIAS_SALUD` | Copias sin consulta o urgencia | 3 | DH | 6 | No | No | [S](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf) |
| `PQR_SERVICIOS_PUBLICOS_DOMICILIARIOS` | PQR del contrato de servicios públicos | 15 | DH | 30 | No | No | [P](https://cancilleria.gov.co/normograma/compilacion/docs/decreto_2150_1995.htm) |
| `PQR_TELECOMUNICACIONES` | PQR de usuarios de telecomunicaciones | 15 | DH | 30 | Sí | No | [T](https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_7811_2025.htm) |
| `PQR_POSTAL` | PQR o indemnización postal | 15 | DH | 30 | No | No | [O](https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_5050_2016.htm) |
| `CONSULTA_DATOS_PERSONALES` | Consulta de datos personales | 10 | DH | 15 | Sí | No | [D](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1581_2012.htm) |
| `RECLAMO_DATOS_PERSONALES` | Reclamo de datos personales | 15 | DH | 23 | Sí | No | [D](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1581_2012.htm) |
| `CONSULTA_HABEAS_DATA_FINANCIERO` | Consulta de hábeas data financiero | 10 | DH | 15 | Sí | No | [F](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1266_2008.htm) |
| `RECLAMO_HABEAS_DATA_FINANCIERO` | Reclamo de hábeas data financiero | 15 | DH | 23 | Sí | No | [F](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1266_2008.htm) |

En todos los perfiles DH: inicio `SIGUIENTE_HABIL`, cierre `FIN_DEL_DIA` a `23:59:59.999999`, calendario `COLOMBIA` y ajuste de vencimiento a hábil. En los perfiles H: inicio y cierre `INMEDIATO`, sin calendario, cierre diario ni ajuste a hábil. La zona predeterminada es `America/Bogota`. Todos parten de la recepción del trámite correspondiente, que el cliente envía como `fechaRadicacion`.

El catálogo no incluye un perfil DC. Esa unidad está disponible mediante `DeadlineRules`; no debe confundirse el informe del artículo 258 con otros procedimientos del Congreso.

## Fuentes y relación con los perfiles

### G. Derecho de petición

La [Ley 1755 de 2015, artículo 1, que sustituye los artículos 13–33 del CPACA](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1755_2015.htm), sustenta los plazos de 15 días para petición general, 10 para información/documentos y 30 para consultas (art. 14). Su parágrafo exige comunicar antes de vencer los motivos y un plazo razonable limitado por el doble; el proyecto lo representa como un total máximo de 30, 20 o 60 DH desde el origen.

El art. 17 regula el requerimiento por información o actuaciones a cargo del peticionario y la reactivación al día siguiente del aporte. El motor no cuenta el mes para completar ni decide desistimientos. El art. 30 fija diez días para información o documentos entre autoridades; el perfil se limita a ese supuesto.

Queja, reclamo, sugerencia y felicitación comparten el plazo general solo cuando su contenido constituye una petición sin procedimiento especial. El nombre recibido no sustituye esa clasificación.

### C. Informes de congresistas

La [Ley 5 de 1992, art. 258](https://cancilleria.gov.co/normograma/compilacion/docs/ley_0005_1992.htm), dispone cinco días para el informe solicitado en ejercicio del control del Congreso. Se configura en **5 DH**, con la regla general de días del art. 62 de la Ley 4 de 1913. El cuestionario de citación del art. 249 contiene un plazo distinto de cinco días calendario y no es este perfil. La [Circular 105 de 2018 de Cancillería](https://cancilleria.gov.co/normograma/compilacion/docs/circular_minrelaciones_0105_2018.htm) también distingue ambos supuestos.

### S y N. Salud

La [Circular Externa Supersalud 2023151000000010-5 de 2023](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20Externa%20No.%202023151000000010-5%20de%202023.pdf), instrucción primera, numerales 3.3.2.1–3.3.2.3 del título VII de la Circular 047 de 2007, establece máximos de 72, 48 y 24 horas para los riesgos simple, priorizado y vital. La atención inmediata que requiera el caso no puede posponerse hasta ese máximo. El literal c del numeral 3.3.2.4 establece tres DH para copias; si se requieren para consulta o urgencia, deben clasificarse como reclamos.

La [Circular Externa Supersalud 2026151000000007-5 de 2026](https://docs.supersalud.gov.co/PortalWeb/Juridica/CircularesExterna/Circular%20externa%20n%C3%BAmero%202026151000000007-5%20de%202026.pdf), instrucción tercera, añade el máximo de **8 H para riesgo vital de niños, niñas y adolescentes**. Su entrada en vigor está vinculada a diez días calendario posteriores a la publicación. La librería no selecciona automáticamente versiones históricas de este perfil.

### P. Servicios públicos domiciliarios

La [Ley 142 de 1994, arts. 152 y 158](https://cancilleria.gov.co/normograma/compilacion/docs/ley_0142_1994.htm), junto con el [Decreto 2150 de 1995, art. 123](https://cancilleria.gov.co/normograma/compilacion/docs/decreto_2150_1995.htm), sustenta el término de 15 DH para las peticiones, quejas y recursos relacionados con el contrato de servicios públicos. Las situaciones de práctica de pruebas o demora atribuible al usuario y los efectos del silencio requieren tratamiento por la aplicación. El catálogo no presume que esas situaciones equivalgan a una ampliación o suspensión del régimen general; el total de 30 DH permite calcular la bandera del cliente con aviso.

### T. Telecomunicaciones

La [Resolución CRC 5050 de 2016, art. 2.1.24.3](https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_5050_2016.htm), modificada por el [art. 30 de la Resolución CRC 7811 de 2025](https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_7811_2025.htm), fija 15 DH y permite 15 adicionales cuando se requieren pruebas y se informa al usuario con sus razones. El perfil configura el total de 30 DH. La comunicación de la respuesta y los efectos del silencio quedan fuera del cálculo.

### O. Servicios postales

La [Resolución CRC 5050 de 2016, art. 2.2.7.10](https://normograma.crcom.gov.co/crc/compilacion/docs/resolucion_crc_5050_2016.htm), contempla 15 DH para resolver PQR o indemnización postal y la posibilidad de ampliación por pruebas informada al usuario. Puede consultarse también la [respuesta oficial CRC, radicado 2025514607](https://www.crcom.gov.co/sites/default/files/normatividad/2025514607_1.pdf), que reproduce el artículo.

**Decisión del catálogo:** `permiteAmpliacion=false` indica que este perfil no modela esa habilitación probatoria; no significa que la norma prohíba toda ampliación. Se mantiene el total de cálculo de 30 DH cuando el cliente activa la bandera, con aviso. No se equipara este perfil automáticamente al trámite del recurso de reposición del art. 2.2.7.12.

### D y F. Datos personales y hábeas data financiero

La [Ley 1581 de 2012, arts. 14 y 15](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1581_2012.htm), establece diez DH para consultas, con hasta cinco adicionales, y quince DH para reclamos, con hasta ocho adicionales. De allí los totales **10→15** y **15→23**.

La [Ley 1266 de 2008, art. 16, apartados I y II, en su texto consolidado](https://cancilleria.gov.co/normograma/compilacion/docs/ley_1266_2008.htm), contiene esos mismos plazos para los perfiles de hábeas data financiero. Los reclamos tienen reglas propias para subsanar y coordinar la actuación de fuente y operador.

**Decisión del catálogo:** todos estos perfiles comienzan el siguiente hábil. Para las consultas, cuyas normas hablan de la fecha de recibo, esta exclusión de la fecha inicial es la convención aprobada del motor. El efecto de la subsanación especial no se infiere automáticamente; `permiteSuspension=false` es informativo y las pausas que envíe el cliente se calculan con aviso.

## Calendario y convenciones de implementación

La regla general de exclusión de días feriados en plazos legales en días se consulta en el [art. 62 de la Ley 4 de 1913](https://normativa.colpensiones.gov.co/compilacion/docs/ley_0004_1913.htm). Los festivos y sus traslados tienen fundamento en la [Ley 51 de 1983](https://www.cancilleria.gov.co/sites/default/files/Normograma/docs/pdf/ley_0051_1983.pdf).

El motor fija como semana hábil lunes a viernes y utiliza las definiciones nacionales de Colombia de `cmixin/business-day`. Esa semana y la hora de cierre son convenciones del paquete; no describen el horario particular de toda entidad. No se reciben cierres especiales, habilitaciones ni configuraciones semanales.

Las siguientes reglas son decisiones funcionales aprobadas y no citas literales de todas las normas anteriores:

- Ampliación como una bandera que selecciona un total explícito desde el origen. No hay fecha de ampliación, contador de prórrogas ni evaluación de oportunidad legal.
- Totales ampliados numéricos incluso donde el catálogo no declara prevista la ampliación. Ningún permiso informativo impide calcular lo enviado.
- Inicio y reanudación de suspensión al siguiente hábil en DH, al siguiente día en DC y en el instante exacto en H. En particular, normalizar el inicio de la suspensión al siguiente hábil es una convención uniforme del proyecto.
- Inicio real de cada suspensión limitado por la radicación y el vencimiento sin pausas, original o ampliado. El cierre puede superar ese límite; otras pausas no lo extienden.
- Unión de intervalos superpuestos y duración cero cuando las fechas no descuentan unidades.
- Cierre supuesto al instante de consulta cuando falta `fin`, con `conCierrePendiente=true` y aviso aunque el vencimiento no se desplace.
- Fechas antiguas admitidas sin elegir automáticamente la legislación histórica. Una fecha válida no acredita por sí sola la aplicación temporal de un perfil.

## Cobertura y control de fuentes

Se documentan los perfiles implementados, no todos los procedimientos relacionados con PQR. No se calculan meses para subsanar, desistimiento, traslado por competencia, notificación, silencio administrativo ni clasificación clínica. El cliente selecciona el perfil y aplica esas reglas externas.

Las fuentes oficiales anteriores se contrastaron para esta documentación. Cuando la página de Función Pública citada por el catálogo no pudo recuperarse, se consultó la compilación oficial de Cancillería. Para CRC postal se utilizó además el PDF oficial que reproduce el artículo. Esas fuentes alternativas no cambian los enlaces conservados en `RequestType::normas()` ni los valores del catálogo. Los sitios oficiales pueden reorganizar sus enlaces; ante un cambio normativo deben revisarse fuente, configuración, pruebas y documentación conjuntamente.

[Volver al índice](README.md) · [Uso de la librería](../README.md)
