<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Usuario;
use App\Models\UsuarioModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Gestión de Personal de Enfermería (rol ADMIN).
 *
 * - HU-04: restablecimiento de credenciales.
 * - HU-05: alta de enfermero/a con contraseña temporal.
 * - HU-06: listado con búsqueda, filtros y paginación.
 * - HU-07: edición de datos profesionales y de contratación.
 * - HU-08: baja lógica y reactivación.
 *
 * Patrón Thin Controller: valida la entrada y delega la persistencia al modelo.
 */
class Usuarios extends BaseController
{
    /** Estados válidos del filtro del listado. */
    private const FILTROS_ESTADO = ['ACTIVO', 'PENDIENTE', 'BAJA', 'TODOS'];

    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * HU-06: listado de personal con búsqueda por texto y filtros por estado y especialidad.
     */
    public function index(): string
    {
        $estado = strtoupper($this->textoGet('estado'));
        if (! in_array($estado, self::FILTROS_ESTADO, true)) {
            $estado = 'ACTIVO';
        }

        $especialidad = $this->textoGet('especialidad');
        if (! in_array($especialidad, UsuarioModel::ESPECIALIDADES, true)) {
            $especialidad = '';
        }

        $busqueda = mb_substr(trim($this->textoGet('q')), 0, 100);

        return view('admin/users/index', [
            'titulo'         => 'Personal de enfermería - TurnoPlaner',
            'usuarios'       => $this->usuarioModel->listarPersonal($estado, $especialidad, $busqueda),
            'pager'          => $this->usuarioModel->pager,
            'filtros'        => ['estado' => $estado, 'especialidad' => $especialidad, 'q' => $busqueda],
            'especialidades' => UsuarioModel::ESPECIALIDADES,
            'usuarioActual'  => (int) session()->get('usuario_id'),
        ]);
    }

    /**
     * HU-05: formulario de alta.
     */
    public function crear(): string
    {
        return view('admin/users/create', [
            'titulo'         => 'Alta de enfermero/a - TurnoPlaner',
            'especialidades' => UsuarioModel::ESPECIALIDADES,
        ]);
    }

    /**
     * HU-05: procesa el alta. El usuario nace ACTIVO, con contraseña temporal y debe_cambiar_pass = 1.
     */
    public function guardar(): RedirectResponse
    {
        $reglas = $this->reglasPersonal() + [
            'password' => [
                'rules'  => 'permit_empty|min_length[8]|max_length[72]',
                'errors' => [
                    'min_length' => 'La contraseña temporal debe tener al menos 8 caracteres.',
                    'max_length' => 'La contraseña temporal no puede superar los 72 caracteres.',
                ],
            ],
        ];

        $datos             = $this->datosRecortados();
        $datos['password'] = (string) $this->request->getPost('password'); // la clave no se recorta

        if (! $this->validateData($datos, $reglas)) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        $claveIngresada = $datos['password'];
        $claveTemporal  = $claveIngresada !== '' ? $claveIngresada : UsuarioModel::generarClaveTemporal();

        $usuario = new Usuario();
        $usuario->fill($this->datosPersonal($datos) + [
            'rol'               => 'ENFERMERO',
            'estado'            => 'ACTIVO', // La aprobación la otorga el Administrador al dar el alta.
            'debe_cambiar_pass' => 1,        // Obliga a definir contraseña propia en el primer ingreso.
        ]);
        $usuario->setPassword($claveTemporal);

        if (! $this->usuarioModel->save($usuario)) {
            return $this->volverConErrores($this->usuarioModel->errors());
        }

        return redirect()->to(base_url('admin/usuarios'))
            ->with('success', "Legajo de {$usuario->nombre} {$usuario->apellido} registrado. El usuario está listo para su primer acceso.")
            ->with('credenciales', $this->credencialesParaMostrar($usuario, $claveTemporal));
    }

    /**
     * HU-07: formulario de edición precompletado.
     */
    public function editar(int $id): string
    {
        return view('admin/users/edit', [
            'titulo'         => 'Editar enfermero/a - TurnoPlaner',
            'usuario'        => $this->buscarEnfermero($id),
            'especialidades' => UsuarioModel::ESPECIALIDADES,
        ]);
    }

    /**
     * HU-07: guarda la edición. No modifica la contraseña ni el historial de guardias.
     */
    public function actualizar(int $id): RedirectResponse
    {
        $usuario = $this->buscarEnfermero($id);

        $datos = $this->datosRecortados();

        if (! $this->validateData($datos, $this->reglasPersonal($id))) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        $usuario->fill($this->datosPersonal($datos));

        if (! $usuario->hasChanged()) {
            return redirect()->to(base_url('admin/usuarios'))
                ->with('info', "No había cambios para guardar en el legajo de {$usuario->getNombreCompleto()}.");
        }

        if (! $this->usuarioModel->save($usuario)) {
            return $this->volverConErrores($this->usuarioModel->errors());
        }

        return redirect()->to(base_url('admin/usuarios'))
            ->with('success', "Legajo de {$usuario->getNombreCompleto()} actualizado correctamente.");
    }

    /**
     * HU-04: genera una contraseña temporal nueva y obliga a cambiarla en el próximo ingreso.
     */
    public function restablecer(int $id): RedirectResponse
    {
        $usuario = $this->buscarEnfermero($id);

        if (! $usuario->estaActivo()) {
            return redirect()->back()->with('error', 'No se puede restablecer la clave de una cuenta dada de baja. Reactívela primero.');
        }

        $claveTemporal = UsuarioModel::generarClaveTemporal();
        $usuario->setPassword($claveTemporal);
        $usuario->debe_cambiar_pass = 1;

        if (! $this->usuarioModel->save($usuario)) {
            return redirect()->back()->with('errors', $this->usuarioModel->errors());
        }

        return redirect()->back()
            ->with('success', "Se restableció la contraseña de {$usuario->getNombreCompleto()}.")
            ->with('credenciales', $this->credencialesParaMostrar($usuario, $claveTemporal));
    }

    /**
     * HU-08: baja lógica (nunca DELETE físico).
     */
    public function darDeBaja(int $id): RedirectResponse
    {
        $usuario = $this->buscarEnfermero($id);

        if (! $usuario->estaActivo()) {
            return redirect()->back()->with('info', "{$usuario->getNombreCompleto()} ya estaba dado/a de baja.");
        }

        $futuros = (new \App\Models\AsignacionModel())
            ->where('usuario_id', $id)
            ->where('fecha >=', date('Y-m-d'))
            ->countAllResults();
        if ($futuros > 0) {
            return redirect()->back()->with('error', "No se puede dar de baja a {$usuario->getNombreCompleto()}: tiene {$futuros} "
                . ($futuros === 1 ? 'turno asignado' : 'turnos asignados') . ' desde hoy en adelante. Reasígnelos o elimínelos en el Planificador primero.');
        }

        if (! $this->usuarioModel->darDeBajaLogica($id)) {
            return redirect()->back()->with('error', 'No se pudo dar de baja. Intente nuevamente.');
        }

        return redirect()->back()->with('success', "{$usuario->getNombreCompleto()} fue dado/a de baja. Puede reactivarlo/a desde el filtro «Dados de baja».");
    }

    /**
     * HU-08: reactivación de una baja lógica.
     */
    public function reactivar(int $id): RedirectResponse
    {
        $usuario = $this->buscarEnfermero($id);

        if ($usuario->estaActivo()) {
            return redirect()->back()->with('info', "{$usuario->getNombreCompleto()} ya estaba activo/a.");
        }

        $this->usuarioModel->reactivar($id);

        return redirect()->back()->with('success', "{$usuario->getNombreCompleto()} fue reactivado/a y puede volver a ingresar.");
    }

    /**
     * Reglas compartidas por el alta (HU-05) y la edición (HU-07).
     * En edición, la unicidad ignora al propio registro.
     *
     * @return array<string, array<string, mixed>>
     */
    private function reglasPersonal(?int $id = null): array
    {
        $ignorar      = $id !== null ? ",id,{$id}" : '';
        $especialidad = implode(',', UsuarioModel::ESPECIALIDADES);

        return [
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
                'rules'  => "required|regex_match[/^[0-9]{7,10}$/]|is_unique[usuarios.dni{$ignorar}]",
                'errors' => [
                    'required'    => 'El DNI es obligatorio.',
                    'regex_match' => 'El DNI debe tener entre 7 y 10 números, sin puntos.',
                    'is_unique'   => 'El DNI ingresado ya está registrado para otro usuario.',
                ],
            ],
            'matricula' => [
                'rules'  => "required|regex_match[/^[a-zA-Z0-9\\s\\-\\/]+$/]|max_length[20]|is_unique[usuarios.matricula{$ignorar}]",
                'errors' => [
                    'required'    => 'La matrícula profesional es obligatoria.',
                    'regex_match' => 'La matrícula solo puede contener letras, números, espacios, guiones y barras.',
                    'max_length'  => 'La matrícula no puede superar los 20 caracteres.',
                    'is_unique'   => 'La matrícula ingresada ya está registrada para otro profesional.',
                ],
            ],
            'email' => [
                'rules'  => "required|valid_email|max_length[150]|is_unique[usuarios.email{$ignorar}]",
                'errors' => [
                    'required'    => 'El correo electrónico es obligatorio.',
                    'valid_email' => 'Ingrese un correo electrónico válido.',
                    'max_length'  => 'El correo no puede superar los 150 caracteres.',
                    'is_unique'   => 'El correo electrónico ingresado ya está registrado para otro usuario.',
                ],
            ],
            'telefono' => $this->reglaTelefono(false),
            'especialidad' => [
                'rules'  => "required|in_list[{$especialidad}]",
                'errors' => [
                    'required' => 'Seleccione la especialidad de enfermería.',
                    'in_list'  => 'Seleccione una especialidad de la lista.',
                ],
            ],
            'carga_horaria_max' => [
                'rules'  => 'required|is_natural_no_zero|less_than_equal_to[80]',
                'errors' => [
                    'required'           => 'La carga horaria semanal máxima es obligatoria.',
                    'is_natural_no_zero' => 'La carga horaria debe ser un número entero mayor a cero (ej. 36 o 40).',
                    'less_than_equal_to' => 'La carga horaria semanal no puede superar las 80 horas.',
                ],
            ],
        ];
    }

    /**
     * Datos del legajo, normalizados a partir del POST ya recortado y validado.
     * Los valores van como string, igual que los devuelve la base, para que
     * Entity::hasChanged() detecte bien cuando no hubo cambios.
     *
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed>
     */
    private function datosPersonal(array $datos): array
    {
        $texto = static fn (string $campo): string => (string) ($datos[$campo] ?? '');

        return [
            'nombre'            => $texto('nombre'),
            'apellido'          => $texto('apellido'),
            'dni'               => $texto('dni'),
            'matricula'         => mb_strtoupper($texto('matricula')),
            'email'             => mb_strtolower($texto('email')),
            'telefono'          => $texto('telefono') !== '' ? $texto('telefono') : null,
            'especialidad'      => $texto('especialidad'),
            'carga_horaria_max' => (string) (int) $texto('carga_horaria_max'),
        ];
    }

    /**
     * Busca un enfermero/a (incluye bajas). Los administradores no se gestionan desde esta pantalla.
     */
    private function buscarEnfermero(int $id): Usuario
    {
        $usuario = $this->usuarioModel->buscarIncluyendoBajas($id);

        if ($usuario === null || ! $usuario->esEnfermero()) {
            throw PageNotFoundException::forPageNotFound('El enfermero/a solicitado no existe.');
        }

        return $usuario;
    }

    /**
     * Datos que se muestran una sola vez al admin para entregarle al enfermero/a.
     *
     * @return array<string, string>
     */
    private function credencialesParaMostrar(Usuario $usuario, string $claveTemporal): array
    {
        return [
            'nombre'  => $usuario->getNombreCompleto(),
            'usuario' => (string) $usuario->email,
            'dni'     => (string) $usuario->dni,
            'clave'   => $claveTemporal,
        ];
    }
}
