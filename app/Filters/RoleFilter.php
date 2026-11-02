<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $rolUsuario = $session->get('rol');

        if (! $session->get('logueado') || empty($rolUsuario)) {
            return redirect()->to(base_url('login'));
        }

        // Argumentos pasados en la ruta, ej: ['ADMIN'] o ['ADMIN', 'ENFERMERO']
        $rolesPermitidos = $arguments ?? [];

        if (! empty($rolesPermitidos) && ! in_array($rolUsuario, $rolesPermitidos, true)) {
            $session->setFlashdata('error', 'Acceso denegado: No posee los permisos requeridos para esta sección.');
            
            return ($rolUsuario === 'ADMIN')
                ? redirect()->to(base_url('inicio'))
                : redirect()->to(base_url('inicio'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action required after
    }
}

