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

// --- Rutas privadas (protegidas por el filtro "auth") ---
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('inicio', 'Home::index');      // pantalla principal del sistema
});
