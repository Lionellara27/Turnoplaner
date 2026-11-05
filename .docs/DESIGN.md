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
  - **Excepciones (solo datos, nunca estética):** el ancho de las barras `.progress-bar` (`style="width: N%"`, como lo exige Bootstrap) y el color que elige el administrador para un tipo de turno. Se pasa como `style="--color-turno: …; --color-turno-texto: …"` (solo esas dos variables CSS, nunca propiedades directas), con el valor saneado por `TipoTurno::getColor()` y escapado con `esc(…, 'attr')`. Ver 4.7.

## 2. Identidad Visual y Colores (TurnoPlaner)

Usar los colores corporativos definidos en las variables CSS del proyecto (`public/css/app.css`):

- **Primary (`--color-primario`):** `#43ab92` (Verde Esmeralda Clínico) - **Acento**: borde inferior de la barra y de los encabezados de tarjeta, íconos, detalles y texto grande. **Nunca como fondo con texto blanco ni como color de texto o de links** (contraste 2.8:1, no cumple WCAG AA).
- **Primary Dark (`--color-primario-oscuro`):** `#307f6d` - Fondo de la barra, de los encabezados de tarjeta y del botón principal (texto blanco, 4.8:1). Color de los links y del contorno de foco sobre superficies claras.
- **Primary Hover (`--color-primario-hover`):** `#266657` - `:hover` y `:active` del botón principal; links sobre el fondo general `#f3f6f8`.
- **Primary Soft (`--color-primario-suave`):** `#e6f4f0` - Fondos de realce (tarjeta de credenciales temporales).
- **Background (`--color-fondo`):** `#f3f6f8` - Fondo general de la app.
- **Surface:** `#ffffff` - Fondo de tarjetas y modales con sombra suave (`.shadow-sm`).
- **Text:** `#1e2a32` (texto principal). Para texto secundario usar la utilidad de Bootstrap `.text-body-secondary` (no `.text-secondary`, que baja de 4.5:1 sobre fondos grises o filas con hover).
- **Success / Ok:** `#2e7d32` / `#e8f5e9` - Alertas de confirmación y estados completados.
- **Error / Danger:** `#c62828` / `#ffebee` - Alertas de error y validación.
- **Info:** `#1f5f8b` / `#e7f1f8` - Avisos neutros (por ejemplo, "No había cambios para guardar").
- **Aviso:** `#8a5a00` / `#fff4d6` (5.4:1) - Notas fijas dentro de una vista, como "Este sector está dado de baja" (`.alerta--aviso` con `role="note"`). No usar `alert-*` de Bootstrap, que no siguen la paleta.

**Regla de contraste:** sobre fondos verdes el texto va en **blanco pleno**. La jerarquía se marca con tamaño o peso, no con opacidad: `.text-white-50` está prohibido.

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
- **Contenido junto al menú:** el espacio útil se achica 240 px. Las tablas anchas se muestran como tabla recién desde 1200 px (`d-xl-block`) y como tarjetas debajo de ese ancho (ver skill de frontend, sección 6).

### 4.7. Etiqueta de Tipo de Turno

- Parcial `app/Views/admin/tipos_turno/_etiqueta.php` (clase `.etiqueta-turno`): fondo con el color del turno y **siempre el nombre encima**, nunca un punto de color solo.
- **Paleta cerrada** (`TipoTurnoModel::PALETA`); el texto blanco u oscuro lo elige `TipoTurno::colorTexto()` por contraste:

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
| Cubierto | "Cubierto n/m" | "Cubierto: n de m enfermeros mínimos." | `bi-check-circle-fill` | `#2e7d32` / `#e8f5e9` | 4.56:1 |
| Faltan | "Faltan k · n/m" | "Faltan k enfermeros: n de m asignados." | `bi-exclamation-triangle-fill` | `#8a5a00` / `#fff4d6` | 5.41:1 |
| Sin cubrir | "Sin cubrir 0/m" | "Sin cubrir: 0 de m enfermeros mínimos." | `bi-x-circle-fill` | `#c62828` / `#ffebee` | 4.92:1 |

- Solo cuentan los enfermeros/as **activos**; uno dado de baja se muestra con el badge "De baja · no cuenta".
- **Prohibido bajar la opacidad** de chips, celdas o textos (con `opacity: .75` el contraste cae a 2.9–3.5:1).

### 4.9. Cuadrícula Semanal (Planificador)

- **Desde 1400 px** (`d-xxl-block`): tabla con filas = tipos de turno (`th scope="row"` con etiqueta y horario) y columnas = días (`th scope="col"`, "lunes" + dd/mm), con `caption` oculto (sector y semana). Debajo de 1400 px, un bloque por día.
- **Hoy:** fondo `--color-primario-suave`, texto "hoy", `aria-current="date"`; los links de esa columna van en `--color-primario-hover` (5.9:1).
- **Día pasado:** fondo `--color-fondo` y la palabra "pasado"; en celular se pliega con `<details>`. Un turno que ya terminó muestra un candado ("Terminado") y no se puede modificar.
- **Cada nombre** es un link a reasignar o eliminar, con texto oculto "reasignar o eliminar su turno X del día Y".
- **Botón de asignar:** grande (`btn-outline-secondary w-100`) donde falta gente; compacto ("+ Agregar", `btn-link`) donde ya está cubierto. Abre el **modal** `#modalAsignar` con el turno precargado y envía por AJAX (mismas validaciones de HU-13; los errores se muestran dentro del modal).
- **Quitar:** al lado de cada nombre, ícono `bi-x-circle` (con texto oculto "Quitar a … del turno …") que abre el modal de confirmación `#modalQuitar`.
- **Mejora progresiva:** sin JavaScript, "Asignar" es un link al formulario completo y cada nombre lleva a "Reasignar turno", donde también se puede eliminar.
- **Resumen de horas:** debajo de la cuadrícula, una tarjeta por enfermero/a activo con "N h de M h", barra de progreso y "Le quedan X h libres" o "Sin horas libres esta semana" (suma de todos los sectores).
- **Celular:** link "Ir a hoy" y navegación de semanas en un `btn-group`.

### 4.10. Selects de Personas

Ordenados por apellido. Cuando ya se sabe el turno y la fecha, cada opción muestra las horas de la semana ("32 h de 36 h esta semana") y las que no pueden tomar el turno van `disabled` con el motivo. Con más de 8 opciones, un buscador arriba (`data-filtrar-select`).

## 5. Foco y Accesibilidad

- **Foco visible:** contorno de 2 px en `--color-primario-oscuro` sobre superficies claras y en blanco sobre superficies verdes (barra y encabezados), con `outline-offset: 2px`. Está definido en `app.css`; **prohibido quitarlo**.
- **Contraste mínimo WCAG AA:** 4.5:1 para texto normal. Ver la tabla de la skill `.agents/skills/ux-ui-expert.md`, sección 6.
- **Tamaño táctil:** en celular, botones de al menos 44 px.

## 6. Tono

- Tratamiento de **usted** en toda la interfaz ("Ingrese", "Revise", "Contacte").
- Términos del hospital, no de la base de datos: "legajo", "dar de baja", "guardia", "pendiente de activación"; nunca "estado = BAJA" ni "registro eliminado".
- Fechas `dd/mm/aaaa` (helper `fecha_ar()`) y horas en formato 24 h.
