# HU-07 | Edición de personal de enfermería

**Épica:** ÉPICA B: ABMC DE USUARIOS Y PERSONAL (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 3  

---

## Historia de Usuario

**Como** administrador,  
**quiero** modificar los datos profesionales y de contratación de un enfermero/a  
**para** mantener actualizada la información del plantel del hospital.

---

## Criterios de Aceptación

* **Acceso y Formulario de Edición:** Accesible desde el listado de personal (HU-06) mediante una acción directa por fila. Carga el formulario precompletado con la información actual del enfermero/a seleccionado.
* **Campos Modificables:** Permite actualizar: Nombre, Apellido, Email, Teléfono, Especialidad y Carga Horaria Semanal Máxima Contratada.
* **Validación de Datos Modificados:** Si se modifica el Email, DNI o Matrícula Profesional, el backend valida que los nuevos valores no pertenezcan a otro usuario activo en la base de datos.
* **Integridad de Legajo:** Los cambios aplicados no alteran el historial de guardias pasadas ni la contraseña del usuario.
* **Guardado y Feedback:** Al procesar la actualización, guarda los datos en la tabla `usuarios`, actualiza el timestamp de modificación (`updated_at`) y muestra un aviso visual de éxito al Administrador.

