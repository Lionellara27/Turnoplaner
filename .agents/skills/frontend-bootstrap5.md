# Estándares de Frontend con Bootstrap 5, CodeIgniter 4 y jQuery

Esta guía técnica define las reglas obligatorias de maquetado, interfaz de usuario y comportamiento dinámico para **TurnoPlaner**, respetando estrictamente el sistema de diseño definido en `DESIGN.md`.

---

## 1. Reglas Generales de Maquetado

1. **Framework Único:** Toda la interfaz debe construirse exclusivamente con **Bootstrap 5** (grilla, utilidades de espaciado, componentes, modales y tablas). Queda prohibido mezclar otros frameworks CSS.
2. **Contexto de Vistas en CodeIgniter 4:** Todo diseño debe ser una vista modular que extienda el layout base:

```php
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="container-fluid py-4">
    <!-- Código HTML aquí -->
</div>
<?= $this->endSection() ?>
```

3. **Etiquetas Prohibidas en Vistas:** Nunca incluir `<!DOCTYPE html>`, `<html>`, `<head>` o `<body>` dentro de las vistas de contenido. Esas etiquetas pertenecen exclusivamente a `app/Views/layouts/main.php`.

---

## 2. Paleta de Colores y Variables de Diseño

Los estilos deben utilizar las variables CSS institucionales definidas en `public/css/app.css`:

| Token | Variable CSS | Valor Hex | Uso Obligatorio |
| :--- | :--- | :--- | :--- |
| **Primary** | `--color-primario` | `#43ab92` | Encabezados de tarjetas, botones principales de acción (`submit`), enlaces destacados. |
| **Primary Dark** | `--color-primario-oscuro` | `#307f6d` | Estados `:hover` y bordes activos de elementos primarios. |
| **Background** | `--color-fondo` | `#f3f6f8` | Fondo general de la aplicación. |
| **Surface** | `--color-blanco` | `#ffffff` | Fondo de tarjetas (`.card`), modales y paneles con `.shadow-sm`. |
| **Text Principal** | `--color-texto` | `#1e2a32` | Títulos y texto de alta jerarquía. |
| **Text Suave** | `--color-texto-suave`| `#5c6b75` | Descripciones secundarias, placeholders y labels informativos. |
| **Success / Ok** | `--color-ok` | `#2e7d32` / `#e8f5e9` | Alertas de confirmación y badges de estado completado o activo. |
| **Danger / Error** | `--color-error` | `#c62828` / `#ffebee` | Alertas de error, validaciones fallidas y confirmación de bajas. |

---

## 3. Identidad Institucional y Manejo del Logo

1. **Invocación del Logo:** Siempre referenciar el recurso utilizando el helper de CodeIgniter 4:
   ```php
   <?= base_url('img/logo-64.png') ?>
   ```
2. **Clase Obligatoria:** Emplear la clase `.barra__logo` para preservar las proporciones (34px, bordes redondeados):
   ```html
   <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
   ```

---

## 4. Componentes Clave y Blueprints

### 4.1. Encabezado de Tarjeta Institucional (*Card Header Blueprint*)
Todos los formularios principales, paneles y listados de gestión deben implementar este encabezado:

```html
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header text-white py-3 px-4" style="background-color: var(--color-primario);">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
            </div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">[Título de la Pantalla]</h1>
                <p class="mb-0 small text-white-50">TurnoPlaner - Gestión de Turnos Hospitalarios</p>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        <!-- Contenido principal -->
    </div>
</div>
```

### 4.2. Botones de Acción Estándar

- **Botón Principal (Submit / Guardar):**
```html
<button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm" style="background-color: var(--color-primario); border-color: var(--color-primario);">
    Guardar Registro
</button>
```

- **Botón Secundario (Cancelar / Volver):**
```html
<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">
    Cancelar
</a>
```

- **Botón Peligro (Baja / Desactivar en Modales):**
```html
<button type="button" class="btn btn-danger px-4 py-2 fw-semibold">
    Confirmar Baja
</button>
```

---

## 5. Formularios y Manejo de Alertas

1. **Diseño con Grillas y Floating Labels:** Utilizar `row g-3`, `col-md-6` (o proporciones acordes) combinados con `.form-floating`:

```html
<form action="<?= base_url('admin/usuarios/crear') ?>" method="post" class="needs-validation">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="form-floating">
                <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Nombre" value="<?= old('nombre') ?>" required>
                <label for="nombre">Nombre</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-floating">
                <input type="text" name="apellido" id="apellido" class="form-control" placeholder="Apellido" value="<?= old('apellido') ?>" required>
                <label for="apellido">Apellido</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary px-4 py-2">Cancelar</a>
        <button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm" style="background-color: var(--color-primario);">
            Registrar Enfermero/a
        </button>
    </div>
</form>
```

2. **Bloque de Mensajes de Error (Flashdata CI4):**
```php
<?php if (session()->getFlashdata('errors')): ?>
    <div class="alerta alerta--error mb-4" role="alert">
        <strong>Por favor, revise los siguientes errores:</strong>
        <ul class="mb-0 mt-2 ps-3 small">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif; ?>
```

3. **Bloque de Mensajes de Éxito (Flashdata CI4):**
```php
<?php if (session()->getFlashdata('success')): ?>
    <div class="alerta alerta--ok mb-4" role="alert">
        <?= esc(session()->getFlashdata('success')) ?>
    </div>
<?php endif; ?>
```

---

## 6. Tablas de Datos Responsive

1. **Envoltorio Obligatorio:** Envolver siempre las tablas en `<div class="table-responsive">`.
2. **Clases de Estilo:** Usar `.table .table-hover .align-middle`.
3. **Acciones Compactas:** Botones pequeños (`.btn-sm`) para acciones de fila.
4. **Badges de Estado:**
   - Activo / Asignado: `<span class="badge bg-success">Activo</span>`
   - Pendiente: `<span class="badge bg-warning text-dark">Pendiente</span>`
   - Baja / Ausente: `<span class="badge bg-danger">Baja</span>`

```html
<div class="table-responsive shadow-sm rounded-3">
    <table class="table table-hover align-middle mb-0 bg-white">
        <thead class="table-light">
            <tr>
                <th>Profesional</th>
                <th>DNI</th>
                <th>Matrícula</th>
                <th>Carga Semanal</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="fw-medium"><?= esc($u->getNombreCompleto()) ?></td>
                    <td><?= esc($u->dni) ?></td>
                    <td><?= esc($u->matricula) ?></td>
                    <td><?= esc($u->carga_horaria_max) ?> hs</td>
                    <td>
                        <span class="badge <?= $u->estado === 'ACTIVO' ? 'bg-success' : 'bg-danger' ?>">
                            <?= esc($u->estado) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="<?= base_url('admin/usuarios/editar/' . $u->id) ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-baja" data-id="<?= $u->id ?>" data-nombre="<?= esc($u->getNombreCompleto()) ?>">Baja</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

---

## 7. Interacciones Dinámicas con AJAX y jQuery

1. **Inclusión de CSRF en Peticiones Asincrónicas:**  
   Garantizar que todas las llamadas `$.ajax()`, `$.post()` o `$.get()` envíen el token CSRF para evitar errores `403 Forbidden`:

```javascript
$(document).ready(function() {
    // Configuración global de cabeceras AJAX
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Manejo de Modal de Confirmación de Baja Lógica
    $('.btn-baja').on('click', function() {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        
        $('#modalBajaNombre').text(nombre);
        $('#formBaja').attr('action', `${baseUrl}admin/usuarios/baja/${id}`);
        $('#modalConfirmarBaja').modal('show');
    });
});
```

2. **Ventanas Modales de Bootstrap 5:**  
   Siempre utilizar los modales nativos de Bootstrap 5 (`<div class="modal fade" ...>`) para confirmaciones destructivas o formularios emergentes del Planificador de Turnos.

