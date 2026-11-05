<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Usuario;
use App\Libraries\SesionUsuario;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Autenticación de TurnoPlaner.
 *
 * - HU-02: login con email o DNI, control de estado y redirección por rol; cierre de sesión.
 * - HU-01: activación de cuenta en el primer ingreso (o tras un restablecimiento de clave).
 *
 * No existe registro público: las cuentas las crea el Administrador (HU-05)
 * con una contraseña temporal y la marca debe_cambiar_pass = 1.
 */
class Auth extends BaseController
{
    /** Intentos de login permitidos por cuenta + IP en la ventana de tiempo. */
    private const INTENTOS_POR_CUENTA = 5;

    /** Intentos de login permitidos por IP (todas las cuentas) en la ventana de tiempo. */
    private const INTENTOS_POR_IP = 30;

    /** Ventana de tiempo para los límites de intentos, en segundos. */
    private const VENTANA_LOGIN = 60;

    /**
     * Hash de relleno: si el usuario no existe se verifica contra este, para que la respuesta
     * tarde lo mismo y no se pueda averiguar qué cuentas existen midiendo el tiempo.
     */
    private const HASH_RELLENO = '$2y$10$anNSqYPlv1W3q8HSe1E1fORfpZ9wIby4dgNulp1yP6OWEtHsnAa0C';

    /**
     * GET /login: muestra el formulario. Si ya hay sesión, va al inicio del rol.
     */
    public function login(): string|RedirectResponse
    {
        if (session()->get('logueado')) {
            return redirect()->to($this->destinoSegunRol((string) session()->get('rol')));
        }

        return view('auth/login', [
            'titulo'         => 'Iniciar sesión - TurnoPlaner',
            'alertasEnVista' => true, // las alertas van dentro de la tarjeta angosta del login
        ]);
    }

    /**
     * POST /login: valida credenciales contra la base.
     */
    public function doLogin(): RedirectResponse
    {
        $datos         = $this->datosRecortados();
        $identificador = UsuarioModel::normalizarIdentificador((string) ($datos['identificador'] ?? ''));

        // Límite de intentos: por cuenta + IP (en un hospital muchas PCs comparten IP) y por IP en general.
        $throttler = service('throttler');
        $ip        = (string) $this->request->getIPAddress();
        if (
            $throttler->check('login-ip-' . md5($ip), self::INTENTOS_POR_IP, self::VENTANA_LOGIN) === false
            || $throttler->check('login-' . md5($ip . '|' . $identificador), self::INTENTOS_POR_CUENTA, self::VENTANA_LOGIN) === false
        ) {
            return $this->volverConErrores(['Demasiados intentos de ingreso. Espere un minuto y vuelva a intentar.'], null, 'error');
        }

        $reglas = [
            'identificador' => [
                'rules'  => 'required|max_length[150]',
                'errors' => [
                    'required'   => 'Ingrese su email o DNI.',
                    'max_length' => 'El usuario ingresado es demasiado largo.',
                ],
            ],
            'password' => [
                'rules'  => 'required|max_length[72]',
                'errors' => [
                    'required'   => 'Ingrese su contraseña.',
                    'max_length' => 'La contraseña ingresada es demasiado larga.',
                ],
            ],
        ];

        if (! $this->validateData($datos, $reglas)) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        $password = (string) $this->request->getPost('password'); // la contraseña NO se recorta
        $usuario  = (new UsuarioModel())->buscarPorIdentificador($identificador);
        $hash     = $usuario !== null ? (string) $usuario->password_hash : self::HASH_RELLENO;

        // Mismo mensaje y mismo tiempo si no existe o si la clave es incorrecta.
        if (! password_verify($password, $hash) || $usuario === null) {
            return $this->volverConErrores(['Usuario o contraseña incorrectos.'], null, 'error');
        }

        if (! $usuario->estaActivo()) {
            return $this->volverConErrores(['Cuenta inactiva o dada de baja. Contacte a la administración.'], null, 'error');
        }

        SesionUsuario::iniciar($usuario);

        if ($usuario->debeCambiarPassword()) {
            return redirect()->to(base_url('activar-cuenta'));
        }

        return redirect()->to($this->destinoSegunRol((string) $usuario->rol));
    }

    /**
     * POST /logout: cierra la sesión y vuelve al login.
     */
    public function logout(): RedirectResponse
    {
        SesionUsuario::cerrar();

        return redirect()->to(base_url('login'))->with('success', 'Sesión cerrada correctamente.');
    }

    /**
     * GET /activar-cuenta: formulario de primer ingreso (HU-01).
     */
    public function activar(): string|RedirectResponse
    {
        $usuario = $this->usuarioLogueado();

        if (! $usuario->debeCambiarPassword()) {
            return redirect()->to($this->destinoSegunRol((string) $usuario->rol));
        }

        return view('auth/activar', [
            'titulo'  => 'Activar cuenta - TurnoPlaner',
            'usuario' => $usuario,
        ]);
    }

    /**
     * POST /activar-cuenta: define la contraseña propia y completa los datos de contacto (HU-01).
     */
    public function doActivar(): RedirectResponse
    {
        $usuario = $this->usuarioLogueado();

        if (! $usuario->debeCambiarPassword()) {
            return redirect()->to($this->destinoSegunRol((string) $usuario->rol));
        }

        $datos  = $this->datosRecortados();
        $reglas = $this->reglasNuevaPassword() + [
            'telefono'  => $this->reglaTelefono(true),
            'domicilio' => $this->reglaDomicilio(true),
        ];

        // Las contraseñas se validan tal cual se escribieron (sin recortar).
        $datos['password']         = (string) $this->request->getPost('password');
        $datos['password_confirm'] = (string) $this->request->getPost('password_confirm');

        if (! $this->validateData($datos, $reglas)) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        if (password_verify($datos['password'], (string) $usuario->password_hash)) {
            return $this->volverConErrores(['password' => 'La nueva contraseña debe ser distinta de la contraseña temporal.']);
        }

        $usuario->setPassword($datos['password']);
        $usuario->telefono          = $datos['telefono'];
        $usuario->domicilio         = $datos['domicilio'];
        $usuario->debe_cambiar_pass = 0;

        $modelo = new UsuarioModel();
        if (! $modelo->save($usuario)) {
            return $this->volverConErrores($modelo->errors());
        }

        // Nueva sesión con la huella de la clave nueva: cualquier otra sesión abierta se cierra.
        SesionUsuario::renovarCredenciales($usuario);

        return redirect()->to($this->destinoSegunRol((string) $usuario->rol))
            ->with('success', "¡Bienvenido/a, {$usuario->nombre}! Su cuenta quedó activada.");
    }

    /**
     * Usuario de la sesión actual. Las rutas que lo usan están protegidas por el filtro "auth".
     */
    private function usuarioLogueado(): Usuario
    {
        $usuario = (new UsuarioModel())->find((int) session()->get('usuario_id'));

        if ($usuario === null) {
            throw new \RuntimeException('Sesión sin usuario válido.');
        }

        return $usuario;
    }

    /**
     * Pantalla de inicio de cada rol (HU-02).
     */
    private function destinoSegunRol(string $rol): string
    {
        return $rol === 'ADMIN' ? base_url('inicio') : base_url('mi-cronograma');
    }
}
