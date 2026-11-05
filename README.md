# TurnoPlaner

Sistema de Asignacion y Control de Turnos Rotativos de Enfermeria.
Trabajo grupal de la materia **Proyecto de Software 2026**.

Stack: PHP 8.2 + CodeIgniter 4.7 + MySQL (XAMPP) + HTML5 / CSS3 + Bootstrap 5.3 + JavaScript.

## Requisitos

- XAMPP con PHP 8.1 o superior (probado con 8.2.12).
- En `C:\xampp\php\php.ini` tiene que estar activa la extension `intl`
  (buscar la linea `;extension=intl` y sacarle el `;`). CodeIgniter 4 no arranca sin ella.
- Git.

## Puesta en marcha (cada integrante, una sola vez)

1. Clonar el repositorio.
2. Copiar el archivo `env` a `.env` y editar:
   ```
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'
   app.indexPage = ''
   database.default.hostname = localhost
   database.default.database = turnoplaner
   database.default.username = root
   database.default.password =
   database.default.DBDriver = MySQLi
   ```
3. Levantar el servidor de desarrollo desde la carpeta del proyecto:
   ```
   C:\xampp\php\php.exe spark serve
   ```
   (si `php` esta en el PATH alcanza con `php spark serve`).
4. Crear la base de datos con el script (XAMPP tiene que tener MySQL iniciado):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database\turnoplaner.sql
   ```
   Tambien se puede importar `database/turnoplaner.sql` desde phpMyAdmin.

   Alternativa con migraciones de CodeIgniter (crea la misma estructura y los mismos usuarios):
   ```
   C:\xampp\php\php.exe spark migrate
   C:\xampp\php\php.exe spark db:seed TurnoplanerSeeder
   ```
5. Abrir http://localhost:8080 en el navegador.

Usuarios de prueba que crea el script (solo para desarrollo):

| Email | Contrasena | Rol | Estado | Primer ingreso |
|-------|------------|-----|--------|----------------|
| admin@turnoplaner.local | admin123 | ADMIN | ACTIVO | No |
| enfermera@turnoplaner.local | enfermero123 | ENFERMERO | ACTIVO | No |
| nuevo@turnoplaner.local | enfermero123 | ENFERMERO | ACTIVO | Si, debe cambiar la contrasena al entrar |

Se puede ingresar con el email o con el DNI (con o sin puntos).
Las cuentas las crea el administrador desde el sistema; no hay registro publico.

Alternativa con Apache de XAMPP: copiar la carpeta a `C:\xampp\htdocs\turnoplaner`
y entrar a http://localhost/turnoplaner/public (cambiar `app.baseURL` acorde).

## Estado actual

| HU | Que hace | Estado |
|----|----------|--------|
| HU-01 | Primer ingreso: define su contrasena y completa telefono y domicilio | Hecha |
| HU-02 | Login con email o DNI, control de roles y cierre de sesion | Hecha |
| HU-03 | Mi perfil: datos de contacto y cambio de contrasena | Hecha |
| HU-04 | Restablecer la contrasena de un enfermero/a (admin) | Hecha |
| HU-05 | Alta de enfermero/a con contrasena temporal (admin) | Hecha |
| HU-06 | Listado con busqueda, filtros y paginacion (admin) | Hecha |
| HU-07 | Edicion del legajo (admin) | Hecha |
| HU-08 | Baja logica y reactivacion (admin) | Hecha |
| HU-09 | Alta de sector con dotacion minima por turno | Hecha |
| HU-10 | Listado, edicion, baja y reactivacion de sectores | Hecha |
| HU-11 | Alta de tipo de turno con duracion calculada (incluye turnos que cruzan la medianoche) | Hecha |
| HU-12 | Listado, edicion, baja y reactivacion de tipos de turno | Hecha |
| HU-13 | Asignar, reasignar y eliminar turnos, con control de superposicion y de carga horaria semanal | Hecha |
| HU-14 | Cuadricula semanal por sector: cobertura, agregar/quitar con modal (AJAX) y resumen de horas por enfermero | Hecha |
| HU-15 a HU-20 | Tablero de cobertura, coberturas, mi cronograma con turnos, ausencias, historial | Pendientes |

## Pruebas

Con el servidor levantado y MySQL iniciado, desde Git Bash en la carpeta del proyecto:

```
bash tests/e2e/turnoplaner_e2e.sh
C:\xampp\php\php.exe spark test:hu05
```

`turnoplaner_e2e.sh` recorre los criterios de aceptacion de HU-01 a HU-14 (140 casos) con sesion, CSRF, AJAX y base de datos.
**Reinicia la base local** con `database/turnoplaner.sql` antes de empezar.

## Donde esta cada cosa

| Que | Archivo |
|-----|---------|
| Rutas (URL -> controlador) | `app/Config/Routes.php` |
| Login, logout y activacion de cuenta | `app/Controllers/Auth.php` |
| Mi perfil | `app/Controllers/Perfil.php` |
| Gestion de personal (admin) | `app/Controllers/Usuarios.php` |
| Sectores y tipos de turno (admin) | `app/Controllers/Sectores.php`, `app/Controllers/TiposTurno.php` |
| Asignacion de turnos y cuadricula semanal | `app/Controllers/Asignaciones.php`, `app/Controllers/Planificador.php` |
| Panel del admin / portal del enfermero | `app/Controllers/Home.php` / `app/Controllers/Cronograma.php` |
| Utilidades comunes de controladores | `app/Controllers/BaseController.php` |
| Filtros de sesion y de rol | `app/Filters/AuthFilter.php`, `app/Filters/RoleFilter.php` |
| Manejo de la sesion | `app/Libraries/SesionUsuario.php` |
| Modelos y entidades | `app/Models/`, `app/Entities/` (baja logica compartida en `app/Models/Concerns/BajaLogica.php`) |
| Helpers de formularios | `app/Helpers/form_ui_helper.php` |
| Layout comun y partes compartidas | `app/Views/layouts/main.php`, `app/Views/partials/` |
| Vistas | `app/Views/auth/`, `app/Views/perfil/`, `app/Views/admin/users/`, `app/Views/home/`, `app/Views/cronograma/` |
| Script de base de datos | `database/turnoplaner.sql` |
| Migraciones y seeders | `app/Database/Migrations/`, `app/Database/Seeds/` (`TurnoplanerSeeder` carga todo) |
| Pruebas de punta a punta | `tests/e2e/turnoplaner_e2e.sh` |
| Guia de diseno (estetica) | `.docs/DESIGN.md` |
| Agentes y skills de IA | `.agents/AGENTS.md`, `.agents/skills/` |
| Estilos | `public/css/app.css` |
| JavaScript | `public/js/app.js` |

## Convenciones del equipo

- Una rama por historia: `feature/HU-05-alta-enfermero`. Se integra a `main` por Pull Request revisado por otro integrante.
- Nombres de archivos, clases y variables en espanol sin tildes (como en el enunciado: ABMC, sector, turno).
- Toda pantalla nueva extiende `layouts/main` y sigue `.docs/DESIGN.md`.
- Todo formulario valida en cliente (HTML5/JS) **y** en servidor (Validation de CI4).
