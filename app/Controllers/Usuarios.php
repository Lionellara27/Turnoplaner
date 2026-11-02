<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UsuarioModel;
use App\Entities\Usuario;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlador de Gestión de Personal / Usuarios (HU-05 a HU-08).
 * Diseñado bajo el patrón Thin Controller de CI4.
 */
class Usuarios extends BaseController
{
    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Listado general de personal de enfermería.
     */
    public function index(): string
    {
        $data = [
            'titulo'   => 'Gestión de Personal de Enfermería - TurnoPlaner',
            'usuarios' => $this->usuarioModel->obtenerActivosConFiltros(
                estado: (string) $this->request->getGet('estado'),
                busqueda: (string) $this->request->getGet('q')
            ),
        ];

        return view('admin/users/index', $data);
    }

    /**
     * Muestra el formulario de alta administrativa de enfermero/a (HU-05).
     */
    public function crear(): string
    {
        return view('admin/users/create', [
            'titulo' => 'Alta de Enfermero/a - TurnoPlaner',
        ]);
    }

    /**
     * Procesa la creación de un nuevo enfermero/a por el Administrador (HU-05).
     */
    public function guardar(): ResponseInterface
    {
        // 1. Reglas de validación estrictas (Criterios de Aceptación HU-05)
        $rules = [
            'nombre' => [
                'rules'  => 'required|min_length[2]|max_length[100]',
                'errors' => [
                    'required'   => 'El nombre es obligatorio.',
                    'min_length' => 'El nombre debe contener al menos 2 caracteres.',
                    'max_length' => 'El nombre no puede superar los 100 caracteres.',
                ],
            ],
            'apellido' => [
                'rules'  => 'required|min_length[2]|max_length[100]',
                'errors' => [
                    'required'   => 'El apellido es obligatorio.',
                    'min_length' => 'El apellido debe contener al menos 2 caracteres.',
                    'max_length' => 'El apellido no puede superar los 100 caracteres.',
                ],
            ],
            'dni' => [
                'rules'  => 'required|numeric|min_length[7]|max_length[10]|is_unique[usuarios.dni]',
                'errors' => [
                    'required'   => 'El DNI es un campo obligatorio.',
                    'numeric'    => 'El DNI solo debe contener caracteres numéricos sin puntos.',
                    'min_length' => 'El DNI debe contener al menos 7 dígitos.',
                    'max_length' => 'El DNI no puede superar los 10 dígitos.',
                    'is_unique'  => 'El DNI ingresado ya se encuentra registrado en el sistema para otro usuario.',
                ],
            ],
            'matricula' => [
                'rules'  => 'required|regex_match[/^[a-zA-Z0-9\s\-\/]+$/]|max_length[20]|is_unique[usuarios.matricula]',
                'errors' => [
                    'required'    => 'La matrícula profesional es obligatoria.',
                    'regex_match' => 'La matrícula solo puede contener letras, números, espacios, guiones y barras.',
                    'max_length'  => 'La matrícula no puede superar los 20 caracteres.',
                    'is_unique'   => 'La matrícula profesional ingresada ya se encuentra registrada para otro profesional.',
                ],
            ],
            'email' => [
                'rules'  => 'required|valid_email|max_length[150]|is_unique[usuarios.email]',
                'errors' => [
                    'required'    => 'El correo electrónico es obligatorio.',
                    'valid_email' => 'Debe proporcionar una dirección de correo electrónico válida.',
                    'max_length'  => 'El correo no puede exceder los 150 caracteres.',
                    'is_unique'   => 'El correo electrónico ingresado ya se encuentra registrado en el sistema.',
                ],
            ],
            'especialidad' => [
                'rules'  => 'required|string|max_length[100]',
                'errors' => [
                    'required' => 'Debe seleccionar o indicar la especialidad de enfermería.',
                ],
            ],
            'carga_horaria_max' => [
                'rules'  => 'required|is_natural_no_zero',
                'errors' => [
                    'required'           => 'La carga horaria semanal máxima contratada es obligatoria.',
                    'is_natural_no_zero' => 'La carga horaria semanal debe ser un valor numérico entero mayor a cero (ej. 36 o 40).',
                ],
            ],
            'telefono' => [
                'rules'  => 'permit_empty|string|max_length[30]',
                'errors' => [
                    'max_length' => 'El teléfono no puede exceder los 30 caracteres.',
                ],
            ],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // 2. Generación de credenciales temporales iniciales (HU-05)
        $passwordPost = (string) $this->request->getPost('password');
        $claveTemporal = $passwordPost !== '' ? $passwordPost : 'Tp' . bin2hex(random_bytes(3)) . '!';

        // 3. Creación de la Entidad de Dominio
        $usuario = new Usuario();
        $usuario->fill([
            'nombre'            => trim((string) $this->request->getPost('nombre')),
            'apellido'          => trim((string) $this->request->getPost('apellido')),
            'dni'               => trim((string) $this->request->getPost('dni')),
            'matricula'         => trim((string) $this->request->getPost('matricula')),
            'email'             => strtolower(trim((string) $this->request->getPost('email'))),
            'telefono'          => trim((string) $this->request->getPost('telefono')) ?: null,
            'especialidad'      => trim((string) $this->request->getPost('especialidad')),
            'carga_horaria_max' => (int) $this->request->getPost('carga_horaria_max'),
            'rol'               => 'ENFERMERO',
            'estado'            => 'ACTIVO', // Aprobado directamente por el Administrador
            'debe_cambiar_pass' => 1,        // Obliga al cambio en el primer ingreso
        ]);

        // Hashea la contraseña con password_hash() vía Mutator
        $usuario->setPassword($claveTemporal);

        // 4. Persistencia en la Base de Datos
        if (! $this->usuarioModel->save($usuario)) {
            return redirect()->back()->withInput()->with('errors', $this->usuarioModel->errors());
        }

        // 5. Confirmación visual con legajo y clave provisoria
        $mensajeExito = "Legajo de enfermero/a {$usuario->nombre} {$usuario->apellido} (Matrícula: {$usuario->matricula}) registrado con éxito en estado Activo. Clave temporal asignada: {$claveTemporal}. El usuario está listo para su primer acceso.";

        return redirect()->to(base_url('admin/usuarios/crear'))
            ->with('success', $mensajeExito)
            ->with('ok', $mensajeExito);
    }

    /**
     * Baja lógica estricta (HU-08 / DoD).
     */
    public function darDeBaja(int $id): ResponseInterface
    {
        $usuario = $this->usuarioModel->find($id);

        if (! $usuario) {
            return redirect()->back()->with('error', 'Usuario no encontrado.');
        }

        $this->usuarioModel->darDeBajaLogica($id);

        return redirect()->back()->with('success', "El usuario {$usuario->getNombreCompleto()} fue dado de baja del plantel activo.");
    }
}
