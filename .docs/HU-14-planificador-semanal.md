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

