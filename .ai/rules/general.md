# Reglas generales del repositorio

## Mensajes de commit

Todos los mensajes de commit deben seguir la especificación **Conventional Commits 1.0.0**, con las siguientes reglas particulares del repositorio.

### 1. Formato obligatorio

El encabezado debe utilizar exactamente este formato:

`tipo(ámbito): descripción`

- El encabezado debe ocupar **una sola línea** y contener un único tipo, ámbito y descripción.
- El ámbito es obligatorio.
- El encabezado completo no debe superar los 72 caracteres, incluidos los separadores.
- Debe existir exactamente un espacio después de los dos puntos.
- El encabezado no debe terminar con punto.
- El cuerpo es opcional y debe separarse del encabezado mediante una línea en blanco.
- No se deben generar varios encabezados para describir un mismo commit.

### 2. Tipos permitidos

Utilizar exclusivamente los siguientes tipos, escritos en inglés y en minúsculas:

| Tipo | Uso |
|---|---|
| `feat` | Incorporar una funcionalidad o capacidad nueva. |
| `fix` | Corregir errores de comportamiento. |
| `chore` | Mantener dependencias, herramientas, configuración o infraestructura del proyecto. |
| `refactor` | Reestructurar código sin modificar su comportamiento observable. |
| `test` | Agregar o modificar exclusivamente pruebas y sus recursos auxiliares. |
| `docs` | Agregar o modificar exclusivamente documentación. |

**Criterios de selección:**

- Seleccionar el tipo según el propósito principal del commit.
- Utilizar `feat` cuando una funcionalidad nueva incluya sus pruebas.
- Utilizar `fix` cuando una corrección incluya pruebas de regresión.
- Reservar `test` para commits dedicados a pruebas.
- Reservar `docs` para commits dedicados a documentación.
- No utilizar tipos distintos de los seis definidos.

### 3. Ámbito (scope)

El ámbito debe identificar el componente principal afectado.

Reglas:

- Utilizar nombres breves, descriptivos y estables.
- Escribir únicamente en minúsculas, sin espacios, tildes ni caracteres especiales.
- Utilizar guiones medios cuando se requieran nombres compuestos.
- Mantener consistencia con los ámbitos utilizados previamente en el repositorio.
- Evitar ámbitos genéricos como `varios` o `cambios`.

Ejemplos válidos: `fechas`, `calendario`, `suspensiones`, `reglas`, `composer`, `pruebas`, `api`.

### 4. Redacción

- Redactar la descripción y el cuerpo en español.
- Conservar en su idioma original los nombres de clases, funciones, variables, archivos, bibliotecas y demás identificadores técnicos.
- Comenzar la descripción con un verbo en infinitivo y minúscula, por ejemplo: `agregar`, `corregir`, `configurar`, `simplificar`, `validar`.
- Expresar concretamente el cambio realizado.
- Evitar descripciones genéricas como `realizar cambios`, `actualizar archivos` o `hacer ajustes`.
- Priorizar la precisión y la brevedad.
- Utilizar el cuerpo para explicar el motivo, el alcance, el comportamiento resultante o la validación realizada.
- Evitar repetir en el cuerpo la información del encabezado.

### 5. Commits que afectan varios componentes

Siempre que sea viable, separar los cambios independientes en commits individuales.

Cuando un mismo commit incluya modificaciones de varios componentes:

1. Generar **un único encabezado** que represente el propósito principal del conjunto de cambios.
2. Seleccionar como ámbito el componente principal afectado.
3. Dejar una línea en blanco después del encabezado.
4. Describir los cambios adicionales en el cuerpo, preferiblemente mediante una lista breve.
5. No agregar otros encabezados con formato `tipo(ámbito): descripción` dentro del mismo mensaje.

Ejemplo:

`feat(calendario): incorporar nuevas reglas de vencimiento`

- Agregar validación de suspensiones.
- Ajustar la normalización de fechas.
- Incorporar pruebas para días festivos.

El encabezado resume el cambio principal y el cuerpo identifica las modificaciones complementarias.

### 6. Cambios incompatibles (Breaking Changes)

Cuando una modificación introduzca incompatibilidades en la API pública o en otros contratos externos del proyecto:

- Agregar `!` inmediatamente después del ámbito.
- Incluir el marcador estándar `BREAKING CHANGE:` en una nota al pie.
- Separar la nota al pie del encabezado o del cuerpo mediante una línea en blanco.
- Explicar en español la incompatibilidad y las acciones necesarias para adaptar el código consumidor.
- Conservar exactamente la escritura del marcador `BREAKING CHANGE:`.

Ejemplo:

`feat(api)!: sustituir la interfaz pública del calculador`

`BREAKING CHANGE: reemplazar DeadlineWrapper por DeadlineCalculator. Actualizar las integraciones para enviar los datos mediante CalculationRequest.`

Estas reglas se aplican a cualquier tipo de commit que introduzca una incompatibilidad.

### 7. Ejemplos de encabezados válidos

- `feat(calculo): agregar vencimientos en días hábiles`
- `fix(suspensiones): evitar descontar dos veces un día`
- `chore(composer): configurar el autoload del paquete`
- `refactor(fechas): simplificar la normalización de instantes`
- `test(calendario): cubrir festivos que caen en lunes`
- `docs(api): documentar el resultado de una suspensión abierta`

### 8. Validación obligatoria

Antes de generar o ejecutar un commit, verificar que:

- El encabezado sigue exactamente la estructura establecida.
- El tipo pertenece a los seis tipos permitidos.
- El ámbito está presente y cumple las reglas de nomenclatura.
- La descripción está escrita en español y comienza con un verbo en infinitivo.
- El encabezado no supera los 72 caracteres.
- Existe un solo encabezado, independientemente del número de componentes modificados.
- El cuerpo, cuando corresponde, está separado mediante una línea en blanco.
- Los cambios incompatibles incluyen `!` y `BREAKING CHANGE:`.
- El mensaje describe fielmente los cambios realizados, sin atribuir modificaciones o validaciones que no se hayan efectuado.

El mensaje final debe ser válido conforme a estas reglas, sin explicaciones adicionales ni texto ajeno al commit.