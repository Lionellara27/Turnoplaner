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

## 2. Identidad Visual y Colores (TurnoPlaner)

Usar los colores corporativos definidos en las variables CSS del proyecto (`css/app.css`):

- **Primary (`--color-primario`):** `#43ab92` (Verde Esmeralda Clínico) - Encabezados de tarjetas, botones principales de acción (`submit`), enlaces destacados.
- **Primary Dark (`--color-primario-oscuro`):** `#307f6d` - Estados `:hover` de botones.
- **Background (`--color-fondo`):** `#f3f6f8` - Fondo general de la app.
- **Surface:** `#ffffff` - Fondo de tarjetas y modales con sombra suave (`.shadow-sm`).
- **Text:** `#1e2a32` (Texto principal) y `#5c6b75` (Texto secundario / descripciones).
- **Success / Ok:** `#2e7d32` / `#e8f5e9` - Alertas de confirmación y estados completados.
- **Error / Danger:** `#c62828` / `#ffebee` - Alertas de error y validación.

## 3. Manejo de Marcas e Imágenes (Logo)

- **Ruta del Logo:** Siempre invocar el logo usando PHP/CodeIgniter:

  `<?= base_url('img/logo-64.png') ?>`

- **Clase CSS del Logo:** Usar siempre la clase `.barra__logo` para mantener la proporción de 34px y bordes redondeados:

```html
<img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
```

## 4. Componentes Clave y Blueprints

### 4.1. Encabezados de Tarjeta (Card Headers)

Los encabezados de formularios o paneles principales deben llevar el color primario y el logo institucional:

```html
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
```

### 4.2. Botones de Acción

- **Botón Principal (Submit/Guardar):**

```html
<button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm" style="background-color: var(--color-primario); border-color: var(--color-primario);">
 [Texto de Acción]
</button>
```

- **Botón Secundario (Cancelar/Volver):**

```html
<a href="<?= base_url('ruta') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">
 Cancelar
</a>
```

### 4.3. Formularios y Tablas

- **Formularios:** Utilizar el sistema de grillas de Bootstrap 5 (`row g-3`, `col-md-6`) combinando con la clase `.form-floating` para todos los inputs y selects.
- **Mensajes de Error:** Incluir siempre el bloque de captura de errores de CodeIgniter al inicio del formulario:

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

- **Mensajes de Éxito:** Incluir también el bloque de confirmación de CodeIgniter al inicio de la vista, junto al de errores:

```php
<?php if (session()->getFlashdata('success')): ?>
 <div class="alerta alerta--ok mb-4" role="alert">
 <?= esc(session()->getFlashdata('success')) ?>
 </div>
<?php endif; ?>
```

- **Tablas de Datos:** Envolver siempre en `<div class="table-responsive">` usando las clases `.table .table-hover .align-middle`. Las acciones por fila deben usar botones pequeños (`.btn-sm`).
