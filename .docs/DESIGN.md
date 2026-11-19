# Design System & AI Guidelines (TurnoPlaner)

## 1. Instrucciones para la IA (Stitch / Generadores)

Este documento define las reglas de diseño para generar interfaces web.

- **Contexto de salida:** Los diseños generados serán utilizados como Vistas (`Views`) dentro del framework CodeIgniter 4 (PHP).
- **Formato esperado:** Generar ÚNICAMENTE el fragmento de código HTML dentro de las secciones del layout base.
- **Estructura obligatoria:**

```php
<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>
 [CÓDIGO HTML AQUÍ]
<?= $this->endSection() ?>
```

- **PROHIBIDO:** No incluir las etiquetas `<!DOCTYPE html>`, `<html>`, `<head>` o `<body>`.
- **Framework CSS:** Usar **Bootstrap 5** en toda la interfaz (grillas, componentes, utilidades y espaciado), no solo en formularios. No mezclar otros frameworks CSS.
- **Sin estilos inline:** los colores se aplican con las clases del proyecto (`.tp-card-header`, `.btn-tp`, `.alerta--*`) definidas en `public/css/app.css`, nunca con `style="..."`.
  - **Excepciones (solo datos, nunca estética):** el ancho de las barras `.progress-bar` (`style="width: N%"`, como lo exige Bootstrap) y el color que elige el administrador para un tipo de turno. Se pasa como `style="--color-turno: …; --color-turno-texto: …"` (solo esas dos variables CSS, nunca propiedades directas), con el valor saneado por `TipoTurno::getColor()` y escapado con `esc(…, 'attr')`. Lo usan `tipos_turno/_etiqueta.php`, `planificador/_celda.php` y `cronograma/_turno.php`. Ver 4.7.
- **Sin JavaScript inline:** nada de `<script>` con código ni `onclick` en las vistas. Todo el comportamiento vive en `public/js/app.js` (se carga en el `<head>` del layout) y se engancha por atributos `data-*`. Los datos que necesita el JS viajan en `<script type="application/json" id="…">` (con `json_encode(…, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`) o en `data-*`.
- **Texto que arma el JS:** siempre con `textContent` / `createElement` / `setAttribute`, **nunca `innerHTML` con datos** (nombres, sectores, motivos): evita inyección de HTML.

## 2. Identidad Visual y Colores (TurnoPlaner) - paleta "Sage & Pine"

Paleta adoptada por el grupo en noviembre de 2026 (verde salvia y pino). Los colores se usan **siempre por su variable CSS** (`public/css/app.css`, sección 1); un hex suelto en una vista o en una regla nueva es un error de revisión. Contrastes medidos con la fórmula WCAG 2.1.

| Token | Hex | Uso | Contraste |
| :--- | :--- | :--- | :--- |
| `--color-primario` | `#2d6855` | Acento: borde inferior de la barra y de los encabezados, íconos, detalles | blanco encima 6.52:1 |
| `--color-primario-oscuro` | `#1b4d3e` | Fondo de la barra, encabezados de tarjeta, botón principal (`.btn-tp`), links y contorno de foco | blanco encima 9.64:1; como link sobre `--color-fondo` 9.2:1 |
| `--color-primario-hover` | `#13392e` | `:hover`/`:active` del botón principal y texto del ítem activo del menú | sobre `--color-primario-suave` 10.75:1 |
| `--color-primario-suave` | `#e2efe9` | Fondos de realce (ítem activo, resumen Hoy/Mañana, credenciales) | - |
| `--color-fondo` | `#f8faf9` | Fondo general de la app | - |
| `--color-blanco` | `#ffffff` | Tarjetas y modales (con `.shadow-sm`) | - |
| `--color-texto` | `#1a2420` | Texto principal | sobre `--color-fondo` 15.21:1 |
| `--color-texto-suave` | `#52635b` | Texto secundario (equivale a `.text-body-secondary`) | sobre blanco 6.37:1; sobre `--color-fondo` 6.08:1 |
| `--color-borde` / `--color-borde-suave` | `#cbd6d0` / `#e2e8e4` | Bordes de tarjetas, tablas y separadores | - |
| `--color-borde-fuerte` | `#6b7c74` | Borde que identifica un control (círculo de selección, opción deshabilitada) | 4.41:1 sobre blanco (WCAG 1.4.11 pide 3:1) |
| `--color-fondo-suave` | `#f1f5f3` | Cabeceras de tabla, etiquetas de turno, encabezados de modal | texto 14.5:1; texto suave 5.80:1 |
| `--color-fondo-pasado` | `#e4e9e6` | Días ya pasados en calendarios | texto suave 5.19:1 |
| `--color-fondo-fuera` | `#f4f7f5` | Días de otro mes y opciones deshabilitadas | texto suave 5.91:1 |
| `--color-ok` / `--color-ok-fondo` | `#155e44` / `#e8f6f0` | Confirmaciones, "Cubierto", "En curso" | 6.95:1 |
| `--color-aviso` / `--color-aviso-fondo` | `#8f5e05` / `#fef7e8` | `.alerta--aviso`, "Faltan", "Hora extra" | 5.22:1 |
| `--color-error` / `--color-error-fondo` | `#a42626` / `#fdf0f0` | Errores, validación, "Sin cubrir" | 6.55:1 |
| `--color-info` / `--color-info-fondo` | `#1f5f8b` / `#e7f1f8` | Avisos neutros, "Programado", "Asignado actualmente" | 5.98:1 |

- **Tipografía** (Google Fonts, cargadas en `layouts/main.php` con `display=swap` y fuentes del sistema de respaldo): **Instrument Sans** para el texto y **Wix Madefor Display** para títulos (`h1`-`h5`, marca). Son la única dependencia externa de estilo, además de Bootstrap e íconos.
- **Mensajes:** `.alerta--ok`, `.alerta--info`, `.alerta--error` y `.alerta--aviso` (ver 4.3). No usar `alert-*` de Bootstrap, que no siguen la paleta.
- **No pisar Bootstrap en todo el sitio:** nada de reglas globales sobre `.table-primary`, `.text-primary`, `.bg-primary-subtle`, `.table-responsive`, etc. Si una pantalla necesita otro aspecto, se crea una clase propia (`.tp-…`). En tablas, el fondo de una celda se cambia con la variable `--bs-table-bg` (Bootstrap pinta las celdas con ella y taparía un `background` común).

**Regla de contraste:** sobre fondos verdes el texto va en **blanco pleno**. La jerarquía se marca con tamaño o peso, nunca con opacidad: `.text-white-50`, `opacity-*` y `opacity:` en textos, chips o celdas están prohibidos (bajan el contraste a 2.2-3.7:1).

## 3. Manejo de Marcas e Imágenes (Logo)

- **Ruta del Logo:** Siempre invocar el logo usando PHP/CodeIgniter:

  `<?= base_url('img/logo-64.png') ?>`

- **Clase CSS del Logo:** Usar siempre la clase `.barra__logo` para mantener la proporción de 34px y bordes redondeados:

```html
<img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
```

- **Excepción, pantalla de login:** logo grande `img/logo.png` de 96 px con la clase `.login__logo`. Igual que en la barra, lleva **círculo blanco detrás** (el PNG es transparente y sobre el verde no se lee).

## 4. Componentes Clave y Blueprints

### 4.1. Encabezados de Tarjeta (Card Headers)

Los encabezados de formularios o paneles principales llevan el fondo verde oscuro, el borde de acento y el logo institucional:

```html
<div class="card-header tp-card-header py-3 px-4">
 <div class="d-flex align-items-center">
 <div class="me-3">
 <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
 </div>
 <div>
 <h1 class="h4 mb-0 fw-semibold text-white">[Título de la Pantalla]</h1>
 <p class="mb-0 small subtitulo">TurnoPlaner - Gestión de Turnos Hospitalarios</p>
 </div>
 </div>
</div>
```

### 4.2. Botones de Acción

- **Una sola acción principal por pantalla** (sin contar los modales). Lo demás es secundario.
- **Botón Principal (Submit/Guardar):**

```html
<button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">
 [Texto de Acción]
</button>
```

- **Botón Secundario (Cancelar/Volver):**

```html
<a href="<?= base_url('ruta') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">
 Cancelar
</a>
```

- **Botón de Peligro (confirmar baja en un modal):** `class="btn btn-danger"`.
- **Botón de acción sobre el encabezado verde** (por ejemplo "Dar de alta"): `class="btn btn-light fw-semibold shadow-sm"`.

### 4.3. Formularios y Tablas

- **Formularios:** Utilizar el sistema de grillas de Bootstrap 5 (`row g-3`, `col-md-6`) combinando con la clase `.form-floating` para todos los inputs y selects.
- **Patrón obligatorio de todo formulario POST:**

```php
<form action="<?= base_url('ruta') ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando...">
 <?= csrf_field() ?>
 <p class="small text-body-secondary">Los campos con * son obligatorios.</p>
 <div class="form-floating">
  <input type="text" class="form-control<?= clase_invalido('dni') ?>" id="dni" name="dni"
         value="<?= esc(old('dni', $valorActual, false)) ?>" required pattern="[0-9]{7,10}"
         aria-describedby="ayuda-dni" data-mensaje="El DNI debe tener entre 7 y 10 números.">
  <label for="dni">DNI *</label>
  <?= feedback_campo('dni') ?>
 </div>
 <div id="ayuda-dni" class="form-text">Solo números, sin puntos. Ej: 30123456</div>
</form>
```

  - `old('campo', $defecto, false)`: `old()` ya escapa; se le pasa `false` y se escapa **una sola vez** con `esc()`.
  - `clase_invalido()` y `feedback_campo()` (helper `form_ui`) marcan el error del servidor junto al campo.
  - `data-mensaje` es el texto que muestra `public/js/app.js` cuando falla la validación del navegador.
  - `data-enviando` deshabilita el botón y muestra un spinner al enviar: evita el doble envío. También en los formularios de los modales.
  - Contraseña con botón de mostrar/ocultar: `.input-group.has-validation` con `.form-floating<?= clase_invalido('password') ?>` y el botón `.btn.btn-outline-secondary.btn-ojo`.
- **Excepción, filtros y búsqueda:** `form-label` arriba del campo (sin floating), botón "Filtrar" explícito y **sin autoenvío** al cambiar un select (WCAG 3.2.2).
- **Mensajes de error, éxito e info:** se renderizan **solo** desde `app/Views/partials/alertas.php`, que el layout incluye una vez. **Las vistas no repiten estos bloques.** Flashdata que reconoce:
  - `success` → `.alerta.alerta--ok` con `role="status"`.
  - `info` → `.alerta.alerta--info` con `role="status"`.
  - `error` → `.alerta.alerta--error` con `role="alert"`.
  - `errors` (`[campo => mensaje]`) → resumen "Por favor, revise los N campos marcados" con un enlace a cada campo, además del error junto al campo.
  - `credenciales` → tarjeta con la contraseña temporal para entregar al enfermero/a.
  - En una vista angosta (login), el controlador pasa `'alertasEnVista' => true` y la vista incluye el parcial dentro de su tarjeta.
- **Tablas de Datos:** Envolver siempre en `<div class="table-responsive">` usando las clases `.table .table-hover .align-middle`. Las acciones por fila deben usar botones pequeños (`.btn-sm`) con texto oculto para lectores de pantalla (`<span class="visually-hidden">Editar a Juan Pérez</span>`). Los modales se ubican fuera de la `<table>`.

### 4.4. Badges de Estado

Siempre ícono + texto, nunca solo color:

| Estado | Clases | Ícono |
| :--- | :--- | :--- |
| Activo | `badge bg-success-subtle text-success-emphasis border border-success-subtle` | `bi-check-circle` |
| Pendiente de activación | `badge bg-warning-subtle text-warning-emphasis border border-warning-subtle` | `bi-hourglass-split` |
| Baja | `badge bg-secondary-subtle text-secondary-emphasis border` | `bi-slash-circle` |

**Indicadores del panel** (tarjetas con un número): ícono de Bootstrap Icons dentro de un círculo de 52 px con el color del estado, y al lado el número y el texto. Clases `.tarjeta-indicador` + `.indicador--ok` / `.indicador--pendiente` / `.indicador--baja`, con el ícono en `<span class="indicador__icono" aria-hidden="true">`. Mismos íconos que los badges (`bi-person-check`, `bi-hourglass-split`, `bi-person-slash`). Íconos siempre de **Bootstrap Icons** (icons.getbootstrap.com), para mantener el mismo estilo de línea en todo el sistema.

### 4.5. Datos de Solo Lectura

Datos que el usuario ve pero no puede editar (por ejemplo, DNI y matrícula en "Mi perfil"). Estructura válida para W3C:

```html
<div class="row g-2">
 <div class="col-6 col-md-4"><dl class="dato-lectura mb-0"><dt>DNI</dt><dd><?= esc($usuario->dni) ?></dd></dl></div>
</div>
```

### 4.6. Navegación: Barra Superior + Menú Lateral

El layout (`app/Views/layouts/main.php`) arma la navegación; **las vistas no agregan menús propios**.

- **Barra superior** (verde oscuro, `.barra`): botón de tres rayitas (solo en pantallas chicas), logo y nombre y rol del usuario (desde 768 px).
- **Menú lateral** (blanco, `.menu-lateral`), con el componente `offcanvas-lg` de Bootstrap 5:
  - **Desde 992 px:** fijo a la izquierda, 240 px de ancho. Un **botón redondo de 32 px clavado sobre la línea derecha del menú, a mitad de altura** (`.menu-lateral__achicar`), lo achica a solo íconos (76 px) y lo vuelve a agrandar; la flecha gira según el estado y la preferencia se recuerda en el navegador.
  - **Menos de 992 px:** oculto; se abre desde las tres rayitas como panel deslizable desde la izquierda, con el nombre y el rol del usuario arriba y una X para cerrar.
- **Estructura del menú:** arriba las secciones del rol; **abajo, fijo al fondo** (`.menu-lateral__pie`), **Mi perfil** (sin título de sección: la línea divisoria alcanza) y debajo el botón **"Cerrar sesión"** (`btn btn-outline-danger w-100`). En el menú achicado el botón queda solo con el ícono.
- **Cerrar sesión durante la activación de cuenta (HU-01):** como no hay menú, el botón va arriba a la derecha en la barra superior.
- **Ítems agrupados por sección** (título en mayúsculas chicas, `.menu-lateral__seccion`). Cada ítem: ícono de Bootstrap Icons + texto, mínimo 44 px de alto.
- **Ítem activo:** fondo `--color-primario-suave`, texto `--color-primario-hover` (5.9:1), peso semibold y borde izquierdo de 3 px en `--color-primario`. Además lleva `aria-current="page"`.
- **Por rol:** el menú muestra solo las opciones del rol (Administrador: Gestión; Enfermero/a: Mis turnos; los dos con Mi perfil abajo). Nunca mostrar un ítem que lleva a "acceso denegado".
- **Agregar una sección nueva** (por ejemplo HU-09 Sectores): sumar `['admin/sectores', 'bi-hospital', 'Sectores']` al arreglo `$menu` del layout, en el grupo que corresponda.
- **Contenido junto al menú:** el espacio útil se achica 240 px. Las tablas anchas se muestran como tabla recién desde 1200 px (`d-xl-block`) y como tarjetas debajo de ese ancho (ver skill de frontend, sección 6). Los calendarios mensuales: el del Planificador desde 1400 px (`d-xxl-block`) y los del Panel y Mi cronograma desde 1200 px; debajo, una lista por día. Toda pantalla se revisa en 360 px, tablet y computadora, sin scroll horizontal.

### 4.7. Etiqueta de Tipo de Turno

- Parcial `app/Views/admin/tipos_turno/_etiqueta.php` (clase `.etiqueta-turno`): píldora con fondo `--color-fondo-suave`, texto `--color-texto` (14.5:1), borde `--color-borde` y **borde izquierdo de 4 px con el color del turno** (`--color-turno`). Siempre el nombre escrito: el color solo ayuda a reconocerlo, nunca es la única señal.
- **Cada tipo de turno pertenece a un sector** (`tipos_turno.sector_id`): el mismo nombre ("Mañana") puede existir en varios sectores con horarios distintos (por ejemplo, UTI trabaja en turnos de 6 h). El formulario pide el sector primero y el listado se puede filtrar por sector.
- **Paleta cerrada** (`TipoTurnoModel::PALETA`). El color pleno con texto encima se usa en el selector de color (`.opcion-color`); el texto blanco u oscuro lo elige `TipoTurno::colorTexto()` por contraste:

| Color | Hex | Texto | Contraste |
| :--- | :--- | :--- | :--- |
| Ámbar | `#c98a00` | `#1e2a32` | 4.97:1 |
| Naranja | `#b34700` | blanco | 5.50:1 |
| Azul noche | `#3d5a99` | blanco | 6.73:1 |
| Violeta | `#7b4ea3` | blanco | 6.07:1 |
| Verde institucional | `#307f6d` | blanco | 4.79:1 |
| Bordó | `#b03a5b` | blanco | 5.83:1 |
| Azul petróleo | `#1f6f8b` | blanco | 5.67:1 |
| Gris | `#5c6b75` | blanco | 5.50:1 |

- **Selector de color:** `.btn-check` + `.opcion-color` (cada opción con su nombre, 44 px de alto; la elegida lleva tilde y doble anillo).
- **Horario:** parcial `tipos_turno/_rango.php` → "22:00 → 06:00 (+1 día)". La flecha se oculta a los lectores de pantalla, que leen "22:00 a 06:00, termina al día siguiente".

### 4.8. Estados de Cobertura

Clases `.cobertura--ok`, `.cobertura--parcial`, `.cobertura--vacio`. Siempre ícono + texto, más un texto oculto completo para lectores de pantalla.

| Estado | Texto visible | Texto para lector de pantalla | Ícono | Colores | Contraste |
| :--- | :--- | :--- | :--- | :--- | :--- |
| Cubierto | "Cubierto n/m" | "Cubierto: n de m enfermeros mínimos." | `bi-check-circle-fill` | `--color-ok` / `--color-ok-fondo` | 6.95:1 |
| Faltan | "Faltan k · n/m" | "Faltan k enfermeros: n de m asignados." | `bi-exclamation-triangle-fill` | `--color-aviso` / `--color-aviso-fondo` | 5.22:1 |
| Sin cubrir | "Sin cubrir 0/m" | "Sin cubrir: 0 de m enfermeros mínimos." | `bi-x-circle-fill` | `--color-error` / `--color-error-fondo` | 6.55:1 |

- Un solo parcial dibuja el chip: `app/Views/admin/planificador/_cobertura.php` (lo usan el Planificador y el Panel). En celdas angostas el chip puede ocupar dos renglones; nunca se recorta el texto.

- Solo cuentan los enfermeros/as **activos**; uno dado de baja se muestra con el badge "De baja · no cuenta".
- **Prohibido bajar la opacidad** de chips, celdas o textos (con `opacity: .75` el contraste cae a 2.9–3.5:1).

### 4.9. Planificador: calendario mensual por sector (HU-14)

- **Encabezado:** h1 "Planificador" (igual que el menú) y subtítulo "Sector · Mes · Dotación mínima". La acción principal "Asignar turno" va como `btn-light` sobre el encabezado verde.
- **Sector:** botones `.selector-sector`; el activo lleva `aria-current="page"`, borde y tilde (no solo color). Mes anterior/siguiente en `.nav-mes`, sin `btn-sm`.
- **Desde 1400 px** (`d-xxl-block`): calendario `.tp-calendario` de lunes a domingo (`th scope="col"`, `caption` oculto con sector y mes). Cada celda (`.tp-celda-dia`) muestra los tipos de turno del sector con su chip de cobertura (4.8) y un "+" de 44×44 px (`.tp-shift-row__btn-add`) con texto oculto "Asignar turno X el día N". El día se abre con un botón propio **"Ver día N"** (`.tp-celda-dia__ver`): la celda nunca es un botón ni tiene controles anidados.
- **Debajo de 1400 px:** lista por día (`.dia-lista`, grilla de 1/2/3 columnas) con los mismos datos; en el mes actual los días pasados van plegados (`.plan-dias-anteriores`, `<details>`).
- **Hoy:** marco de 2 px en `--color-primario-oscuro`, la marca `.badge-hoy` y `aria-current="date"`. **Días pasados:** fondo `--color-fondo-pasado` (sin opacidad). **Otro mes:** `--color-fondo-fuera`. Fines de semana: `.cal-mes__finde`.
- **Panel lateral del día** (`.tp-drawer`, offcanvas): por cada turno (`.tp-turno-bloque`), el personal asignado (`.tp-enfermero-fila`) con botones de ícono `.btn-icono` de 44 px y nombre accesible completo ("Quitar a Pérez, Juan del turno Tarde del día jueves 19/11/2026"), la marca "HE" si es hora extra (4.12) y "Asignar" para ese turno.
- **Modales:** `#modalAsignar` (asignar o reasignar por AJAX, con el selector de 4.10) y `#modalQuitar` (confirmación). Al cerrar, el foco vuelve a un control visible (el "Ver día" que abrió el panel). Los errores del servidor (422) se muestran dentro del modal y el botón recupera su texto e ícono.
- **CSRF:** cada respuesta AJAX trae el token nuevo (`csrf`) y `app.js` lo copia en **todos** los `input[name=csrf_test_name]` de la página; el token se regenera en cada envío (`Config\Security::$regenerate = true`).
- **Mejora progresiva:** sin JavaScript, el "+" es un link al formulario completo de asignación (que funciona sin JS, ver 4.10) y desde el formulario de reasignar se puede eliminar. El panel lateral es solo con JS.
- **Carga horaria del personal** (`.tp-carga-item`): por enfermero/a, **horas de la semana actual contra su tope semanal** ("32 h de 36 h esta semana") con barra de progreso (`.carga-semana--ok/alto/completo`) y "Le quedan X h", más el total del mes y sus horas extras. Filtros con `form-label` visible (buscar, especialidad, carga, orden) y botón "Ver ficha" que abre `#modalDetalleEnfermero`.
- **Ventana de planificación:** se asigna desde hoy hasta hoy + 2 meses (`AsignacionModel::fechaMaximaHabilitada()`); el aviso con la fecha límite se muestra debajo del calendario.

### 4.10. Selector de enfermero/a (formulario de asignación y modal)

- **Radios reales, no `<select>` ni `div role="radio"`:** parciales `planificador/_selector_enfermeros.php` y `_opcion_enfermero.php`. Cada opción es `<input type="radio" class="btn-check" name="usuario_id" required>` + `<label>` como tarjeta (`.tp-enfermero-opcion`), dentro de un `<fieldset>` con `<legend>` visible "Enfermero/a *". Así funciona sin JS, con flechas del teclado, `disabled` y validación nativa.
- **Cada opción muestra:** nombre (`aria-labelledby`), especialidad, matrícula, **horas de la semana contra su tope** ("32 h de 36 h esta semana") y el estado de disponibilidad (`aria-describedby`):

| Estado | Clase | Significado | Colores | Contraste |
| :--- | :--- | :--- | :--- | :--- |
| Disponible | `.estado-disponibilidad--disponible` | Libre y dentro de su carga semanal | `--color-ok` / `--color-ok-fondo` | 6.95:1 |
| Hora extra | `.estado-disponibilidad--extra` | Libre, pero ya cumplió su carga: el turno se registra como hora extra | `--color-aviso` / `--color-aviso-fondo` | 5.22:1 |
| No disponible | `.estado-disponibilidad--no-disponible` | Turno superpuesto o sin carga horaria: va `disabled` con el **motivo escrito** | texto suave sobre `--color-fondo-fuera`, borde punteado `--color-borde-fuerte` | 5.91:1 |
| Asignado actualmente | `.estado-disponibilidad--actual` | Al reasignar, la persona que ya tiene el turno | `--color-info` / `--color-info-fondo` | 5.98:1 |

- **Orden** (lo decide el servidor, `AsignacionModel::evaluarDisponibilidad()`): disponibles con menos horas primero, luego hora extra, al final no disponibles.
- **Filtros** (`.selector-enfermeros`, componente `FiltroLista` de `app.js`): buscador por nombre o matrícula (Enter no envía el formulario), especialidad, "Con horas disponibles" y orden; todos con `form-label` visible y 44 px de alto. El contador de resultados tiene `role="status"`. Si el filtro oculta a la persona elegida se avisa "Seleccionado: X (oculto por el filtro)".
- **Modal del Planificador:** pide la disponibilidad real por `GET admin/asignaciones/disponibilidad?sector_id&tipo_turno_id&fecha[&excluir]` (JSON, solo AJAX) y muestra "Cargando…" o un error con "Reintentar".
- **El servidor es la barrera:** aunque alguien fuerce un id por POST, `validarReglas` rechaza superposiciones, inactivos y turnos de otro sector.
- **360 px:** la tarjeta se apila en columna y los badges hacen salto de línea (`min-width: 0` en el nombre).

### 4.11. Mi Cronograma (portal del enfermero/a, HU-17)

- **Arriba, siempre:** tarjeta `.proximo-turno` con dos columnas, **Hoy** y **Mañana** (fecha larga). Cada una muestra sus turnos (etiqueta, horario y sector, apilados sin "·" sueltos) o "Sin turno asignado". Si no hay nada ni hoy ni mañana, agrega "Próximo turno:" con el siguiente programado; si una Noche empezó ayer y sigue, "Turno en curso (empezó ayer)". No depende de la semana o el mes que se esté mirando.
- **Vista inicial: mes.** Desde 1200 px, calendario mensual `.tp-calendario` (lunes a domingo) con clases `.tp-calendario__dia` (`--hoy`, `--fuera`), `__numero`, `__nota`, `__turno` y `__sector`; cada turno muestra etiqueta, sector, horario, **estado** y la marca "HE". Debajo de 1200 px, la agenda por día de `_dias.php`.
- **Navegación:** dos `btn-group` (Mes / Semana y Anterior / Este mes o Esta semana / Siguiente), sin `btn-sm` (44 px de alto). El botón activo lleva `aria-current`. En celular, Anterior y Siguiente quedan como flechas con texto oculto.
- **Totales:** "N turnos en este período · X h en total" y, si hay, "HE N h de horas extras"; solo si hay turnos.
- **Estado vacío** (semana o mes): ícono `bi-calendar-x`, "No tiene turnos asignados en …" y, si no es el período actual, el botón "Ir a la semana actual" / "Ir al mes actual".
- **Lista de días** (parcial `cronograma/_dias.php`): tabla desde 1200 px (`d-xl-block`, borde en el contenedor) y agenda por día debajo (`d-xl-none`). La semana muestra los 7 días; los días sin turno dicen "Sin turno asignado" (no "Franco": que no haya turno no significa que sea franco). El mes muestra solo los días con turno y, en el mes actual, los días ya pasados van plegados en un `<details>` ("Ver N días anteriores de este mes").
- **Hoy:** fila/bloque con fondo `--color-primario-suave` (o marco de 2 px en el calendario), `aria-current="date"` y la marca `.badge-hoy` (blanco sobre `--color-primario-oscuro`, 9.64:1).
- **Noche:** el horario muestra el día de salida, "22:00 → 06:00 del mié 11/11"; el día siguiente lleva la nota "Sale de Noche a las 06:00" (ícono `bi-moon-stars`).
- **Lectores de pantalla:** en la agenda, cada turno tiene una frase completa oculta ("Mañana en UTI (área crítica), de 06:00 a 14:00, programado, registrado como hora extra.") y las piezas visuales van `aria-hidden`.

| Estado | Clase | Ícono | Colores | Contraste |
| :--- | :--- | :--- | :--- | :--- |
| Programado | `.estado-turno--programado` | `bi-calendar-check` | `#1f5f8b` / `#e7f1f8` | 5.98:1 |
| En curso | `.estado-turno--en-curso` | `bi-play-circle-fill` | `--color-ok` / `--color-ok-fondo` | 6.95:1 |
| Finalizado | `.estado-turno--finalizado` | `bi-check2-circle` | `--color-texto-suave` / `#eceff1` | 5.52:1 |

"Próximo" se reserva para la tarjeta de arriba; en la lista, un turno que todavía no empezó es "Programado".

### 4.12. Horas extras

- **Regla (HU-13, acordada por el grupo):** superar la carga horaria semanal **no se rechaza**: el turno se guarda y el excedente queda registrado como hora extra (`asignaciones.es_extra` / `minutos_extra`). Se calcula por semana (lunes a domingo), en orden cronológico de inicio, y **se recalcula toda la semana** al crear, reasignar o eliminar un turno (`AsignacionModel::recalcularExtrasSemana()`), así el resultado no depende del orden en que se cargó.
- **Marca visual:** `.badge-extra` "HE" (`#6d4a00` sobre `#fdf0d5`, 7.07:1) con texto oculto "Hora extra"; nunca solo un `title`. El aviso al asignar dice "(registrado como HORA EXTRA cubierta: N h)".

## 5. Foco y Accesibilidad

- **Foco visible:** contorno de 2 px en `--color-primario-oscuro` sobre superficies claras y en blanco sobre superficies verdes (barra y encabezados), con `outline-offset: 2px`. Está definido en `app.css`; **prohibido quitarlo**.
- **Contraste mínimo WCAG AA:** 4.5:1 para texto normal. Ver la tabla de la skill `.agents/skills/ux-ui-expert.md`, sección 6.
- **Tamaño táctil:** botones, links de acción e íconos clicables de al menos 44×44 px (incluye el "+" del calendario y los íconos del panel lateral).
- **Estados:** siempre ícono + texto (y texto oculto completo cuando el visible es abreviado, como "3/2" o "HE"). Nunca solo color.

## 6. Tono

- Tratamiento de **usted** en toda la interfaz ("Ingrese", "Revise", "Haga clic", "No tiene"); nunca voseo ("Hacé", "tenés").
- **"Sector"** para los servicios del hospital (UTI, Guardia…), en todas las pantallas y mensajes; no "zona".
- Términos del hospital, no de la base de datos: "legajo", "dar de baja", "guardia", "pendiente de activación"; nunca "estado = BAJA" ni "registro eliminado".
- Fechas `dd/mm/aaaa` (helper `fecha_ar()`) y horas en formato 24 h.
