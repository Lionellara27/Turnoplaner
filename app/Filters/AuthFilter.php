<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro de autenticacion.
 *
 * Se ejecuta ANTES de cualquier ruta marcada con 'filter' => 'auth'.
 * Si el usuario no tiene sesion iniciada, lo manda al login.
 * Mas adelante aca mismo se va a controlar el ROL (admin / enfermero).
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('logueado')) {
            $session->setFlashdata('error', 'Tenes que iniciar sesion para entrar.');

            return redirect()->to('/login');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No hacemos nada despues de la respuesta.
    }
}
