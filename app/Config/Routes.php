<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas de TurnoPlaner.
 *
 * Cada linea conecta una URL con un metodo de un controlador.
 * Formato: $routes->get('url', 'Controlador::metodo');
 *
 * @var RouteCollection $routes
 */

// --- Rutas publicas (no hace falta estar logueado) ---
$routes->get('/', 'Auth::login');               // la raiz muestra el login
$routes->get('login', 'Auth::login');           // GET  -> muestra el formulario
$routes->post('login', 'Auth::doLogin');        // POST -> procesa el formulario
$routes->get('logout', 'Auth::logout');         // cierra la sesion

// Para testing / preview visual
$routes->get('preview', 'TestController::preview');

// --- Rutas privadas (protegidas por el filtro "auth") ---
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('inicio', 'Home::index');      // pantalla principal del sistema
});

// --- Rutas de Administración (Filtro auth + role:ADMIN) ---
$routes->group('admin', ['filter' => ['auth', 'role:ADMIN']], static function (RouteCollection $routes): void {
    // ABMC de Personal de Enfermería (HU-05 a HU-08)
    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios/crear', 'Usuarios::crear');
    $routes->post('usuarios/crear', 'Usuarios::guardar');
    $routes->post('usuarios/guardar', 'Usuarios::guardar');
    $routes->post('usuarios/baja/(:num)', 'Usuarios::darDeBaja/$1');
});

// Alias compatible para maquetaciones anteriores
$routes->post('registro/guardar', 'Usuarios::guardar');
