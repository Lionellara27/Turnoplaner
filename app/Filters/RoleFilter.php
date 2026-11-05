<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Control de acceso basado en roles (HU-02).
 * Uso en rutas: ['filter' => ['auth', 'role:ADMIN']].
 * Siempre va DESPUÉS de 'auth', que ya validó la sesión.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session    = session();
        $rolUsuario = (string) $session->get('rol');

        if (! $session->get('logueado') || $rolUsuario === '') {
            return redirect()->to(base_url('login'));
        }

        $rolesPermitidos = $arguments ?? [];

        if ($rolesPermitidos !== [] && ! in_array($rolUsuario, $rolesPermitidos, true)) {
            $destino = $rolUsuario === 'ADMIN' ? 'inicio' : 'mi-cronograma';

            return redirect()->to(base_url($destino))
                ->with('error', 'Acceso denegado: no posee los permisos requeridos para esa sección.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Sin acciones posteriores.
    }
}
