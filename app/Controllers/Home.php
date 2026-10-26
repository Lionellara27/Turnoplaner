<?php

namespace App\Controllers;

/**
 * Pantalla principal del sistema (despues del login).
 * Esta protegida por el filtro "auth" en Routes.php.
 */
class Home extends BaseController
{
    public function index()
    {
        return view('home/dashboard', [
            'titulo'  => 'Inicio',
            'usuario' => session()->get('usuario'),
            'rol'     => session()->get('rol'),
        ]);
    }
}
