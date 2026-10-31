# HU-03 | Gestión de Mi Perfil

**Épica:** ÉPICA A: ACCESO, SEGURIDAD Y CUENTA (Módulo General)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 3  

---

## Historia de Usuario

**Como** usuario logueado,  
**quiero** consultar y editar mis datos de contacto y cambiar mi contraseña  
**para** mantener mi información personal actualizada.

---

## Criterios de Aceptación

* **Modificación de datos permitidos:** Permite modificar: Teléfono, Email (validando que no pertenezca a otro usuario) y Contraseña (exigiendo ingresar la contraseña actual).
* **Campos restringidos:** Los campos administrativos (DNI, Matrícula, Especialidad y Carga horaria máxima contratada) se muestran en modo **solo lectura** (solo el Administrador puede modificarlos).
* **Feedback:** Muestra confirmación visual al guardar los cambios.

