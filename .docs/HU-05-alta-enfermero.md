# HU-05 | Alta de enfermero/a por el administrador

**Épica:** ÉPICA B: ABMC DE USUARIOS Y PERSONAL (Administrador)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 5  

---

## Historia de Usuario

**Como** administrador,  
**quiero** dar de alta a un enfermero/a en el sistema ingresando sus datos institucionales  
**para** asignarle sus credenciales iniciales de acceso e incorporarlo al plantel activo.

---

## Criterios de Aceptación

* **Formulario Administrativo de Alta:** Campos obligatorios: Nombre, Apellido, DNI, Matrícula Profesional, Email, Especialidad y Carga Horaria Semanal Máxima Contratada (ej. 36 hs, 40 hs).
* **Validaciones de Unicidad y Formato:** Validación en cliente y servidor: DNI, Matrícula Profesional y Email deben ser únicos en la base de datos. Si alguno ya existe, emite un mensaje de error específico identificando el campo duplicado.
* **Asignación de Credenciales e Inicialización:** El sistema genera el registro guardando una contraseña temporal inicial encriptada mediante `password_hash()` de PHP. Asigna automáticamente la bandera `debe_cambiar_pass = 1` para obligar al cambio de clave en el primer ingreso.
* **Estado Inicial:** El usuario se crea directamente en estado "Activo" (la aprobación la otorga el Administrador al momento del alta).
* **Confirmación Visual:** Al guardar exitosamente, muestra un mensaje de confirmación en el panel administrativo indicando que el legajo fue registrado y el usuario está listo para su primer acceso.

