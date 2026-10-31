# HU-02 | Login y cierre de sesión con control de roles (Historia Pivote)

**Épica:** ÉPICA A: ACCESO, SEGURIDAD Y CUENTA (Módulo General)  
**Prioridad:** M (Must - Obligatorio Etapa 1)  
**Puntos de Historia:** 8  

---

## Historia de Usuario

**Como** usuario registrado,  
**quiero** iniciar y cerrar sesión en el sistema  
**para** acceder únicamente a las pantallas y operaciones que corresponden a mi rol.

---

## Criterios de Aceptación

* **Formulario de Login:** Presenta campos para Email (o DNI) y Contraseña. Cuenta con validaciones de campos requeridos tanto en cliente como en backend.
* **Interceptación de Primer Inicio de Sesión (AuthFilter):** Si las credenciales son válidas y la bandera `debe_cambiar_pass == 1`, el sistema bloquea el acceso a las agendas y paneles principales y redirige obligatoriamente al formulario de activación de cuenta (HU-01).
* **Validación de Estado de Cuenta:** Si la cuenta se encuentra en estado "Baja", el sistema impide el ingreso y muestra un mensaje de error claro: *"Cuenta inactiva o dada de baja. Contacte a la administración"*.
* **Redirección por Rol (Acceso estándar):** Si `debe_cambiar_pass == 0` y la cuenta está activa:
  * **Administrador:** Redirige al panel general de gestión y planificador.
  * **Enfermero/a:** Redirige a su portal personal ("Mi Cronograma").
* **Protección de Rutas (Filtros de Sesión y Roles):** Mediante Filters de CodeIgniter 4, se restringe el acceso directo por URL a rutas no autorizadas. Si un usuario con rol Enfermero intenta ingresar a una URL administrativa, el sistema intercepta la petición, rechaza el acceso y redirige con una alerta de permisos insuficientes.
* **Cierre de Sesión:** Botón de "Cerrar sesión" siempre visible en la barra de navegación que destruye la sesión activa y redirige a la pantalla de Login.

