# HU-16 | Carga y confirmación de coberturas de reemplazo

**Épica:** ÉPICA E: PLANIFICACIÓN, CUADRÍCULAS Y COBERTURAS (Administrador)  
**Prioridad:** S (Should - Obligatorio Etapa 2 / Final)  
**Puntos de Historia:** 13  

---

## Historia de Usuario

**Como** administrador,  
**quiero** asignar y confirmar un enfermero/a de reemplazo ante una inasistencia reportada  
**para** garantizar la continuidad asistencial en el sector afectado.

---

## Criterios de Aceptación

* **Acción de reemplazo:** Desde el listado de ausencias reportadas o desde una alerta del Tablero, opción **"Asignar reemplazo"**.
* **Filtro y sugerencias inteligentes:** El selector filtra y sugiere únicamente enfermeros activos que **no** tengan turno asignado en esa franja y que cuenten con margen en su carga horaria semanal.
* **Confirmación y actualización:** Al confirmar la cobertura, el turno original pasa a estado "Ausente justificado (Cubierto)", se crea la asignación vinculada para el enfermero reemplazante y desaparece la alerta de descubierto en el sector.

