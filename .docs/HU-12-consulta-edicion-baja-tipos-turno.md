# HU-12 | Consulta, edición y baja lógica de tipos de turno

**Épica:** ÉPICA D: ABMC DE TIPOS DE TURNOS (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** [ A definir / Pendiente ]  

---

## Historia de Usuario

**Como** administrador,  
**quiero** listar, editar y dar de baja lógica los tipos de turno configurados  
**para** mantener actualizado el catálogo de franjas horarias disponibles.

---

## Criterios de Aceptación

* **Listado:** Listado con Nombre, Rango horario (Inicio - Fin), Cantidad de horas, Color y Estado.
* **Baja lógica e integridad:** El sistema valida que no existan asignaciones futuras activas con ese tipo de turno antes de permitir desactivarlo.

---

## Notas de implementación (05/11/2026)

* Se implementó junto con HU-11 como un único ABMC de tipos de turno (mismo listado, mismo formulario de alta y edición).
* La validación "no permitir la baja si hay asignaciones futuras" depende de la tabla `asignaciones` (HU-13). Está programada en `App\Models\Concerns\BajaLogica::tieneAsignacionesFuturas()` y quedó activa al implementarse HU-13.
* La duración no se escribe: se calcula al guardar (también en vivo en el formulario). Inicio igual a fin no se permite.
