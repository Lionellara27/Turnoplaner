# HU-04 | Restablecimiento de credenciales de acceso

**Épica:** ÉPICA A: ACCESO, SEGURIDAD Y CUENTA (Módulo General)  
**Prioridad:** S (Should - Obligatorio Etapa 2 / Final)  
**Puntos de Historia:** 8  

---

## Historia de Usuario

**Como** administrador,  
**quiero** restablecer la contraseña de un usuario  
**para** permitirle recuperar el acceso en caso de olvido o bloqueo.

---

## Criterios de Aceptación

* **Acción de restablecimiento:** Desde el listado de usuarios (ABMC), botón de acción "Restablecer contraseña" con confirmación previa.
* **Generación de credenciales temporales:** El sistema genera una clave temporal aleatoria (o restablece a una clave por defecto parametrizada), la guarda con hash y se la informa en pantalla al Administrador.
* **Exigencia de actualización:** En su próximo inicio de sesión, el sistema solicita al usuario actualizar su contraseña obligatoriamente.

