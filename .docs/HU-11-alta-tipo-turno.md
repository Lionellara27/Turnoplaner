# HU-11 | Alta de tipo de turno (franjas horarias)

**Épica:** ÉPICA D: ABMC DE TIPOS DE TURNOS (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 4  

---

## Historia de Usuario

**Como** administrador,  
**quiero** configurar los tipos de turnos y franjas horarias rotativas (ej. Mañana 06:00 a 14:00, Tarde 14:00 a 22:00, Noche 22:00 a 06:00, Guardia Especial 12 hs)  
**para** utilizarlos en la planificación.

---

## Criterios de Aceptación

* **Campos del formulario:** Nombre del turno (único), Hora de inicio, Hora de fin, Duración en horas (calculada automáticamente o validada) y Etiqueta de color para identificarlo en la cuadrícula.
* **Cálculo nocturno:** Soporta correctamente el cálculo de duración en franjas nocturnas que cruzan la medianoche (ej. de 22:00 a 06:00 = 8 horas).

