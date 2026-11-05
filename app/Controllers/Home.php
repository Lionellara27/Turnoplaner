<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Panel general del Administrador (HU-02: destino del rol ADMIN tras el login).
 * Un enfermero/a que entra a /inicio es enviado a su portal.
 */
class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (session()->get('rol') !== 'ADMIN') {
            return redirect()->to(base_url('mi-cronograma'));
        }

        $modelo = new UsuarioModel();

        return view('home/dashboard', [
            'titulo'      => 'Panel de gestión - TurnoPlaner',
            'nombre'      => (string) session()->get('nombre'),
            'activos'     => $modelo->where('rol', 'ENFERMERO')->countAllResults(),
            'pendientes'  => $modelo->where('rol', 'ENFERMERO')->where('debe_cambiar_pass', 1)->countAllResults(),
            'bajas'       => $modelo->onlyDeleted()->where('rol', 'ENFERMERO')->countAllResults(),
        ]);
    }
}
