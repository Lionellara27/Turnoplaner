# HU-13 | Asignación individual de turno con control de carga horaria

**Épica:** ÉPICA E: PLANIFICACIÓN, CUADRÍCULAS Y COBERTURAS (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 13  

---

## Historia de Usuario

**Como** administrador,  
**quiero** asignar un turno específico (sector, fecha y franja) a un enfermero/a validando su disponibilidad  
**para** cubrir la demanda sin generar superposiciones ni excesos de carga horaria.

---

## Criterios de Aceptación

* **Selección de campos:** Selección de: Enfermero/a (solo activos), Sector (solo activos), Tipo de Turno (solo activos) y Fecha.
* **Reglas de validación del negocio:**
  1. Rechaza la asignación si el enfermero ya tiene otro turno asignado en la misma fecha y franja horaria superpuesta.
  2. Suma las horas ya asignadas al enfermero en esa semana más la duración del nuevo turno; si supera su **Carga horaria máxima contratada**, emite alerta/rechazo explicando el motivo.
* **Gestión de turnos existentes:** Permite eliminar o reasignar un turno futuro ya cargado.

