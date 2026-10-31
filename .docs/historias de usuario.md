

# **TURNOPLANER \- PLANIFICACIÓN DEL PROYECTO**

**Materia:** Proyecto de Software \- 2026 (UNRN)

**Proyecto:** Sistema de Asignación y Control de Turnos Rotativos de Enfermería

**Versión:** 0.1 — **Fecha:** 20/10/2026

**Pendiente:** Completar nombres del equipo, estimaciones (Planning Poker) y validar las dudas con la cátedra (Sección 9).

## **1\. RESUMEN DEL PROYECTO**

**Problema:**

Los centros de salud y hospitales necesitan planificar y supervisar de forma continua los turnos rotativos del personal de enfermería (mañana, tarde, noche y guardias especiales). La administración requiere balancear la carga horaria contratada de cada profesional, garantizar dotaciones mínimas en áreas críticas (UTI, Guardia, Neonatología) y gestionar ausencias imprevistas sin dejar sectores descubiertos ni afectar la continuidad asistencial.

**Solución:**

**TurnoPlaner**, una aplicación web que centraliza:

* Administración de catálogos base (Personal, Sectores y Tipos de Turno).  
* Planificación y asignación matricial de turnos (por sector, fecha y franja).  
* Consulta de cronogramas individuales (formato tabla/agenda).  
* Gestión de inasistencias justificadas y asignación de reemplazos.  
* Supervisión y reportería (tablero de alertas de cobertura e historial de horas trabajadas).

**Roles del sistema:**

* **Administrador / Jefe de Guardia:** Gestiona el personal (alta, edición, baja lógica y habilitación de cuentas), configura sectores y franjas horarias; arma y publica las cuadrículas semanales/mensuales; supervisa alertas de dotación mínima y asigna coberturas de reemplazo.  
* **Enfermero/a:** Se registra en el sistema (quedando pendiente de habilitación administrativa), gestiona su perfil, consulta su cronograma de turnos, notifica inasistencias justificadas (adjuntando motivo/certificado), registra cumplimiento de guardia y consulta su historial de horas.  
* **ROLES a definir/separar seguro hay** 

## **2\. ANÁLISIS DE LA CONSIGNA (Resolución de dudas y criterios de diseño)**

* **"Balancear cargas horarias" (¿manual o cálculo automático?)**

   ➔ Lo decide el Administrador de forma asistida al armar la cuadrícula. El sistema **no** asigna turnos automáticamente: suma las horas asignadas en la semana/ciclo (según la duración del tipo de turno) y valida o alerta si el enfermero supera su *"carga horaria máxima contratada"*. *(Ver Sección 9, Duda 1\)*.  
* **"Garantizar dotaciones mínimas" (UTI, Guardia, Neonatología)**

   ➔ Cada sector crítico tiene configurada una cantidad mínima de enfermeros requerida por franja horaria (ej. UTI requiere mínimo 3 enfermeros por turno). El sistema cuenta cuántos enfermeros activos están asignados a ese sector en cada fecha y franja, lo compara contra el mínimo del sector y, si es menor, dispara una alerta visual en el Tablero de Control.  
   *Nota de diseño:* En Etapa 1 se define un valor de dotación mínima general por sector para simplificar el ABMC *(Ver Sección 9, Duda 2\)*.  
* **"Registro de ausencias imprevistas" (¿personal o insumos?)**

   ➔ Se refiere exclusivamente a inasistencias del **personal de enfermería** (por enfermedad o fuerza mayor), no a insumos ni equipamiento. El enfermero reporta la ausencia (con motivo/certificado), esa baja descuenta un recurso disponible en la franja afectada y habilita al Administrador a cargar un enfermero de reemplazo.  
* **"Delimitando las operaciones entre la coordinación del hospital y el personal de enfermería en planta"**

   ➔ Define el control de acceso basado en roles (RBAC) mediante sesiones: la coordinación (**Rol Administrador**) accede a los ABMC, planificador y tablero de control; el personal en planta (**Rol Enfermero/a**) solo accede a su perfil, su agenda personal, sus avisos de ausencia y su historial.  
* **"Registro de asistencia y reportería"**

   ➔ **Asistencia:** Se refiere al check-in / check-out de guardia con comentarios. *Observación clave:* esta función figura en la descripción general del PDF, pero **no** se exige en el Régimen de Aprobación de Cursada ni de Final, por lo que se planifica como opcional / baja prioridad (*Could Have*).  
   ➔ **Reportería:** Comprende la vista de *"Historial Personal"* (horas trabajadas y guardias cubiertas) para el enfermero, y el *"Tablero de control de cobertura"* para el administrador (ambos de Etapa 2 \- Final).  
* **Forma de trabajo: VERTICAL (Cortes verticales por Historia de Usuario)**

   ➔ Cada Historia de Usuario (HU) se desarrolla de punta a punta atravesando todas las capas de la arquitectura MVC en CodeIgniter (Base de Datos MySQL \+ Modelo \+ Controlador \+ Vista HTML5/CSS3/JS) hasta quedar 100% funcional y validada dentro del Sprint. No se divide el proyecto en fases horizontales (*"primero toda la BD, después todo el backend"*).

## **3\. ALCANCE POR ETAPA (Según Régimen de Aprobación de la cátedra)**

### **ETAPA 1 \- APROBACIÓN DE LA CURSADA (Obligatorio — Entrega: 17 al 24/11/2026)**

**General**

* ☐ Registro en el sistema (alta de enfermero con estado pendiente de habilitación administrativa).  
* ☐ Login y cierre de sesión con control de roles (Admin / Enfermero).  
* ☐ Gestión de Mi Perfil (ver y editar datos propios y contraseña).

**Administrador**

* ☐ ABMC de Usuarios (Enfermeros/as: DNI, matrícula, contacto, especialidad, carga horaria máxima, habilitación de cuenta y baja lógica).  
* ☐ ABMC de Sectores / Servicios del hospital (UTI, Guardia, Neonatología, etc., definiendo su dotación mínima por franja).  
* ☐ ABMC de Tipos de Turno (nombre, hora inicio, hora fin y duración en hs).  
* ☐ Asignación individual y semanal de turnos a enfermeros (Planificador con control de horas acumuladas).

**Enfermero/a**

* ☐ Consulta de turnos asignados en formato tabla / agenda personal.

### **ETAPA 2 \- APROBACIÓN FINAL DE LA MATERIA**

**Administrador**

* ☐ Tablero de control de cobertura (alerta visual sobre sectores con dotación asignada por debajo del mínimo requerido en una franja horaria).  
* ☐ Carga y confirmación de coberturas de reemplazo ante inasistencias reportadas.  
* ☐ Restablecimiento de credenciales de acceso de usuarios.

**Enfermero/a**

* ☐ Aviso de ausencia imprevista (indicando fecha/turno afectado y motivo).  
* ☐ Consulta del historial acumulado de horas trabajadas y guardias cubiertas en el ciclo.

### **FUERA DE ALCANCE (Opcionales / Could Have — Solo si sobra tiempo)**

* Check-in / check-out de guardia con observaciones (mencionado en el enunciado general pero no exigido en el Régimen de Aprobación).  
* Subida de archivos físicos (PDF/JPG de certificado médico) en el aviso de ausencia (se resuelve con campo de texto de motivo/justificación).  
* Envío de notificaciones automáticas por correo electrónico (SMTP).  
* Algoritmo de generación y balanceo 100% automático de cuadrículas.

## **4\. STACK TECNOLÓGICO**

**Exigido por la cátedra:**

* **Backend:** PHP respetando los lineamientos del framework CodeIgniter.  
* **Base de datos:** MySQL.  
* **Frontend:** HTML5 \+ CSS3 (válidos según estándares W3C) \+ JavaScript.  
* **Permitido:** jQuery, Bootstrap, AJAX.

**Decisión arquitectónica del grupo:**

* **CodeIgniter 4** utilizando arquitectura MVC clásica (Modelos, Controladores y Vistas renderizadas desde el servidor con Layouts de CI4).  
* **Bootstrap 5** para maquetado responsive y modales; **jQuery \+ AJAX** para interacciones dinámicas sin recargar página (asignación en cuadrícula de turnos, filtros y validaciones asincrónicas).  
* **Se descarta Angular / API REST separada para la Etapa 1:** no son obligatorios en la consigna oficial y duplican la complejidad técnica 

**Herramientas de trabajo y entorno:**

* **Entorno local:** XAMPP / WampServer (PHP 8.1+ con extensiones intl y mbstring activas para CodeIgniter 4 \+ MySQL / phpMyAdmin).  
* **Control de versiones:** Git \+ GitHub (un repositorio único, rama main estable, rama develop de integración y ramas feature/HU-XX por historia).  
* **Tablero Scrum:** Trello o GitHub Projects (con columnas: *Backlog, To Do, In Progress, En Revisión, Done*).  
* **Estimación:** Planning Poker (cartas físicas o *planningpokeronline.com*).  
* **Control de calidad:** Validador oficial W3C Markup & CSS Validation Service.

**Estándares de usabilidad y validaciones (aplica a TODOS los formularios):**

* Indicaciones claras por campo (labels descriptivos, placeholders y textos de ayuda).  
* Validación doble (cliente con HTML5/JS y servidor con *Validation* de CI4).  
* Reglas de formato: campos obligatorios, DNI numérico único, email con formato válido y único, matrícula profesional y rangos horarios coherentes.  
* Feedback visual inmediato (mensajes de éxito/error con alertas de Bootstrap y modales de confirmación antes de dar de baja un registro).

## **5\. FORMA DE TRABAJO: METODOLOGÍA SCRUM (CORTES VERTICALES)**

**Roles del equipo (completar nombres):**

* **Product Owner (PO):** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_ *(prioriza el Product Backlog y valida criterios; la cátedra oficia de stakeholder/cliente)*.  
* **Scrum Master (SM):** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_ *(facilita ceremonias, elimina bloqueos y cuida que los cortes sean verticales)*.  
* **Development Team:** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_ , \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_ , \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_

**Duración del Sprint: 1 SEMANA (de lunes a domingo)**

*Justificación:* Contamos con exactamente 4 semanas hasta la ventana de entrega (17 al 24/11). Sprints de 1 semana permiten inspeccionar y adaptar rápido, evitar acumulación de deuda técnica y llegar a cada clase con un incremento funcional demostrable.

**Ceremonias:**

* **Sprint Planning (Lunes, 60 min):** Selección de Historias de Usuario (HU) del Product Backlog hacia el Sprint Backlog y desglose en tareas técnicas (BD \+ Modelo \+ Controlador \+ Vista).  
* **Daily Scrum (Diaria, asincrónica por WhatsApp/Discord o 10 min en vivo):** Cada integrante responde: 1\) Qué cerré ayer, 2\) Qué voy a hacer hoy, 3\) Qué bloqueo tengo.  
* **Sprint Review (Domingo / día de clase, 30 min):** Demo del software funcionando sobre la rama integrada para validar las HU terminadas.  
* **Sprint Retrospective (Domingo post-Review, 15 min):** Qué salió bien y qué ajustar en la comunicación o el código para el siguiente Sprint.

**Estimación de esfuerzo:**

* **Planning Poker** utilizando escala Fibonacci modificada: **1, 2, 3, 5, 8, 13**.  
* **Historia pivote / referencia (2 puntos):** HU-02 *"Login y cierre de sesión"*.  
* **Regla de partición:** Cualquier HU estimada en 13 puntos o más es demasiado grande para un Sprint de 1 semana y debe dividirse en historias más chicas.

**Definition of Ready — DoR (Criterios para que una HU pueda entrar al Sprint):**

* Redactada bajo el estándar: *"Como \<rol\>, quiero \<acción\> para \<beneficio\>"*.  
* Cuenta con Criterios de Aceptación claros y verificables.  
* Fue estimada en puntos de historia por el equipo (Planning Poker).  
* Sus dependencias previas (ej. tablas maestras) ya están resueltas o entran en el mismo Sprint.

**Definition of Done — DoD (Criterios para dar una HU por terminada):**

* Corte vertical completo: Tabla/ajuste en BD \+ Modelo CI4 \+ Controlador CI4 \+ Vista HTML5/Bootstrap funcionando de punta a punta.  
* Validaciones de formulario activas en frontend (HTML5/JS) y backend (CI4), respetando bajas lógicas cuando corresponda.  
* Código HTML5 y CSS3 sin errores estructurales (criterio W3C de la cátedra).  
* Código subido a GitHub mediante *Pull Request*, revisado por al menos otro compañero y fusionado a la rama principal sin conflictos.  
* Archivo database.sql (o migraciones/seeds de CI4) actualizado en el repositorio.  
* Probada contra todos sus Criterios de Aceptación en el entorno local.

## **6\. PRODUCT BACKLOG \- HISTORIAS DE USUARIO**

* **Prioridad MoSCoW:** **M** \= Must (Obligatorio Etapa 1 \- Cursada) \| **S** \= Should (Obligatorio Etapa 2 \- Final) \| **C** \= Could (Opcional / Extra).  
* **Puntos de Historia (Story Points):** A completar por el equipo durante el Planning Poker (escala Fibonacci: 1, 2, 3, 5, 8, 13).

### **ÉPICA A: ACCESO, SEGURIDAD Y CUENTA (Módulo General)**

**HU-01 \| Primer inicio de sesión y activación de cuenta de enfermero** — **Prioridad:** M \| **Puntos:** \[ 5 \]

**Historia:** Como enfermero/a dado de alta por la institución, quiero ingresar por primera vez con mis credenciales temporales para definir mi contraseña privada y completar mis datos personales antes de acceder al sistema. 

**Criterios de Aceptación:**

* **Interceptación de primer acceso:** Al autenticarse correctamente con la clave provisoria, si \`debe\_cambiar\_pass \== 1\`, el sistema (vía AuthFilter) bloquea la navegación general y redirige obligatoriamente al formulario de activación inicial.   
* **Campos del formulario:** Exige establecer una nueva contraseña (con confirmación) y completar los datos complementarios de contacto (Teléfono y Domicilio).   
* **Campos restringidos:** Los datos cargados por el Administrador (DNI, Matrícula, Especialidad y Carga Horaria) se muestran únicamente en modo lectura.   
* **Seguridad de contraseña:** La nueva clave se almacena encriptada mediante \`password\_hash()\` de PHP.   
* **Transición de estado:** Al procesarse el formulario con éxito, el sistema actualiza \`debe\_cambiar\_pass \= 0\`, marca la cuenta como \`ACTIVA\` y redirige a la vista "Mi Cronograma" con un mensaje de bienvenida. 

**HU-02 \| Login y cierre de sesión con control de roles (Historia Pivote)** — **Prioridad:** M \| **Puntos:** \[ 8 \]

**Historia:** Como usuario registrado, quiero iniciar y cerrar sesión en el sistema para acceder únicamente a las pantallas y operaciones que corresponden a mi rol.

* **Criterios de Aceptación**  
  * Formulario de Login: Presenta campos para Email (o DNI) y Contraseña. Cuenta con validaciones de campos requeridos tanto en cliente como en backend.  
  * Interceptación de Primer Inicio de Sesión (AuthFilter): Si las credenciales son válidas y la bandera debe\_cambiar\_pass \== 1, el sistema bloquea el acceso a las agendas y paneles principales y redirige obligatoriamente al formulario de activación de cuenta (HU-01).  
  * Validación de Estado de Cuenta: Si la cuenta se encuentra en estado "Baja", el sistema impide el ingreso y muestra un mensaje de error claro: "Cuenta inactiva o dada de baja. Contacte a la administración".  
  * Redirección por Rol (Acceso estándar): Si debe\_cambiar\_pass \== 0 y la cuenta está activa:  
    * Administrador: Redirige al panel general de gestión y planificador.}  
    * Enfermero/a: Redirige a su portal personal ("Mi Cronograma").  
  * Protección de Rutas (Filtros de Sesión y Roles): Mediante Filters de CodeIgniter 4, se restringe el acceso directo por URL a rutas no autorizadas. Si un usuario con rol Enfermero intenta ingresar a una URL administrativa, el sistema intercepta la petición, rechaza el acceso y redirige con una alerta de permisos insuficientes.  
  * Cierre de Sesión: Botón de "Cerrar sesión" siempre visible en la barra de navegación que destruye la sesión activa y redirige a la pantalla de Login.

**HU-03 \| Gestión de Mi Perfil** — **Prioridad:** M \| **Puntos:** \[ 3 \]

* **Historia:** Como usuario logueado, quiero consultar y editar mis datos de contacto y cambiar mi contraseña para mantener mi información personal actualizada.  
* **Criterios de aceptación:**  
  * Permite modificar: Teléfono, Email (validando que no pertenezca a otro usuario) y Contraseña (exigiendo ingresar la contraseña actual).  
  * Los campos administrativos (DNI, Matrícula, Especialidad y Carga horaria máxima contratada) se muestran en modo **solo lectura** (solo el Administrador puede modificarlos).  
  * Muestra confirmación visual al guardar los cambios.

**HU-04 \| Restablecimiento de credenciales de acceso** — **Prioridad:** S \| **Puntos:** \[ 8 \]

* **Historia:** Como administrador, quiero restablecer la contraseña de un usuario para permitirle recuperar el acceso en caso de olvido o bloqueo.  
* **Criterios de aceptación:**  
  * Desde el listado de usuarios (ABMC), botón de acción "Restablecer contraseña" con confirmación previa.  
  * El sistema genera una clave temporal aleatoria (o restablece a una clave por defecto parametrizada), la guarda con hash y se la informa en pantalla al Administrador.  
  * En su próximo inicio de sesión, el sistema solicita al usuario actualizar su contraseña.

### **ÉPICA B: ABMC DE USUARIOS Y PERSONAL (Administrador)**

**HU-05  \| Alta de enfermero/a por el administrador** — **Prioridad:** M \| **Puntos:** \[ 5 \]

**Historia:** Como administrador, quiero dar de alta a un enfermero/a en el sistema ingresando sus datos institucionales para asignarle sus credenciales iniciales de acceso e incorporarlo al plantel activo.

* **Criterios de Aceptación**:  
  * **Formulario Administrativo de Alta:** Campos obligatorios: Nombre, Apellido, DNI, Matrícula Profesional, Email, Especialidad y Carga Horaria Semanal Máxima Contratada (ej. 36 hs, 40 hs).  
  * **Validaciones de Unicidad y Formato:** Validación en cliente y servidor: DNI, Matrícula Profesional y Email deben ser únicos en la base de datos. Si alguno ya existe, emite un mensaje de error específico identificando el campo duplicado.  
  * **Asignación de Credenciales e Inicialización:** El sistema genera el registro guardando una contraseña temporal inicial encriptada mediante password\_hash() de PHP. Asigna automáticamente la bandera debe\_cambiar\_pass \= 1 para obligar al cambio de clave en el primer ingreso.  
  * **Estado Inicial:** El usuario se crea directamente en estado "Activo" (la aprobación la otorga el Administrador al momento del alta).  
  * **Confirmación Visual**: Al guardar exitosamente, muestra un mensaje de confirmación en el panel administrativo indicando que el legajo fue registrado y el usuario está listo para su primer acceso.

**HU-06 \| Consulta y búsqueda de personal** — **Prioridad:** M \| **Puntos:** \[ 3 \]

* **Historia:** Como administrador, quiero visualizar el listado completo del personal de enfermería con filtros de búsqueda para localizar rápidamente a cualquier integrante y revisar su situación.  
* **Criterios de aceptación:**  
  * Tabla con columnas: Nombre y Apellido, DNI, Matrícula, Especialidad, Carga horaria máxima, Rol y Estado (Pendiente / Activo / Baja).  
  * Buscador por texto (Nombre, Apellido o DNI) y filtros desplegables por **Estado** y **Especialidad**.  
  * Incluye paginación o tabla interactiva ordenada.

**HU-07 \| Edición de personal de enfermería** — **Prioridad:** M \| **Puntos:** \[ 3 \]

* **Historia:** Como administrador, quiero modificar los datos profesionales y de contratación de un enfermero/a para mantener actualizada la información del plantel del hospital.  
* **Criterios de Aceptación:**  
  * **Acceso y Formulario de Edición**: Accesible desde el listado de personal (HU-06) mediante una acción directa por fila. Carga el formulario precompletado con la información actual del enfermero/a seleccionado.  
  * **Campos Modificables:** Permite actualizar: Nombre, Apellido, Email, Teléfono, Especialidad y Carga Horaria Semanal Máxima Contratada.  
  * **Validación de Datos Modificados**: Si se modifica el Email, DNI o Matrícula Profesional, el backend valida que los nuevos valores no pertenezcan a otro usuario activo en la base de datos.  
  * **Integridad de Legajo**: Los cambios aplicados no alteran el historial de guardias pasadas ni la contraseña del usuario.  
  * **Guardado y Feedback**: Al procesar la actualización, guarda los datos en la tabla usuarios, actualiza el timestamp de modificación (updated\_at) y muestra un aviso visual de éxito al Administrador.

**HU-08 \| Baja lógica y reactivación de enfermero/a** — **Prioridad:** M \| **Puntos:** \[ 3 \]

* **Historia:** Como administrador, quiero dar de baja de forma lógica a un enfermero/a sin eliminar su registro físico para preservar el historial de turnos pasados e impedir nuevas asignaciones.  
* **Criterios de aceptación:**  
  * Solicita confirmación mediante ventana modal antes de aplicar la baja.  
  * El registro **no** se borra con DELETE en MySQL; su estado pasa a **"Baja"** (activo \= 0).  
  * Un enfermero dado de baja no puede iniciar sesión ni aparece disponible en los selectores del Planificador para turnos futuros.  
  * Permite reactivar al usuario desde el listado si vuelve a prestar servicio.

### **ÉPICA C: ABMC DE SECTORES / SERVICIOS HOSPITALARIOS (Administrador)**

**HU-09 \| Alta de sector hospitalario con dotación mínima** — **Prioridad:** M \| **Puntos:** \[ 5 \]

* **Historia:** Como administrador, quiero registrar los sectores o servicios del hospital (UTI, Guardia, Neonatología, Internación) y definir su dotación mínima de enfermeros por franja para garantizar la cobertura asistencial.  
* **Criterios de aceptación:**  
  * Campos: Nombre del sector (único), Descripción, Indicador de Área Crítica (Sí/No) y **Dotación mínima requerida por franja** (cantidad mínima de enfermeros necesarios en cualquier turno activo).  
  * *(Opcional / Mejora de diseño a validar con cátedra)*: Permitir detallar una dotación mínima específica para cada Tipo de Turno (Mañana, Tarde, Noche) y mostrar el cálculo de horas mínimas diarias de enfermería del sector.

**HU-10 \| Consulta, edición y baja lógica de sectores** — **Prioridad:** M \| **Puntos:** \[ 2 \]

* **Historia:** Como administrador, quiero listar, modificar y dar de baja lógica los sectores del hospital para reflejar cambios en la estructura operativa.  
* **Criterios de aceptación:**  
  * Tabla con: Nombre del sector, Tipo (Crítico / General), Dotación mínima requerida y Estado (Activo / Baja).  
  * Baja lógica con confirmación: un sector inactivo no puede seleccionarse para nuevas asignaciones de turnos.

### **ÉPICA D: ABMC DE TIPOS DE TURNOS (Administrador)**

**HU-11 \| Alta de tipo de turno (franjas horarias)** — **Prioridad:** M \| **Puntos:** \[ 4 \]

* **Historia:** Como administrador, quiero configurar los tipos de turnos y franjas horarias rotativas (ej. Mañana 06:00 a 14:00, Tarde 14:00 a 22:00, Noche 22:00 a 06:00, Guardia Especial 12 hs) para utilizarlos en la planificación.  
* **Criterios de aceptación:**  
  * Campos: Nombre del turno (único), Hora de inicio, Hora de fin, Duración en horas (calculada automáticamente o validada) y Etiqueta de color para identificarlo en la cuadrícula.  
  * Soporta correctamente el cálculo de duración en franjas nocturnas que cruzan la medianoche (ej. de 22:00 a 06:00 \= 8 horas).

**HU-12 \| Consulta, edición y baja lógica de tipos de turno** — **Prioridad:** M \| **Puntos:** \[ \_\_\_ \]

* **Historia:** Como administrador, quiero listar, editar y dar de baja lógica los tipos de turno configurados.  
* **Criterios de aceptación:**  
  * Listado con Nombre, Rango horario (Inicio \- Fin), Cantidad de horas, Color y Estado.  
  * Baja lógica: el sistema valida que no existan asignaciones futuras activas con ese tipo de turno antes de permitir desactivarlo.

### **ÉPICA E: PLANIFICACIÓN, CUADRÍCULAS Y COBERTURAS (Administrador)**

**HU-13 \| Asignación individual de turno con control de carga horaria** — **Prioridad:** M \| **Puntos:** \[ 13 \]

* **Historia:** Como administrador, quiero asignar un turno específico (sector, fecha y franja) a un enfermero/a validando su disponibilidad para cubrir la demanda sin generar superposiciones ni excesos de carga horaria.  
* **Criterios de aceptación:**  
  * Selección de: Enfermero/a (solo activos), Sector (solo activos), Tipo de Turno (solo activos) y Fecha.  
  * **Reglas de validación del negocio:**  
    1. Rechaza la asignación si el enfermero ya tiene otro turno asignado en la misma fecha y franja horaria superpuesta.  
    2. Suma las horas ya asignadas al enfermero en esa semana más la duración del nuevo turno; si supera su **Carga horaria máxima contratada**, emite alerta/rechazo explicando el motivo.  
  * Permite eliminar o reasignar un turno futuro ya cargado.

**HU-14 \| Planificador semanal de cuadrículas** — **Prioridad:** M \| **Puntos:** \[ 13 \]

* **Historia:** Como administrador, quiero gestionar una cuadrícula matricial semanal por sector (filas: tipos de turno; columnas: días de la semana) para planificar y balancear la dotación de manera visual y ágil.  
* **Criterios de aceptación:**  
  * Filtros superiores por **Sector** y **Semana** (con botones Semana Anterior / Siguiente).  
  * Cada celda (Franja \+ Día) lista los enfermeros asignados y permite agregar o quitar personal (invocando las validaciones de la HU-13 mediante modal y peticiones AJAX).  
  * Indicador visual en cada celda de **Asignados vs. Dotación Mínima** (ej. 2 / 3 enfermeros).  
  * Resumen inferior o lateral con el total de **horas semanales acumuladas** por cada enfermero frente a su máximo contratado (para facilitar el balanceo manual de cargas).

**HU-15 \| Tablero de control de cobertura (Alertas visuales)** — **Prioridad:** S \| **Puntos:** \[ 8 \]

* **Historia:** Como administrador, quiero visualizar un tablero general de control que resalte con alertas visuales los sectores y franjas cuya dotación esté por debajo del mínimo requerido para detectar y resolver descubiertos a tiempo.  
* **Criterios de aceptación:**  
  * Vista panorámica por semana/día de todos los sectores activos (especialmente áreas críticas: UTI, Guardia, Neonatología).  
  * Código de colores tipo semáforo por franja: **Verde** (dotación igual o superior al mínimo) y **Rojo / Alerta** (dotación asignada menor a la dotación mínima del sector).  
  * Las ausencias reportadas y aprobadas descuentan automáticamente personal disponible de la celda, actualizando la alerta en tiempo real.  
  * Al hacer clic sobre una alerta roja, redirige al Planificador (HU-14) o a la carga de reemplazo (HU-16) en ese sector y fecha.

**HU-16 \| Carga y confirmación de coberturas de reemplazo** — **Prioridad:** S \| **Puntos:** \[ 13 \]

* **Historia:** Como administrador, quiero asignar y confirmar un enfermero/a de reemplazo ante una inasistencia reportada para garantizar la continuidad asistencial en el sector afectado.  
* **Criterios de aceptación:**  
  * Desde el listado de ausencias reportadas o desde una alerta del Tablero, opción **"Asignar reemplazo"**.  
  * El selector filtra y sugiere únicamente enfermeros activos que **no** tengan turno asignado en esa franja y que cuenten con margen en su carga horaria semanal.  
  * Al confirmar la cobertura, el turno original pasa a estado "Ausente justificado (Cubierto)", se crea la asignación vinculada para el enfermero reemplazante y desaparece la alerta de descubierto en el sector.

### **ÉPICA F: PORTAL DEL ENFERMERO/A**

**HU-17 \| Consulta de Mi Cronograma (Agenda mensual/semanal)** — **Prioridad:** M \| **Puntos:** \[ 8 \]

* **Historia:** Como enfermero/a, quiero consultar mis turnos asignados en formato de tabla y/o agenda interactiva para conocer con claridad qué días, en qué horarios y en qué sector del hospital debo presentarme.  
* **Criterios de aceptación:**  
  * Vista filtrable por mes y semana con navegación (Anterior / Actual / Siguiente).  
  * Muestra para cada guardia: Fecha, Día de la semana, Sector asignado, Tipo de Turno (con su color distintivo), Horario de inicio y fin, y Estado del turno.  
  * Privacidad por rol: el enfermero logueado visualiza estrictamente sus propias asignaciones.

**HU-18 \| Notificación / Aviso de ausencia imprevista** — **Prioridad:** S \| **Puntos:** \[ 5 \]

* **Historia:** Como enfermero/a, quiero notificar formalmente una inasistencia a un turno futuro indicando el motivo para que la coordinación del hospital pueda gestionar mi reemplazo.  
* **Criterios de aceptación:**  
  * Desde "Mi Cronograma" o sección "Mis Ausencias", permite seleccionar un turno futuro propio y reportar inasistencia.  
  * Campos obligatorios: Tipo de motivo (Enfermedad, Fuerza mayor, Licencia médica, Otro) y Descripción/Observación justificativa.  
  * El aviso se registra y impacta en el panel del Administrador para su gestión de cobertura (HU-16), mostrando al enfermero el estado de su solicitud.

**HU-19 \| Consulta de Historial Personal de horas y guardias** — **Prioridad:** S \| **Puntos:** \[ 8 \]

* **Historia:** Como enfermero/a, quiero consultar mi historial acumulado de horas trabajadas, turnos cubiertos y ausencias registradas para llevar el control de mi desempeño y carga laboral.  
* **Criterios de aceptación:**  
  * Filtro por período (Mes / Año).  
  * Tarjetas de resumen (KPIs personales): Total de horas cumplidas en el mes (comparado contra su carga contratada), Cantidad de guardias cubiertas (normales y reemplazos) y Cantidad de ausencias registradas.  
  * Tabla detallada con el desglose cronológico de cada turno del período.

**HU-20 \| Registro de cumplimiento de turno (Check-in / Check-out)** — **Prioridad:** C \| **Puntos:** \[ 15 \]

* **Historia:** Como enfermero/a, quiero registrar mi ingreso (check-in) y salida (check-out) de la guardia junto con observaciones de entrega de turno para dejar constancia efectiva de asistencia y novedades clínicas.  
* **Criterios de aceptación:**  
  * *(Funcionalidad opcional sujeta a disponibilidad de tiempo luego de cubrir Etapa 1 y 2\)*.  
  * Botón de "Marcar Ingreso" y "Finalizar Guardia" en el turno del día actual, con campo de texto para comentarios de pase de guardia.

### **TAREAS TÉCNICAS TRANSVERSALES (Apoyo al Sprint 1 y 4\)**

* **TT-01 \| Configuración de repositorio y entorno base:** Inicialización de proyecto en CodeIgniter 4, configuración de .env, .gitignore, estructura MVC y guía en README.md de instalación en XAMPP/WampServer.  
* **TT-02 \| Base de datos inicial y Seeds:** Creación del script database.sql con las tablas relacionales e inserción de datos de prueba (1 Admin, 5 Enfermeros, 3 Sectores críticos: UTI, Guardia, Neonatología, y 4 Tipos de Turno).  
* **TT-03 \| Maquetado de Layout principal:** Plantilla base con Bootstrap 5, barra de navegación dinámica según rol en sesión, alertas flash de éxito/error y validación W3C HTML5/CSS3.  
* **TT-04 \| Gestión del Tablero Scrum:** Creación del proyecto en Trello / GitHub Projects con columnas del flujo ágil y carga de las 20 Historias de Usuario.  
* **TT-05 \| Cierre y Presentación Final:** Revisión integral de validaciones en todos los formularios, exportación final del .sql y armado de las diapositivas de defensa grupal.

## **7\. MODELO DE DATOS TENTATIVO (MySQL)**

**Regla de integridad:** Todos los ABMC utilizan **baja lógica** mediante la columna estado (nunca se ejecutan sentencias DELETE físicas sobre catálogos o personal con historial).

| Tabla | Columnas y Tipos Clave | Descripción / Relaciones |
| :---- | :---- | :---- |
| **usuarios** | id (PK), nombre, apellido, dni (UNIQUE), matricula (UNIQUE), mail (UNIQUE), telefono, password\_hash, rol (ADMIN \| ENFERMERO), especialidad, carga\_horaria\_max (INT, hs/semana), estado (PENDIENTE \| ACTIVO \| BAJA), debe\_cambiar\_pass (TINYINT), created\_at, updated\_at | Almacena tanto administradores como enfermeros/as. |
| **sectores** | id (PK), nombre (UNIQUE), descripcion, es\_critico (TINYINT), dotacion\_minima\_general (INT), estado (ACTIVO \| BAJA) | Servicios del hospital (UTI, Guardia, Neonatología, Internación). |
| **tipos\_turno** | id (PK), nombre (UNIQUE), hora\_inicio (TIME), hora\_fin (TIME), duracion\_hs (INT), color (VARCHAR), estado (ACTIVO \| BAJA) | Franjas horarias rotativas (Mañana, Tarde, Noche, Guardia 12 hs). |
| **dotacion\_minima** | id (PK), sector\_id (FK), tipo\_turno\_id (FK), cantidad\_minima (INT) | Clave única compuesta (sector\_id \+ tipo\_turno\_id). Horas mínimas \= cantidad\_minima × duracion\_hs. |
| **asignaciones** | id (PK), usuario\_id (FK), sector\_id (FK), tipo\_turno\_id (FK), fecha (DATE), estado (ASIGNADO \| AUSENTE \| CUBIERTO \| CUMPLIDO), creado\_por (FK), created\_at | Tabla central del Planificador de Cuadrículas y de Mi Cronograma. |
| **ausencias** | id (PK), asignacion\_id (FK), motivo, observacion, estado (PENDIENTE \| APROBADA \| RECHAZADA \| CUBIERTA), created\_at | Avisos de inasistencia justificada cargados por el enfermero/a (Etapa 2). |
| **coberturas** | id (PK), ausencia\_id (FK), usuario\_reemplazo\_id (FK), confirmado\_por (FK), created\_at | Registro de reemplazos ante ausencias para evitar descubiertos (Etapa 2). |
| **marcaciones** | id (PK), asignacion\_id (FK), hora\_ingreso (DATETIME), hora\_salida (DATETIME), observaciones (TEXT) | Registro de check-in / check-out con comentarios (Opcional \- solo si se desarrolla HU-20). |

## **8\. PLAN DE SPRINTS PROPUESTO (ETAPA 1 Y 2\)**

**Fecha de inicio:** Martes 20/10/2026 \| **Ventana de entrega de Cursada:** 17 al 24/11/2026

* **SPRINT 0 (20/10 al 25/10) — Preparación y Cimientos**  
  * Cerrar este documento de planificación con el grupo y asignar roles Scrum.  
  * Estimar en Planning Poker todas las Historias de Usuario de prioridad **M (Must)**.  
  * Completar **TT-01** (Repo Git \+ CodeIgniter 4), **TT-02** (Script SQL inicial \+ datos de prueba) y **TT-04** (Tablero Scrum).  
  * **Objetivo demostrable:** Entorno local (XAMPP/WAMP \+ CI4 \+ MySQL) funcionando en las máquinas de todos los integrantes.  
* **SPRINT 1 (26/10 al 01/11) — Acceso, Roles y Alta de Personal**  
  * **Historias:** **HU-01** (Registro), **HU-02** (Login y control de roles), **HU-03** (Mi Perfil) y **TT-03** (Layout base con menú dinámico).  
  * **Historias Admin:** **HU-05** (Alta de enfermero/a) y **HU-06** (Listado y búsqueda de personal).  
  * **Objetivo demostrable:** Un administrador inicia sesión, crea y lista enfermeros; un enfermero se registra desde afuera, queda pendiente y al ser habilitado logra ingresar a su panel.  
* **SPRINT 2 (02/11 al 08/11) — Configuración Hospitalaria (ABMCs completos)**  
  * **Historias Personal:** **HU-07** (Edición y habilitación de cuenta) y **HU-08** (Baja lógica).  
  * **Historias Catálogos:** **HU-09** y **HU-10** (ABMC de Sectores con dotación mínima) \+ **HU-11** y **HU-12** (ABMC de Tipos de Turno).  
  * **Objetivo demostrable:** Todos los ABMC obligatorios para aprobar la cursada funcionando de punta a punta con validaciones.  
* **SPRINT 3 (09/11 al 15/11) — Planificación y Consulta de Turnos (Core)**  
  * **Historias Planificador:** **HU-13** (Asignación individual con validación de carga horaria) y **HU-14** (Cuadrícula semanal interactiva).  
  * **Historias Enfermero:** **HU-17** (Consulta de Mi Cronograma en formato tabla/agenda).  
  * **Objetivo demostrable:** Etapa 1 (Aprobación de Cursada) 100% completa e integrada de punta a punta.  
* **SEMANA FINAL / SPRINT 4 (16/11 al 24/11) — Calidad, Cierre y Defensa**  
  * Pruebas integrales de regresión, corrección de bugs y validación de estándares HTML5/CSS3 (W3C).  
  * Completar **TT-05**: Armado de presentación grupal, exportación de database.sql final y empaquetado .zip del código fuente.  
  * *Buffer opcional (si sobra tiempo):* Adelantar **HU-18** (Aviso de ausencia), ya que es el disparador de la Etapa 2\.  
* **ETAPA 2 (Post-cursada / Examen Final de la Materia)**  
  * Desarrollo de **HU-04** (Restablecer credenciales), **HU-15** (Tablero de control de cobertura con alertas visuales), **HU-16** (Coberturas de reemplazo), **HU-18** (Aviso de ausencia), **HU-19** (Historial personal de horas) y, opcionalmente, **HU-20** (Check-in / Check-out).

**Regla de oro del equipo:** Si un Sprint se atrasa, se recorta alcance no crítico (pasando una HU al siguiente Sprint), pero **nunca se estira la fecha de cierre del Sprint**.

## **9\. DUDAS PARA VALIDAR CON LA CÁTEDRA (Product Owner / Cliente)**

1. "Balancear cargas horarias": ¿Alcanza con que el sistema **valide** que el enfermero no supere su carga horaria máxima semanal contratada y muestre el contador de horas acumuladas en la cuadrícula, o se espera algún algoritmo que reparta o sugiera turnos automáticamente?  
2. "Asignación individual y semanal": ¿La asignación semanal se resuelve mediante la vista de Cuadrícula Semanal (HU-14) donde el administrador va cargando los días de esa semana, o se espera poder clonar una semana completa / aplicar un patrón rotativo automático (ej. *Mañana-Mañana-Tarde-Tarde-Noche-Franco*)?  
3. Configuración de Dotación Mínima: ¿Se define por Sector y Tipo de Turno (ej. UTI requiere 3 en Mañana, 3 en Tarde y 2 en Noche, como modelamos en la tabla dotacion\_minima), es un único número fijo por sector, o también varía según el día de la semana (lunes a viernes vs. fin de semana)?  
4. Flujo de Alta de Enfermeros/as: Planteamos tanto el autorregistro público (quedando en estado *"Pendiente de habilitación"* hasta que el Admin lo apruebe) como el alta directa desde el ABMC del Administrador. ¿Es correcto mantener ambas vías?  
5. Versión y lineamientos de CodeIgniter: ¿Trabajamos con **CodeIgniter 4**? ¿Existe alguna plantilla base o convención específica de la cátedra que debamos respetar?  
6. Arquitectura Frontend/Backend: Para asegurar los plazos de la Etapa 1, decidimos usar **CodeIgniter MVC clásico** (vistas PHP renderizadas con Bootstrap y peticiones puntuales con jQuery/AJAX) en lugar de separar en API REST \+ Angular. ¿Están de acuerdo con este enfoque?  
7. "Guardia Especial de 12 hs": ¿Es simplemente un registro más dentro del ABMC de Tipos de Turno con hora de inicio y fin fijas (ej. 08:00 a 20:00) definido por el Administrador, o tiene un comportamiento distinto al resto de las franjas?  
8. **Fecha exacta de defensa:** Dentro de la ventana del **17 al 24 de noviembre**, ¿qué día específico rinde nuestro grupo?

## **10\. ENTREGABLES FINALES**

1. **Presentación grupal:** Contar **todo** el proceso de desarrollo (este documento, Product Backlog, tablero Scrum, estimaciones, planificación de sprints, retrospectivas, qué salió bien y qué dificultades tuvieron).  
2. **Demo funcionando:** Demostración en vivo del software operativo con todas las funcionalidades obligatorias especificadas para la Etapa 1 (Cursada).  
3. **Paquete de código fuente:** Archivo comprimido (`.zip`) que contenga el código fuente completo del proyecto, los scripts SQL de la base de datos (estructura relacional y datos iniciales de prueba) y un archivo `README.md` detallando los pasos de instalación local y los usuarios de prueba con sus roles.

NOTA: Guardar capturas de pantalla del tablero Scrum al finalizar cada Sprint para utilizarlas como evidencia visual en las diapositivas de la presentación 

## **11\. GLOSARIO TÉCNICO Y DE NEGOCIO**

* **ABMC:** Alta, Baja, Modificación y Consulta.  
* **Baja lógica:** Acción de marcar un registro como inactivo (`estado = 0` o `BAJA`) en lugar de eliminarlo físicamente de la base de datos, preservando la integridad referencial del historial.  
* **Dotación mínima:** Cantidad mínima requerida de personal de enfermería (y por ende, la cantidad mínima de horas asistenciales) en un sector hospitalario determinado para cubrir una franja horaria específica.  
* **Franja / Tipo de turno:** Bloque horario rotativo establecido (ej. Turno Mañana, Turno Tarde, Turno Noche, Guardia Especial).  
* **Cuadrícula:** Grilla o matriz visual de planificación que cruza el Sector, el Día y la Franja Horaria con los enfermeros asignados.  
* **Descubierto:** Situación crítica en la cual una franja horaria de un sector cuenta con menos enfermeros asignados que los exigidos por su dotación mínima.  
* **Cobertura:** Recurso humano de reemplazo asignado formalmente para cubrir un turno que quedó afectado por una ausencia justificada.  
* **HU (Historia de Usuario):** Requerimiento funcional del sistema redactado desde la perspectiva del rol que obtiene el beneficio (*"Como... quiero... para..."*).  
* **MoSCoW:** Técnica de priorización de requerimientos dividida en *Must have* (obligatorio), *Should have* (deseable), *Could have* (opcional) y *Won't have* (fuera de alcance).  
* **DoR / DoD:** *Definition of Ready* (criterios que debe cumplir una HU antes de entrar a un Sprint) / *Definition of Done* (criterios de calidad que debe cumplir una HU para darse por 100% terminada).

