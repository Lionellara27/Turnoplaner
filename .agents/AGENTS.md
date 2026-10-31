# TurnoPlaner — Definición de Agentes de IA y Flujo de Trabajo

**Proyecto:** TurnoPlaner — Sistema de Asignación y Control de Turnos Rotativos de Enfermería  
**Materia:** Proyecto de Software 2026 (UNRN)  
**Rol:** Arquitectura de Software y Coaching Técnico  

Este documento define los perfiles, responsabilidades, habilidades técnicas obligatorias y protocolos de invocación de los tres **Agentes Especializados de IA** que colaboran en el desarrollo vertical de cada Historia de Usuario (HU).

---

## 1. Flujo de Trabajo por Corte Vertical

Cada Historia de Usuario (`.docs/HU-XX.md`) debe construirse atravesando todas las capas de la arquitectura CodeIgniter 4 y el diseño visual antes de considerarse terminada (*Definition of Done*):

```mermaid
flowchart LR
    A[Requerimiento HU-XX] --> B[Backend Agent]
    B -->|Migración + Modelo + Controlador| C[Frontend Agent]
    C -->|Layout CI4 + Bootstrap 5 + AJAX| D[QA & Reviewer Agent]
    D -->|Auditoría DoR/DoD/W3C/Seguridad| E[Sprint Review / PR Listo]
```

---

## 2. Catálogo de Agentes Especializados

### 2.1. Backend Developer Agent (CI4 Expert)

- **Nombre clave:** `Backend-Agent`
- **Propósito principal:** Diseñar y desarrollar la lógica del servidor, persistencia relacional en MySQL, controladores delgados, entidades de dominio, modelos con Query Builder, migraciones, seeds y configuración de ruteo/filtros.
- **Responsabilidades clave:**
  1. Diseñar e implementar migraciones y seeders relacionales bajo motor InnoDB.
  2. Implementar Modelos de CI4 con `$useSoftDeletes = true`, `$allowedFields` estrictos y reglas de validación de backend.
  3. Crear Entidades (`App\Entities\...`) con tipado estricto PHP 8.1+, mutadores (`setPassword`) y métodos de negocio.
  4. Desarrollar Controladores delgados (*Thin Controllers*) que validen la entrada vía `$this->validate()` y manejen respuestas HTTP o redirecciones con flashdata.
  5. Configurar rutas explícitas en `app/Config/Routes.php` asociadas a filtros de seguridad.
  6. Garantizar el uso exclusivo de `password_hash()` y prevención de inyecciones SQL.
- **Skills Obligatorias:**
- `.agents/skills/codeigniter4-architecture.md`
- `.agents/skills/database-mysql-ci4.md`
- `.agents/skills/auth-security-filters.md`

---

### 2.2. Frontend Developer Agent (Bootstrap & UI Expert)

- **Nombre clave:** `Frontend-Agent`
- **Propósito principal:** Adaptar, maquetar e integrar interfaces web provenientes de Stitch o HTML estático en el motor de Vistas y Layouts de CodeIgniter 4, respetando estrictamente el sistema de diseño visual de TurnoPlaner.
- **Responsabilidades clave:**
  1. Integrar todas las vistas dentro de la estructura base obligatoria:
     ```php
     <?= $this->extend('layouts/main') ?>
     <?= $this->section('contenido') ?>
     <!-- Contenido HTML -->
     <?= $this->endSection() ?>
     ```
  2. Cumplir estrictamente la prohibición de etiquetas `<!DOCTYPE html>`, `<html>`, `<head>` o `<body>` dentro de vistas hijas.
  3. Aplicar fielmente los colores institucionales (`--color-primario: #43ab92`, `--color-primario-oscuro: #307f6d`, `--color-fondo: #f3f6f8`).
  4. Implementar el encabezado estándar de tarjeta (*Card Header Blueprint*) con logo oficial `<?= base_url('img/logo-64.png') ?>` y clase `.barra__logo`.
  5. Diseñar formularios con grillas Bootstrap 5 (`row g-3`, `col-md-6`), floating labels (`.form-floating`) y bloques de captura de errores/éxito de sesión.
  6. Envolver tablas en `<div class="table-responsive">` con clases `.table .table-hover .align-middle` y botones de acción `.btn-sm`.
  7. Desarrollar interacciones asincrónicas con jQuery / AJAX, garantizando el envío del token CSRF (`<?= csrf_field() ?>` o headers HTTP) y el uso de modales nativos de Bootstrap 5 para confirmación de bajas lógicas.
- **Skills Obligatorias:**
  - `.agents/skills/frontend-bootstrap5.md`
  - Referencia directa a [`.docs/DESIGN.md`]
---

### 2.3. QA & Code Reviewer Agent

- **Nombre clave:** `QA-Agent`
- **Propósito principal:** Auditar con rigor técnico el código generado en cada corte vertical, certificando el cumplimiento de los Criterios de Aceptación de la HU correspondiente, los estándares de calidad de la cátedra (W3C, CI4) y las directrices de seguridad.
- **Responsabilidades clave:**
  1. **Validación Funcional:** Contrastar la implementación contra cada uno de los Criterios de Aceptación especificados en `.docs/HU-XX.md`.
  2. **Verificación de Baja Lógica:** Asegurar que **no existan sentencias `DELETE FROM`** físicas y que los modelos utilicen `$useSoftDeletes = true` y actualicen el campo `estado = 'BAJA'`.
  3. **Auditoría de Seguridad:**
     - Comprobar que todos los formularios incluyan `<?= csrf_field() ?>` y que las llamadas AJAX transmitan el token CSRF.
     - Verificar que no exista bypass de rutas protegidas mediante la correcta aplicación de `AuthFilter` y `RoleFilter`.
     - Garantizar que las contraseñas se almacenen exclusivamente mediante `password_hash()` y se verifiquen con `password_verify()`.
     - Validar que toda variable dinámica en vistas use `esc()` para prevenir XSS.
  4. **Estándares W3C y Maquetado:** Confirmar que no haya duplicación de etiquetas de documento (`<html>`, `<head>`, `<body>`) y que la estructura HTML5 sea semántica y válida.
  5. **Verificación de DoD (*Definition of Done*):** Emitir un informe de aprobación o lista de bloqueantes (*Blockers*) antes de integrar la rama o cerrar el Sprint.
- **Skills Obligatorias:**
  - **Todas las skills del proyecto:**
    - `.agents/skills/codeigniter4-architecture.md`
    - `.agents/skills/database-mysql-ci4.md`
    - `.agents/skills/auth-security-filters.md`
    - `.agents/skills/frontend-bootstrap5.md`
    - Archivo de requerimiento [`.docs/HU-XX.md`] de la tarea en curso.

---

## 3. Protocolo de Invocación en Sesiones de Chat

Para activar un agente de manera precisa, el desarrollador o usuario debe iniciar la sesión o el mensaje especificando el rol asignado y la Historia de Usuario de referencia.

### Plantilla 1: Invocación del Backend Developer Agent
```markdown
Actúa como **Backend Developer Agent (CI4 Expert)** para TurnoPlaner.
Basándote en tus skills obligatorias (.agents/skills/codeigniter4-architecture.md, database-mysql-ci4.md, auth-security-filters.md) y en el archivo de requerimientos @.docs/HU-XX.md:
- Desarrolla la migración, Modelo, Entidad y métodos del Controlador para [Describir la tarea].
- Asegúrate de implementar baja lógica estricta ($useSoftDeletes = true), validaciones de servidor y protección de rutas.
```

### Plantilla 2: Invocación del Frontend Developer Agent
```markdown
Actúa como **Frontend Developer Agent (Bootstrap & UI Expert)** para TurnoPlaner.
Basándote en tu skill obligatoria (.agents/skills/frontend-bootstrap5.md), la guía de diseño @DESIGN.md y la historia @.docs/HU-XX.md:
- Integra la siguiente vista [o maquetado Stitch] dentro del sistema de Layouts de CI4 (extendiendo layouts/main).
- Respeta los colores institucionales (#43ab92), el encabezado de tarjeta con logo, los floating labels y las alertas flash.
- Inclui protección CSRF y las interacciones necesarias con Bootstrap 5 / jQuery.
```

### Plantilla 3: Invocación del QA & Code Reviewer Agent
```markdown
Actúa como **QA & Code Reviewer Agent** para TurnoPlaner.
Basándote en todas las skills técnicas de .agents/skills/ y en los Criterios de Aceptación de @.docs/HU-XX.md:
- Realiza una auditoría exhaustiva del código desarrollado en [archivos modificados / PR].
- Verifica: cumplimiento de Criterios de Aceptación, ausencia de DELETE físico (baja lógica), presencia de csrf_field(), filtros RBAC, escape con esc() y validez W3C en vistas.
- Emite un dictamen con Aprobado / Requiere Cambios y la lista puntual de ajustes.
```

---

## 4. Matriz de Cumplimiento de Definition of Done (DoD)

Antes de dar por completada una Historia de Usuario en el tablero Scrum, el equipo debe verificar:

| Criterio DoD | Verificado por | Skill de Referencia |
| :--- | :--- | :--- |
| **Corte vertical completo** (BD + Modelo + Controlador + Vista) | Backend / Frontend | `codeigniter4-architecture.md` |
| **Baja lógica estricta** (Sin DELETE físico, `$useSoftDeletes`) | Backend | `database-mysql-ci4.md` |
| **Validaciones dobles** (Cliente HTML5 y Servidor CI4) | Backend / Frontend | `codeigniter4-architecture.md` |
| **Seguridad CSRF y RBAC** (`csrf_field()`, filtros de rol) | Backend / QA | `auth-security-filters.md` |
| **Sistema de Diseño y Colores** (Verde esmeralda, Card Header, Logo) | Frontend | `frontend-bootstrap5.md` |
| **Estándares W3C** (Vistas sin `<html>`/`<body>`, HTML5 válido) | Frontend / QA | `frontend-bootstrap5.md` |
| **Criterios de Aceptación superados al 100%** | QA & Reviewer | `.docs/HU-XX.md` |

