# Estándares de Frontend con Bootstrap 5 y CodeIgniter 4

Esta guía técnica define las reglas obligatorias de maquetado, interfaz de usuario y comportamiento dinámico para **TurnoPlaner**, respetando estrictamente el sistema de diseño definido en [`.docs/DESIGN.md`](../../.docs/DESIGN.md).

> **Antes de escribir cualquier vista, estilo o script de interfaz:** leer `.docs/DESIGN.md` (estética, fuente de verdad) y `.agents/skills/ux-ui-expert.md` (flujo, usabilidad y accesibilidad). Si algo de esta guía contradice a `DESIGN.md`, gana `DESIGN.md`.

---

## 1. Reglas Generales de Maquetado

1. **Framework Único:** Toda la interfaz se construye con **Bootstrap 5.3** (grilla, utilidades, componentes, modales y tablas) y **Bootstrap Icons**. Prohibido mezclar otros frameworks CSS.
2. **Contexto de Vistas en CodeIgniter 4:** Todo diseño es una vista que extiende el layout base:

```php
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<!-- Código HTML aquí -->
<?= $this->endSection() ?>
```

3. **Navegación:** la barra superior y el menú lateral los arma el layout (`DESIGN.md` 4.6). Una HU nueva agrega su ítem al arreglo `$menu` de `layouts/main.php`; las vistas no crean menús propios.
4. **Etiquetas Prohibidas en Vistas:** Nunca incluir `<!DOCTYPE html>`, `<html>`, `<head>` o `<body>`. Pertenecen solo a `app/Views/layouts/main.php`.
5. **Sin estilos inline:** nada de `style="..."` (única excepción documentada: las variables `--color-turno` / `--color-turno-texto` de la etiqueta de tipo de turno, `DESIGN.md` §1 y 4.7). Los colores se aplican con las clases del proyecto (`.tp-card-header`, `.btn-tp`, `.alerta--*`, `.dato-lectura`) de `public/css/app.css`. Si hace falta un estilo nuevo, se agrega a `app.css` con una variable de la paleta.
6. **Partes reutilizables:** lo que se repite en dos pantallas va a `app/Views/partials/` (por ejemplo `alertas.php`, `campos_nueva_password.php`) o a un `_form.php` compartido por alta y edición.
7. **JavaScript:** `public/js/app.js` es vanilla (sin jQuery). jQuery **no** está cargado en el layout; si una HU lo necesita (por ejemplo AJAX en el planificador), se agrega en `layouts/main.php` desde el CDN con versión fija y atributo `integrity`.
8. **CDN con integridad:** todo `<link>`/`<script>` externo lleva versión exacta, `integrity="sha384-..."` y `crossorigin="anonymous"`.

---

## 2. Paleta de Colores y Variables de Diseño

Usar las variables de `public/css/app.css`. Los valores y usos oficiales están en `DESIGN.md` sección 2. Resumen operativo:

| Necesito... | Uso |
| :--- | :--- |
| Fondo verde con texto blanco (encabezado, botón principal, barra) | `--color-primario-oscuro` `#307f6d` vía `.tp-card-header` / `.btn-tp` |
| Un acento verde (borde, ícono, detalle) | `--color-primario` `#43ab92` |
| Texto secundario | `.text-body-secondary` (no `.text-secondary`) |
| Texto sobre verde | blanco pleno (`.text-white`, `.subtitulo`); **nunca** `.text-white-50` |
| Link | color por defecto (ya configurado como `--color-primario-oscuro`), sobre fondo blanco |

---

## 3. Identidad Institucional y Manejo del Logo

1. **Invocación del Logo:** siempre con el helper de CodeIgniter 4: `<?= base_url('img/logo-64.png') ?>`.
2. **Clase Obligatoria:** `.barra__logo` (34px, redondeado):
   ```html
   <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
   ```
3. **Login:** `img/logo.png` de 96 px con `.login__logo`.

---

## 4. Componentes Clave y Blueprints

### 4.1. Encabezado de Tarjeta Institucional (*Card Header Blueprint*)

```html
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
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
    <div class="card-body p-4 p-md-5 bg-white">
        <!-- Contenido principal -->
    </div>
</div>
```

### 4.2. Botones de Acción Estándar

- **Principal (una sola por pantalla):** `<button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">Guardar</button>`
- **Secundario (Cancelar / Volver):** `<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">Cancelar</a>`
- **Peligro (confirmación en modal):** `<button type="submit" class="btn btn-danger">Dar de baja</button>`
- **Sobre el encabezado verde:** `class="btn btn-light fw-semibold shadow-sm"`.
- **Orden en celular:** con `flex-column flex-sm-row`, la acción principal arriba (`order-1 order-sm-2`) y Cancelar abajo (`order-2 order-sm-1`).

---

## 5. Formularios y Manejo de Alertas

### 5.1. Formulario estándar

```php
<form action="<?= base_url('admin/usuarios/crear') ?>" method="post" class="needs-validation" novalidate data-enviando="Registrando...">
    <?= csrf_field() ?>
    <p class="small text-body-secondary">Los campos con * son obligatorios.</p>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="form-floating">
                <input type="text" name="nombre" id="nombre" class="form-control<?= clase_invalido('nombre') ?>" placeholder="Nombre"
                       value="<?= esc(old('nombre', $usuario->nombre ?? '', false)) ?>" minlength="2" maxlength="100" required
                       data-mensaje="Ingrese el nombre (al menos 2 letras).">
                <label for="nombre">Nombre *</label>
                <?= feedback_campo('nombre') ?>
            </div>
        </div>
    </div>

    <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
        <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">Cancelar</a>
        <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">Registrar enfermero/a</button>
    </div>
</form>
```

Reglas del patrón:

1. `class="needs-validation" novalidate`: la validación la hace `app.js` y muestra el texto de `data-mensaje` junto al campo.
2. `data-enviando`: al enviar, deshabilita el botón y muestra un spinner (evita doble envío y el 403 por token CSRF regenerado). **También en los formularios de los modales.**
3. `old('campo', $defecto, false)` dentro de `esc()`: se escapa una sola vez (ver skill de seguridad, sección 6).
4. `clase_invalido()` / `feedback_campo()` (helper `app/Helpers/form_ui_helper.php`, autocargado): error del servidor junto al campo.
5. Tipos correctos: `type="email"`, `type="tel"` + `inputmode="tel"`, `inputmode="numeric"` en DNI, `type="date"`/`type="time"` en fechas y horas.
6. Ayuda de formato con `form-text` y `aria-describedby` en todo campo con formato especial.
7. **Patrones HTML:** los navegadores compilan `pattern` con el flag `v` de las expresiones regulares. Dentro de `[...]` hay que escapar `( ) [ ] { } / - \ |`. Ejemplo válido: `pattern="[0-9+\(\)\s\-]{6,30}"`. Sin el escape, el navegador ignora el patrón en silencio.
8. **Contraseña con botón de mostrar:**

```php
<div class="input-group has-validation">
    <div class="form-floating<?= clase_invalido('password') ?>">
        <input type="password" class="form-control<?= clase_invalido('password') ?>" id="password" name="password"
               placeholder="Contraseña" autocomplete="current-password" required data-mensaje="Ingrese su contraseña.">
        <label for="password">Contraseña *</label>
    </div>
    <button class="btn btn-outline-secondary btn-ojo" type="button" data-mostrar-clave="#password" aria-label="Mostrar contraseña">
        <i class="bi bi-eye" aria-hidden="true"></i>
    </button>
    <?= feedback_campo('password') ?>
</div>
```

### 5.2. Alertas (éxito, error, info, errores de validación)

**Las vistas NO escriben bloques de alertas.** El layout incluye una vez `app/Views/partials/alertas.php`, que muestra los flashdata `success`, `info`, `error`, `errors` (resumen con enlaces a cada campo) y `credenciales`. Desde el controlador:

```php
return redirect()->to(base_url('admin/usuarios'))->with('success', 'Legajo actualizado.');
return redirect()->back()->with('info', 'No había cambios para guardar.');
return $this->volverConErrores($this->validator->getErrors()); // vuelve con lo cargado, sin contraseñas
```

En una pantalla angosta (login) el controlador pasa `'alertasEnVista' => true` y la vista hace `<?= $this->include('partials/alertas') ?>` dentro de su tarjeta.

### 5.3. Filtros y búsqueda

`form-label` arriba del campo (sin floating), método GET, botón "Filtrar" `btn-outline-secondary` y **sin autoenvío** al cambiar un select.

---

## 6. Tablas de Datos Responsive

1. **Envoltorio Obligatorio:** `<div class="table-responsive">`.
2. **Clases de Estilo:** `.table .table-hover .align-middle`; `<caption class="visually-hidden">` describiendo la tabla.
3. **Acciones Compactas:** `.btn-sm` con ícono y texto oculto para lectores de pantalla.
4. **Badges de Estado:** los de `DESIGN.md` 4.4 (`*-subtle` + ícono + texto).
5. **Modales fuera de la tabla** (un `<div class="modal">` dentro de `<td>` es HTML válido pero dificulta el foco; se ubican después de la tarjeta).
6. **Estado vacío:** si no hay filas, en lugar de la tabla un bloque con ícono, mensaje y la acción para salir del vacío.
7. **Pantallas chicas y menú lateral:** una tabla de más de 3 columnas obliga a deslizar de costado y esconde los botones de acción; con el menú lateral abierto (desde 992 px) el contenido pierde 240 px. Patrón del proyecto (ver `app/Views/admin/users/index.php`): debajo de 1200 px, `<ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 d-xl-none">` con una tarjeta por fila (datos en `dl.row` y botones con texto); desde 1200 px, la tabla en `<div class="table-responsive d-none d-xl-block">`, agrupando datos relacionados en una celda (DNI y matrícula, nombre y rol) para que entre sin deslizar. El badge y las acciones salen de parciales (`_estado.php`, `_acciones.php`) para no duplicar código.
8. **Verificar a 375 px:** cargar la pantalla dentro de un `<iframe>` de 375 px de ancho (los navegadores sin ventana tienen un ancho mínimo cercano a 500 px y recortan la captura) y comprobar que `document.documentElement.scrollWidth` sea 375.

```php
<div class="table-responsive">
    <table class="table table-hover align-middle border mb-0">
        <caption class="visually-hidden">Listado del personal de enfermería</caption>
        <thead class="table-light">
            <tr><th scope="col">Nombre y apellido</th><th scope="col" class="text-center">Estado</th><th scope="col" class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td class="fw-semibold"><?= esc($u->getNombreCompleto()) ?></td>
                <td class="text-center">
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Activo
                    </span>
                </td>
                <td class="text-end text-nowrap">
                    <a href="<?= base_url('admin/usuarios/' . $u->id . '/editar') ?>" class="btn btn-outline-secondary btn-sm" title="Editar legajo">
                        <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">Editar a <?= esc($u->getNombreCompleto()) ?></span>
                    </a>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalBaja<?= (int) $u->id ?>">
                        <i class="bi bi-person-x" aria-hidden="true"></i><span class="visually-hidden">Dar de baja a <?= esc($u->getNombreCompleto()) ?></span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<nav class="mt-3 d-flex justify-content-center" aria-label="Paginación">
    <?= $pager->links('default', 'bootstrap_tp') ?>
</nav>
```

---

## 7. Interacciones Dinámicas

1. **Modales de Bootstrap 5:** para toda confirmación destructiva (baja, restablecer clave). El modal contiene un `<form method="post">` con `csrf_field()` y `data-enviando`.
2. **AJAX:** el token CSRF se publica en el layout con `<meta>` y se lee desde JS; ver `auth-security-filters.md` sección 5.3. Un archivo `.js` no ejecuta PHP.
3. **"Atrás" del navegador:** `app.js` recarga la página si viene del caché (`pageshow` con `persisted`), para no dejar botones deshabilitados.
4. **Portapapeles:** `navigator.clipboard` solo funciona en HTTPS o `localhost`. En la intranet por `http://` seleccionar el texto y pedir Ctrl+C (ya implementado en `[data-copiar]`).

---

## 8. Verificación antes de dar por terminada una vista

- `C:\xampp\php\php.exe -l` sobre cada vista modificada.
- Validar el HTML en https://validator.w3.org/ (pegar el código fuente de la página con sesión iniciada).
- Checklist de `ux-ui-expert.md` sección 8.
- `bash tests/e2e/turnoplaner_e2e.sh` sin fallas.
