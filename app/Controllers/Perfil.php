<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Usuario;
use App\Libraries\SesionUsuario;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * HU-03: Gestión de Mi Perfil.
 *
 * Cualquier usuario logueado puede editar su teléfono, email y domicilio, y cambiar
 * su contraseña ingresando la actual. DNI, matrícula, especialidad y carga horaria
 * son datos administrativos: se muestran en modo lectura y solo los cambia el Administrador.
 */
class Perfil extends BaseController
{
    /** Intentos de "contraseña actual" permitidos por minuto (frena la fuerza bruta con una sesión robada). */
    private const INTENTOS_PASSWORD = 5;

    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * GET /mi-perfil
     */
    public function index(): string
    {
        return view('perfil/index', [
            'titulo'  => 'Mi perfil - TurnoPlaner',
            'usuario' => $this->usuarioLogueado(),
        ]);
    }

    /**
     * POST /mi-perfil/datos: actualiza los datos de contacto.
     */
    public function actualizarDatos(): RedirectResponse
    {
        $usuario = $this->usuarioLogueado();
        $datos   = $this->datosRecortados();

        // Para el enfermero/a, teléfono y domicilio son obligatorios (igual que en la activación, HU-01).
        $obligatorios = $usuario->esEnfermero();

        $reglas = [
            'email' => [
                'rules'  => "required|valid_email|max_length[150]|is_unique[usuarios.email,id,{$usuario->id}]",
                'errors' => [
                    'required'    => 'El correo electrónico es obligatorio.',
                    'valid_email' => 'Ingrese un correo electrónico válido.',
                    'max_length'  => 'El correo no puede superar los 150 caracteres.',
                    'is_unique'   => 'Ese correo electrónico ya pertenece a otro usuario.',
                ],
            ],
            'telefono'  => $this->reglaTelefono($obligatorios),
            'domicilio' => $this->reglaDomicilio($obligatorios),
        ];

        if (! $this->validateData($datos, $reglas)) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        $usuario->email     = mb_strtolower((string) $datos['email']);
        $usuario->telefono  = ($datos['telefono'] ?? '') !== '' ? $datos['telefono'] : null;
        $usuario->domicilio = ($datos['domicilio'] ?? '') !== '' ? $datos['domicilio'] : null;

        if (! $usuario->hasChanged()) {
            return redirect()->to(base_url('mi-perfil'))->with('info', 'No había cambios para guardar.');
        }

        if (! $this->usuarioModel->save($usuario)) {
            return $this->volverConErrores($this->usuarioModel->errors());
        }

        session()->set('email', $usuario->email);

        return redirect()->to(base_url('mi-perfil'))->with('success', 'Sus datos de contacto se actualizaron correctamente.');
    }

    /**
     * POST /mi-perfil/password: cambia la contraseña verificando la actual.
     */
    public function cambiarPassword(): RedirectResponse
    {
        $usuario = $this->usuarioLogueado();
        $destino = base_url('mi-perfil') . '#seguridad';

        if (service('throttler')->check('cambio-clave-' . $usuario->id, self::INTENTOS_PASSWORD, MINUTE) === false) {
            return $this->volverConErrores(['Demasiados intentos. Espere un minuto y vuelva a intentar.'], $destino, 'error');
        }

        $reglas = [
            'password_actual' => [
                'rules'  => 'required',
                'errors' => ['required' => 'Ingrese su contraseña actual.'],
            ],
        ] + $this->reglasNuevaPassword();

        if (! $this->validate($reglas)) {
            return $this->volverConErrores($this->validator->getErrors(), $destino);
        }

        if (! password_verify((string) $this->request->getPost('password_actual'), (string) $usuario->password_hash)) {
            return $this->volverConErrores(['password_actual' => 'La contraseña actual no es correcta.'], $destino);
        }

        $nueva = (string) $this->request->getPost('password');
        if (password_verify($nueva, (string) $usuario->password_hash)) {
            return $this->volverConErrores(['password' => 'La nueva contraseña debe ser distinta de la actual.'], $destino);
        }

        $usuario->setPassword($nueva);

        if (! $this->usuarioModel->save($usuario)) {
            return $this->volverConErrores($this->usuarioModel->errors(), $destino);
        }

        // Esta sesión sigue; las demás sesiones abiertas de la cuenta se cierran (huella de credenciales).
        SesionUsuario::renovarCredenciales($usuario);

        return redirect()->to(base_url('mi-perfil'))
            ->with('success', 'Su contraseña se cambió correctamente. Si tenía la sesión abierta en otro equipo, allí se cerró.');
    }

    private function usuarioLogueado(): Usuario
    {
        $usuario = $this->usuarioModel->find((int) session()->get('usuario_id'));

        if ($usuario === null) {
            throw new \RuntimeException('Sesión sin usuario válido.');
        }

        return $usuario;
    }
}
