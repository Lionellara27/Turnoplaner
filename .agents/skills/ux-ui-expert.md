# Experto UX/UI para TurnoPlaner

Esta skill define cómo se diseña y se revisa cualquier pantalla de **TurnoPlaner** desde el punto de vista de la experiencia de usuario y la interfaz. **Consume [`.docs/DESIGN.md`](../../.docs/DESIGN.md)**, que es la única fuente de verdad de la estética (colores, logo, componentes y blueprints). Esta skill no redefine la estética: dice cómo aplicarla bien y cómo verificar que la pantalla sea usable.

---

## 1. Regla de oro: orden de consulta antes de tocar el front

Toda tarea que cree o modifique una vista, un estilo o un script de interfaz sigue este orden, sin excepción:

1. **`.docs/DESIGN.md`**: leerlo completo. Define colores, logo, encabezados de tarjeta, botones, formularios, alertas y tablas.
2. **`.docs/HU-XX.md`**: la historia de usuario en curso y sus criterios de aceptación.
3. **Esta skill** (`ux-ui-expert.md`): flujo, usabilidad, accesibilidad y checklist.
4. **`.agents/skills/frontend-bootstrap5.md`**: cómo implementarlo técnicamente con CI4, Bootstrap 5 y jQuery.

Si algo de esta skill o de la skill de frontend contradice a `DESIGN.md`, **gana `DESIGN.md`**. Si `DESIGN.md` no cubre un caso, se resuelve con esta skill y se propone agregarlo a `DESIGN.md`.

Nunca se inventan colores, tipografías ni componentes nuevos: se usan las variables CSS de `public/css/app.css` (`var(--color-primario)`, etc.), que deben coincidir con `DESIGN.md`.

---

## 2. Conocer al usuario antes de diseñar

TurnoPlaner tiene dos perfiles con contextos muy distintos. Cada pantalla se diseña pensando en quién la usa:

| Perfil | Contexto de uso | Prioridad de diseño |
| :--- | :--- | :--- |
| **Administrador / Jefe de Guardia** | Escritorio, oficina, sesiones largas armando cuadrículas. | Densidad de información, tablas, filtros, acciones rápidas, pocas recargas (AJAX). |
| **Enfermero/a** | Celular, entre turnos o al llegar a la guardia, con poco tiempo. | Mobile first, una acción principal por pantalla, texto grande, botones táctiles. |

Preguntas obligatorias antes de maquetar:

1. ¿Quién usa esta pantalla y desde qué dispositivo?
2. ¿Cuál es **la** acción principal? Debe ser el único botón con color primario.
3. ¿Qué tiene que ver el usuario **antes** de actuar (datos, totales, alertas)?
4. ¿Qué pasa después de actuar (a dónde vuelve, qué mensaje ve)?

---

## 3. Los cuatro estados de toda pantalla

Ninguna vista está terminada si no resuelve sus cuatro estados:

| Estado | Qué mostrar | Ejemplo TurnoPlaner |
| :--- | :--- | :--- |
| **Vacío** | Mensaje explicativo + acción para salir del vacío. | "Todavía no hay sectores cargados." + botón "Crear sector". |
| **Cargando** | Spinner de Bootstrap o botón deshabilitado durante AJAX. | Al asignar un turno en la cuadrícula, la celda muestra `spinner-border-sm`. |
| **Error** | Alerta de `DESIGN.md` (`.alerta--error`) + error junto al campo. | "El DNI ya está registrado." debajo del input DNI. |
| **Éxito** | Alerta `.alerta--ok` y el resultado visible. | "Enfermera María Gómez dada de alta." y aparece en el listado. |

---

## 4. Heurísticas de usabilidad aplicadas (Nielsen)

1. **Visibilidad del estado del sistema:** el usuario siempre sabe dónde está (título de pantalla en el Card Header, ítem del menú activo) y qué está pasando (spinners, alertas).
2. **Lenguaje del usuario:** usar los términos del hospital, no los de la base de datos. Se dice "Turno Noche", "Guardia", "Dar de baja"; nunca "estado = BAJA", "tipo_turno_id" ni "registro eliminado".
3. **Control y libertad:** toda pantalla de alta o edición tiene botón secundario "Cancelar" o "Volver" (blueprint de `DESIGN.md`).
4. **Consistencia:** mismo componente para el mismo problema en todo el sistema. Todas las tablas, formularios y alertas salen de los blueprints de `DESIGN.md`.
5. **Prevención de errores:** deshabilitar o filtrar lo que no se puede elegir (enfermeros en BAJA no aparecen al asignar). Confirmar con modal toda baja lógica.
6. **Reconocer antes que recordar:** selects con nombres legibles ("Pérez, Juan · Guardia"), no IDs. Mostrar las horas acumuladas al asignar, sin obligar a calcularlas.
7. **Eficiencia:** búsqueda y filtros en listados; atajos para el admin (asignar desde la celda de la cuadrícula).
8. **Diseño minimalista:** una sola acción primaria por pantalla; lo secundario en `btn-outline-secondary`.
9. **Ayudar a recuperarse de errores:** el mensaje dice qué pasó y cómo arreglarlo. Mal: "Error de validación". Bien: "La enfermera ya tiene un turno de 06:00 a 14:00 ese día."
10. **Ayuda y documentación:** texto de ayuda (`form-text`) en campos con formato especial.

---

## 5. Formularios (exigencia explícita de la cátedra)

La consigna pide "un nivel estándar de usabilidad, proporcionando las indicaciones correspondientes para cada caso" y validaciones en campos requeridos y con formato especial. Reglas:

1. **Estructura:** grilla `row g-3` + `.form-floating` como indica `DESIGN.md`.
2. **Requeridos marcados:** atributo `required` y un asterisco visible en el label. Al inicio del formulario, la leyenda "Los campos con * son obligatorios".
3. **Indicaciones por campo:** `form-text` debajo de cada campo con formato especial:
   - DNI: "Solo números, sin puntos. Ej: 30123456".
   - Matrícula: "Ej: MP-1001".
   - Carga horaria: "Horas semanales contratadas, entre 1 y 80. Ej: 36 o 40".
   - Horarios: "Formato 24 h. Si el turno termina al día siguiente, se calcula solo".
4. **Tipos de input correctos:** `type="email"`, `type="tel"`, `inputmode="numeric"` en DNI, `type="time"` en horarios, `type="date"` en fechas. En el celular abre el teclado adecuado.
5. **Error junto al campo:** además del bloque de errores general de `DESIGN.md`, marcar el campo con `is-invalid` y mostrar el mensaje en `invalid-feedback`.
6. **No borrar lo cargado:** al volver con errores, repoblar con `old('campo')`. Perder el formulario es el error de UX más frustrante.
7. **Botón de envío:** se deshabilita y muestra spinner al enviar, para evitar doble alta.
8. **Contraseñas:** botón de mostrar/ocultar con Bootstrap Icons (`bi-eye` / `bi-eye-slash`) y requisitos visibles antes de escribir.

---

## 6. Accesibilidad mínima (WCAG 2.1 nivel AA)

1. **Contraste:** texto normal 4.5:1, texto grande o íconos 3:1. Contrastes medidos de la paleta actual de `DESIGN.md`:

| Combinación | Contraste | Resultado |
| :--- | :--- | :--- |
| Texto `#1e2a32` sobre fondo `#f3f6f8` | 13.5:1 | Cumple |
| Texto secundario `#5c6b75` sobre fondo `#f3f6f8` | 5.1:1 | Cumple |
| Blanco sobre primario oscuro `#307f6d` | 4.8:1 | Cumple |
| Error `#c62828` sobre `#ffebee` | 4.9:1 | Cumple |
| Ok `#2e7d32` sobre `#e8f5e9` | 4.6:1 | Cumple |
| Primario oscuro `#307f6d` sobre blanco (links) | 4.8:1 | Cumple |
| Info `#1f5f8b` sobre `#e7f1f8` | 6.0:1 | Cumple |
| Gris `#5c6b75` sobre `#eceff1` (estado "Finalizado") | 4.8:1 | Cumple |
| Gris `#5c6b75` sobre fila de hoy `#e6f4f0` | 4.9:1 | Cumple |
| Blanco sobre `#307f6d` (`.badge-hoy`) | 4.8:1 | Cumple |
| `.text-body-secondary` sobre fila con hover `#f4faf8` | 6.5:1 | Cumple |
| **Blanco sobre primario `#43ab92`** | **2.8:1** | **No cumple: por eso los fondos con texto usan `#307f6d`** |
| **Primario `#43ab92` sobre blanco** | **2.8:1** | **No cumple: nunca como color de texto o link** |
| **Blanco al 85 % (`.text-white-50` o similar) sobre `#307f6d`** | **4.0:1** | **No cumple: sobre verde, blanco pleno** |
| **`.text-secondary` (`#6c757d`) sobre fila con hover** | **4.0:1** | **No cumple: usar `.text-body-secondary`** |
| **`#307f6d` sobre fondo general `#f3f6f8`** | **4.4:1** | **No cumple: links siempre sobre blanco** |
| **`#307f6d` sobre verde suave `#e6f4f0`** (columna de hoy) | **4.2:1** | **No cumple: usar `#266657` (5.9:1)** |
| Aviso `#8a5a00` sobre `#fff4d6` | 5.4:1 | Cumple |
| **Cualquier texto con `opacity: .75`** | **2.9 a 3.5:1** | **No cumple: nunca bajar la opacidad para marcar "pasado" o "inactivo"** |

   Regla práctica: si una combinación nueva no está en esta tabla, medirla antes de usarla (fórmula de luminancia relativa de WCAG; un script de Python de 10 líneas alcanza).

2. **Nunca solo color:** todo estado comunicado con color lleva además texto o ícono. El semáforo de cobertura dice "Cubierto", "Justo" o "Descubierto 2/3", no solo verde, amarillo o rojo.
3. **Labels reales:** cada input tiene `<label for>`; el placeholder no reemplaza al label.
4. **Foco visible:** no quitar el `outline` de foco. Se debe poder recorrer todo con Tab. El anillo por defecto de Bootstrap (azul al 25 %) es casi invisible sobre el verde: el proyecto usa un contorno de 2 px verde oscuro sobre fondos claros y blanco sobre fondos verdes (`app.css`).
5. **Alertas anunciadas:** error con `role="alert"`, éxito e info con `role="status"` (ya resuelto en `partials/alertas.php`). Cambios dinámicos (por ejemplo "Contraseña copiada") en una región `aria-live="polite"`.
6. **Imágenes:** `alt` descriptivo; el logo usa `alt="Logo TurnoPlaner"`.
7. **Tamaño táctil:** en pantallas del enfermero, botones de al menos 44 × 44 px.
8. **Sin cambios de contexto inesperados (WCAG 3.2.2):** un select no recarga la página al cambiar; los filtros se aplican con un botón. Con teclado, recorrer las opciones de un select dispara `change` en Chrome/Edge.
9. **Íconos que son la única etiqueta de un botón:** llevan `<span class="visually-hidden">` con la acción y el nombre ("Editar a Juan Pérez"); `title` no alcanza.

---

## 7. Patrones propios del dominio

1. **Fechas y horas:** mostrar siempre `dd/mm/aaaa` y formato 24 h (`22:00`). Nunca el formato de MySQL (`2026-11-05`) en pantalla.
2. **Turnos que cruzan la medianoche:** mostrar "22:00 → 06:00 (+1 día)" para que no parezca un error.
3. **Cuadrícula semanal (HU-14):** filas = tipos de turno, columnas = días. Cada celda muestra los asignados y el indicador "asignados/mínimo" (ej. `2/3`). El día actual resaltado. En celular, la cuadrícula pasa a lista por día.
4. **Estados de cobertura (HU-14 y HU-15):** "Cubierto n/m", "Faltan k · n/m" y "Sin cubrir 0/m", con ícono y texto oculto completo para lectores de pantalla (`DESIGN.md` 4.8). En el tablero de cobertura, ordenar primero lo que no está cubierto.
5. **Horas acumuladas:** al asignar un turno, mostrar "Horas esta semana: 32 / 36". Si la asignación supera el máximo, avisar antes de enviar.
6. **Bajas lógicas:** modal de confirmación con el nombre del registro ("¿Dar de baja a Juan Pérez?") y aclaración de que se puede reactivar.
7. **Identidad por rol:** el menú muestra solo las opciones del rol. Nunca mostrar un botón que lleva a "acceso denegado".

---

## 8. Checklist de revisión UX/UI

Antes de marcar como terminada una HU con interfaz, verificar:

- [ ] Se consultó `.docs/DESIGN.md` y la pantalla usa sus blueprints y variables de color.
- [ ] La vista extiende `layouts/main` y no tiene `<html>`, `<head>` ni `<body>`.
- [ ] Hay una sola acción primaria y un botón para cancelar o volver.
- [ ] Están resueltos los estados vacío, cargando, error y éxito.
- [ ] Los campos obligatorios están marcados y los de formato especial tienen indicación.
- [ ] Los errores aparecen junto al campo y el formulario conserva lo cargado.
- [ ] Ningún estado se comunica solo con color.
- [ ] Contraste AA en todo texto (ver tabla de la sección 6).
- [ ] Se puede usar solo con teclado y el foco es visible.
- [ ] Fechas en `dd/mm/aaaa` y horas en 24 h.
- [ ] Probado a 375 px de ancho (celular) sin scroll horizontal de la página.
- [ ] Textos en el lenguaje del hospital, sin términos técnicos ni de base de datos.

---

## 9. Decisiones tomadas y aprendizajes (iteración HU-01 a HU-08, 05/11/2026)

Resueltos y reflejados en `.docs/DESIGN.md`:

1. **Contraste del primario.** `#43ab92` queda como acento; los fondos con texto blanco usan `#307f6d` (`.tp-card-header`, `.btn-tp`, barra). Sobre verde, texto blanco pleno.
2. **Tratamiento al usuario:** usted en toda la interfaz.
3. **Alertas centralizadas** en `partials/alertas.php`; el resumen de errores enlaza a cada campo y además el error se muestra junto al campo.
4. **Un solo nombre por estado:** "Pendiente de activación" (cuenta activa que todavía no hizo su primer ingreso), igual en el panel, el badge y el filtro.
5. **Mensajes neutros como info, no como éxito:** "No había cambios para guardar" usa `.alerta--info`.
6. **Credenciales temporales** se muestran una sola vez en una tarjeta con botón Copiar; en http sin TLS se selecciona el texto.
7. **Primer ingreso y Mi perfil piden lo mismo:** si teléfono y domicilio son obligatorios al activar la cuenta, también lo son al editarlos después (para el enfermero/a).

Errores que la revisión encontró y conviene no repetir:

- Escapar dos veces (`esc(old(...))`) corrompe apóstrofes y `&` al reenviar el formulario.
- Un `<div>` dentro de `<dl>` solo puede contener `dt`/`dd` directos: usar `div.row > div.col > dl`.
- `pattern` con `(` o `-` sin escapar dentro de `[...]` se ignora en navegadores modernos (flag `v`).
- Logout dentro del menú colapsable: en celular el botón "siempre visible" queda escondido.

## 10. Plantilla de invocación

```markdown
Actúa como **UX/UI Expert Agent** para TurnoPlaner.
Antes de proponer nada, lee @.docs/DESIGN.md (estética, fuente de verdad), la historia @.docs/HU-XX.md
y tu skill .agents/skills/ux-ui-expert.md.
- Definí el flujo de la pantalla [nombre]: usuario, dispositivo, acción principal y los cuatro estados.
- Proponé la estructura usando solo los blueprints de DESIGN.md.
- Revisá la vista [archivo] con el checklist de la sección 8 y devolvé los ajustes concretos.
```
