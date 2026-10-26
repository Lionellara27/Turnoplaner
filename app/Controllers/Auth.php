<?php

namespace App\Controllers;

/**
 * Controlador de autenticacion (HU-02: Login y cierre de sesion).
 *
 * Decision del grupo (confirmada por la catedra el 26/10/2026): NO hay
 * registro publico. Las cuentas las crea el Administrador (HU-05) y le
 * entrega usuario y contrasena temporal al enfermero/a. En el primer
 * ingreso el sistema le pide cambiar la contrasena (HU-01).
 *
 * SPRINT 1 - version base: el login todavia NO valida usuario ni contrasena.
 * Cualquier dato que se mande entra al sistema. Se reemplaza el TODO de
 * doLogin() por la consulta real al modelo y password_verify().
 */
class Auth extends BaseController
{
    /**
     * GET /login  -> muestra el formulario.
     * Si ya hay sesion, manda directo al inicio.
     */
    public function login()
    {
        if (session()->get('logueado')) {
            return redirect()->to('/inicio');
        }

        return view('auth/login', ['titulo' => 'Iniciar sesion']);
    }

    /**
     * POST /login -> procesa el formulario.
     */
    public function doLogin()
    {
        $usuario = trim((string) $this->request->getPost('usuario'));

        // TODO (HU-02 completa): buscar el usuario en la BD, verificar la
        // contrasena con password_verify() y chequear estado ACTIVO.

        // Datos de sesion que va a usar el resto del sistema.
        session()->set([
            'logueado' => true,
            'usuario'  => $usuario !== '' ? $usuario : 'invitado',
            'rol'      => 'ADMIN', // provisorio hasta tener usuarios reales
        ]);

        return redirect()->to('/inicio');
    }

    /**
     * GET /logout -> destruye la sesion y vuelve al login.
     */
    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login')->with('ok', 'Cerraste sesion correctamente.');
    }
}
