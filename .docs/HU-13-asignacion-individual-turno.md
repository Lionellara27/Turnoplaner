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

---

## Notas de implementación (05/11/2026)

* Cada asignación guarda `inicio` y `fin` completos (fecha y hora). Un turno de 22:00 a 06:00 termina al día siguiente, y si después se edita el horario del tipo de turno, los turnos ya asignados no cambian.
* **Superposición:** se compara contra todos los turnos del enfermero/a por fecha y hora real (la noche del lunes contra la mañana del martes). Terminar 06:00 y empezar 06:00 no es superposición.
* **Carga horaria:** semana de lunes a domingo; el turno cuenta en la semana del día en que empieza. Se rechaza explicando la cuenta (horas ya asignadas + turno nuevo contra el máximo contratado).
* Solo se asigna, reasigna o elimina de hoy en adelante. Eliminar es baja lógica (`deleted_at`).
* También incorpora el control de HU-12: un tipo de turno o un sector con asignaciones futuras no se puede dar de baja.
* Se asigna desde el botón "Asignar" de cada celda de la cuadrícula (HU-14), que precarga sector, turno y fecha.
