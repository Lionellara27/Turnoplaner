# HU-01 | Primer inicio de sesión y activación de cuenta de enfermero

**Épica:** ÉPICA A: ACCESO, SEGURIDAD Y CUENTA (Módulo General)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 5  

---

## Historia de Usuario

**Como** enfermero/a dado de alta por la institución,  
**quiero** ingresar por primera vez con mis credenciales temporales  
**para** definir mi contraseña privada y completar mis datos personales antes de acceder al sistema.

---

## Criterios de Aceptación

* **Interceptación de primer acceso:** Al autenticarse correctamente con la clave provisoria, si `debe_cambiar_pass == 1`, el sistema (vía AuthFilter) bloquea la navegación general y redirige obligatoriamente al formulario de activación inicial.
* **Campos del formulario:** Exige establecer una nueva contraseña (con confirmación) y completar los datos complementarios de contacto (Teléfono y Domicilio).
* **Campos restringidos:** Los datos cargados por el Administrador (DNI, Matrícula, Especialidad y Carga Horaria) se muestran únicamente en modo lectura.
* **Seguridad de contraseña:** La nueva clave se almacena encriptada mediante `password_hash()` de PHP.
* **Transición de estado:** Al procesarse el formulario con éxito, el sistema actualiza `debe_cambiar_pass = 0`, marca la cuenta como `ACTIVA` y redirige a la vista "Mi Cronograma" con un mensaje de bienvenida.

