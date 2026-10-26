# TurnoPlaner

Sistema de Asignacion y Control de Turnos Rotativos de Enfermeria.
Trabajo grupal de la materia **Proyecto de Software 2026**.

Stack: PHP 8.2 + CodeIgniter 4.7 + MySQL (XAMPP) + HTML5 / CSS3 / JavaScript planos.

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
5. Abrir http://localhost:8080 en el navegador.

Usuarios de prueba que crea el script (solo para desarrollo):

| Email | Contrasena | Rol | Estado | Primer ingreso |
|-------|------------|-----|--------|----------------|
| admin@turnoplaner.local | admin123 | ADMIN | ACTIVO | No |
| enfermera@turnoplaner.local | enfermero123 | ENFERMERO | ACTIVO | No |
| nuevo@turnoplaner.local | enfermero123 | ENFERMERO | ACTIVO | Si, debe cambiar la contrasena al entrar |

Las cuentas las crea el administrador desde el sistema; no hay registro publico.

Alternativa con Apache de XAMPP: copiar la carpeta a `C:\xampp\htdocs\turnoplaner`
y entrar a http://localhost/turnoplaner/public (cambiar `app.baseURL` acorde).

## Estado actual (Sprint 1 - base)

- [x] Esqueleto CodeIgniter 4 configurado.
- [x] Layout base (barra superior, alertas, pie) en `app/Views/layouts/main.php`.
- [x] Pantalla de login en `/login` (HU-02, version base: **todavia no valida usuario ni contrasena**).
- [x] Filtro `auth` que protege las rutas privadas y redirige al login.
- [x] Pantalla de inicio en `/inicio` y cierre de sesion en `/logout`.
- [x] Base de datos `turnoplaner` y tabla `usuarios` con datos de prueba (TT-02) en `database/turnoplaner.sql`.
- [ ] Login real con `password_verify()` y control de roles.

## Donde esta cada cosa

| Que | Archivo |
|-----|---------|
| Rutas (URL -> controlador) | `app/Config/Routes.php` |
| Login / logout | `app/Controllers/Auth.php` |
| Pantalla de inicio | `app/Controllers/Home.php` |
| Filtro que exige sesion | `app/Filters/AuthFilter.php` (registrado en `app/Config/Filters.php`) |
| Layout comun | `app/Views/layouts/main.php` |
| Vista de login | `app/Views/auth/login.php` |
| Vista de inicio | `app/Views/home/dashboard.php` |
| Script de base de datos | `database/turnoplaner.sql` |
| Estilos | `public/css/app.css` |
| JavaScript | `public/js/app.js` |

## Convenciones del equipo

- Una rama por historia: `feature/HU-05-alta-enfermero`. Se integra a `main` por Pull Request revisado por otro integrante.
- Nombres de archivos, clases y variables en espanol sin tildes (como en el enunciado: ABMC, sector, turno).
- Toda pantalla nueva extiende `layouts/main` y usa las clases de `app.css`.
- Todo formulario valida en cliente (HTML5/JS) **y** en servidor (Validation de CI4).
