# HU-14 | Planificador semanal de cuadrículas

**Épica:** ÉPICA E: PLANIFICACIÓN, CUADRÍCULAS Y COBERTURAS (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 13  

---

## Historia de Usuario

**Como** administrador,  
**quiero** gestionar una cuadrícula matricial semanal por sector (filas: tipos de turno; columnas: días de la semana)  
**para** planificar y balancear la dotación de manera visual y ágil.

---

## Criterios de Aceptación

* **Filtros y navegación:** Filtros superiores por **Sector** y **Semana** (con botones Semana Anterior / Siguiente).
* **Gestión interactiva por celda:** Cada celda (Franja + Día) lista los enfermeros asignados y permite agregar o quitar personal (invocando las validaciones de la HU-13 mediante modal y peticiones AJAX).
* **Indicador de cobertura:** Indicador visual en cada celda de **Asignados vs. Dotación Mínima** (ej. 2 / 3 enfermeros).
* **Balanceo de carga:** Resumen inferior o lateral con el total de **horas semanales acumuladas** por cada enfermero frente a su máximo contratado (para facilitar el balanceo manual de cargas).

---

## Notas de implementación (05/11/2026)

* Ruta `admin/planificador?sector=ID&semana=AAAA-MM-DD` (cualquier día de la semana; se normaliza al lunes).
* Cada celda muestra "Cubierto n/m", "Faltan k · n/m" o "Sin cubrir 0/m" contra la dotación mínima del sector. Solo cuentan los enfermeros/as activos.
* **Agregar:** botón de la celda → modal con el turno precargado → POST AJAX a `admin/asignaciones/crear` (mismas validaciones de HU-13; responde JSON con los errores o el resultado).
* **Quitar:** ícono al lado de cada nombre → modal de confirmación → POST AJAX a `admin/asignaciones/{id}/eliminar` (baja lógica).
* **Resumen de horas:** debajo de la cuadrícula, horas de la semana de cada enfermero/a (todos los sectores) contra su máximo contratado.
* Desde 1400 px se ve como tabla; debajo, un bloque por día con los días pasados plegados y un link "Ir a hoy".
