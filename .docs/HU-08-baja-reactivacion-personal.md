# HU-08 | Baja lógica y reactivación de enfermero/a

**Épica:** ÉPICA B: ABMC DE USUARIOS Y PERSONAL (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 3  

---

## Historia de Usuario

**Como** administrador,  
**quiero** dar de baja de forma lógica a un enfermero/a sin eliminar su registro físico  
**para** preservar el historial de turnos pasados e impedir nuevas asignaciones.

---

## Criterios de Aceptación

* **Confirmación de baja:** Solicita confirmación mediante ventana modal antes de aplicar la baja.
* **Baja lógica:** El registro **no** se borra con DELETE en MySQL; su estado pasa a **"Baja"** (`activo = 0` / `estado = 'BAJA'`).
* **Restricción operativa:** Un enfermero dado de baja no puede iniciar sesión ni aparece disponible en los selectores del Planificador para turnos futuros.
* **Reactivación:** Permite reactivar al usuario desde el listado si vuelve a prestar servicio.

