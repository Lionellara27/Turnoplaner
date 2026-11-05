<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas de TurnoPlaner.
 *
 * Formato: $routes->get('url', 'Controlador::metodo');
 * No hay registro público: las cuentas las crea el Administrador (HU-05).
 *
 * @var RouteCollection $routes
 */

// Comodín para IDs: hasta 9 dígitos (un número más largo da 404 en vez de error 500).
$routes->addPlaceholder('id', '[0-9]{1,9}');

// --- Rutas públicas ---
$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::doLogin');
$routes->post('logout', 'Auth::logout'); // POST + CSRF: un link externo no puede cerrar la sesion

// Vista previa de maquetas (solo en desarrollo, nunca en producción).
if (ENVIRONMENT === 'development') {
    $routes->get('preview', 'TestController::preview', ['filter' => ['auth', 'role:ADMIN']]);
}

// --- Cualquier usuario logueado ---
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    // HU-01: primer ingreso (el filtro auth solo deja pasar a estas rutas si debe_cambiar_pass = 1)
    $routes->get('activar-cuenta', 'Auth::activar');
    $routes->post('activar-cuenta', 'Auth::doActivar');

    // HU-03: Mi perfil
    $routes->get('mi-perfil', 'Perfil::index');
    $routes->post('mi-perfil/datos', 'Perfil::actualizarDatos');
    $routes->post('mi-perfil/password', 'Perfil::cambiarPassword');

    // Panel del admin (un enfermero es redirigido a su portal)
    $routes->get('inicio', 'Home::index');
});

// --- Portal de enfermería ---
$routes->group('', ['filter' => ['auth', 'role:ENFERMERO']], static function (RouteCollection $routes): void {
    $routes->get('mi-cronograma', 'Cronograma::index');
});

// --- Administración ---
$routes->group('admin', ['filter' => ['auth', 'role:ADMIN']], static function (RouteCollection $routes): void {
    $routes->get('usuarios', 'Usuarios::index');                          // HU-06
    $routes->get('usuarios/crear', 'Usuarios::crear');                    // HU-05
    $routes->post('usuarios/crear', 'Usuarios::guardar');                 // HU-05
    $routes->get('usuarios/(:id)/editar', 'Usuarios::editar/$1');        // HU-07
    $routes->post('usuarios/(:id)/editar', 'Usuarios::actualizar/$1');   // HU-07
    $routes->post('usuarios/(:id)/restablecer', 'Usuarios::restablecer/$1'); // HU-04
    $routes->post('usuarios/baja/(:id)', 'Usuarios::darDeBaja/$1');      // HU-08
    $routes->post('usuarios/(:id)/reactivar', 'Usuarios::reactivar/$1'); // HU-08

    // HU-09 / HU-10: sectores
    $routes->get('sectores', 'Sectores::index');
    $routes->get('sectores/crear', 'Sectores::crear');
    $routes->post('sectores/crear', 'Sectores::guardar');
    $routes->get('sectores/(:id)/editar', 'Sectores::editar/$1');
    $routes->post('sectores/(:id)/editar', 'Sectores::actualizar/$1');
    $routes->post('sectores/(:id)/baja', 'Sectores::darDeBaja/$1');
    $routes->post('sectores/(:id)/reactivar', 'Sectores::reactivar/$1');

    // HU-11 / HU-12: tipos de turno
    $routes->get('tipos-turno', 'TiposTurno::index');
    $routes->get('tipos-turno/crear', 'TiposTurno::crear');
    $routes->post('tipos-turno/crear', 'TiposTurno::guardar');
    $routes->get('tipos-turno/(:id)/editar', 'TiposTurno::editar/$1');
    $routes->post('tipos-turno/(:id)/editar', 'TiposTurno::actualizar/$1');
    $routes->post('tipos-turno/(:id)/baja', 'TiposTurno::darDeBaja/$1');
    $routes->post('tipos-turno/(:id)/reactivar', 'TiposTurno::reactivar/$1');

    // HU-14: cuadrícula semanal
    $routes->get('planificador', 'Planificador::index');

    // HU-13: asignación de turnos
    $routes->get('asignaciones/crear', 'Asignaciones::crear');
    $routes->post('asignaciones/crear', 'Asignaciones::guardar');
    $routes->get('asignaciones/(:id)/editar', 'Asignaciones::editar/$1');
    $routes->post('asignaciones/(:id)/editar', 'Asignaciones::actualizar/$1');
    $routes->post('asignaciones/(:id)/eliminar', 'Asignaciones::eliminar/$1');
});
