<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use App\Entities\Usuario;

class UsuarioModel extends Model
{
    /**
     * Especialidades de enfermería disponibles (HU-05, HU-06, HU-07).
     * Única fuente para los selects de alta, edición y filtros.
     */
    public const ESPECIALIDADES = [
        'Enfermería General',
        'Cuidados Críticos / Terapia Intensiva (UTI)',
        'Pediatría y Neonatología',
        'Guardia y Emergencias',
        'Quirófano / Centro Quirúrgico',
        'Salud Mental',
        'Oncología',
    ];

    /** Cantidad de filas por página en el listado de personal (HU-06). */
    public const POR_PAGINA = 10;

    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Usuario::class;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nombre',
        'apellido',
        'dni',
        'matricula',
        'email',
        'telefono',
        'domicilio',
        'password_hash',
        'rol',
        'especialidad',
        'carga_horaria_max',
        'estado',
        'debe_cambiar_pass',
    ];

    // Control de fechas y auditoría
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Reglas de validación internas del modelo (segunda barrera, la primera está en el controlador)
    protected $validationRules = [
        'id'                => 'permit_empty|is_natural_no_zero',
        'nombre'            => 'required|min_length[2]|max_length[100]',
        'apellido'          => 'required|min_length[2]|max_length[100]',
        'dni'               => 'required|regex_match[/^[0-9]{7,10}$/]|is_unique[usuarios.dni,id,{id}]',
        'matricula'         => 'permit_empty|regex_match[/^[a-zA-Z0-9\s\-\/]+$/]|max_length[20]|is_unique[usuarios.matricula,id,{id}]',
        'email'             => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,{id}]',
        'telefono'          => 'permit_empty|max_length[30]',
        'domicilio'         => 'permit_empty|max_length[150]',
        'carga_horaria_max' => 'permit_empty|is_natural_no_zero|less_than_equal_to[80]',
        'rol'               => 'permit_empty|in_list[ADMIN,ENFERMERO]',
        'estado'            => 'permit_empty|in_list[ACTIVO,BAJA]',
    ];

    protected $validationMessages = [
        'dni' => [
            'required'    => 'El número de DNI es obligatorio.',
            'regex_match' => 'El DNI debe tener entre 7 y 10 números, sin puntos.',
            'is_unique'   => 'Ya existe un usuario registrado con el número de DNI ingresado.',
        ],
        'matricula' => [
            'regex_match' => 'La matrícula solo puede contener letras, números, espacios, guiones y barras.',
            'is_unique'   => 'Ya existe un/a profesional registrado/a con esa matrícula.',
        ],
        'email' => [
            'required'    => 'El correo electrónico es obligatorio.',
            'valid_email' => 'Debe ingresar un correo electrónico válido.',
            'is_unique'   => 'Ya existe un usuario registrado con ese correo electrónico.',
        ],
        'nombre' => [
            'required'   => 'El nombre es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            'max_length' => 'El nombre no puede exceder 100 caracteres.',
        ],
        'apellido' => [
            'required'   => 'El apellido es obligatorio.',
            'min_length' => 'El apellido debe tener al menos 2 caracteres.',
            'max_length' => 'El apellido no puede exceder 100 caracteres.',
        ],
        'carga_horaria_max' => [
            'is_natural_no_zero' => 'La carga horaria máxima semanal debe ser un número entero mayor a cero.',
            'less_than_equal_to' => 'La carga horaria máxima semanal no puede superar las 80 horas.',
        ],
    ];

    /**
     * Baja lógica estricta: Actualiza estado a BAJA y ejecuta soft delete (deleted_at = NOW()).
     * PROHIBIDO EL DELETE FÍSICO.
     */
    public function darDeBajaLogica(int $id): bool
    {
        // Las dos escrituras van juntas: o se aplican ambas o ninguna.
        $this->db->transStart();
        $this->update($id, ['estado' => 'BAJA']);
        $this->delete($id);
        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Reactivación de un usuario dado de baja (HU-08).
     * deleted_at no está en $allowedFields, por eso se limpia con el Query Builder.
     */
    public function reactivar(int $id): bool
    {
        return $this->builder()
            ->where($this->primaryKey, $id)
            ->update([
                'estado'     => 'ACTIVO',
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Listado de personal con búsqueda, filtros y paginación (HU-06).
     *
     * @param string $estado       'ACTIVO' (por defecto), 'PENDIENTE' (activos sin primer ingreso), 'BAJA' o 'TODOS'.
     * @param string $especialidad Una de self::ESPECIALIDADES o vacío.
     * @param string $busqueda     Texto libre: nombre, apellido, DNI o matrícula.
     *
     * @return Usuario[]
     */
    public function listarPersonal(string $estado = 'ACTIVO', string $especialidad = '', string $busqueda = ''): array
    {
        // Las bajas tienen deleted_at, así que para verlas hay que incluir los registros "borrados" lógicamente.
        if ($estado === 'BAJA') {
            $this->onlyDeleted();
        } elseif ($estado === 'TODOS') {
            $this->withDeleted();
        } elseif ($estado === 'PENDIENTE') {
            $this->where('debe_cambiar_pass', 1);
        }

        if ($especialidad !== '') {
            $this->where('especialidad', $especialidad);
        }

        // Cada palabra debe aparecer en algún campo: "gomez maria" encuentra a "María Gómez".
        $palabras = preg_split('/\s+/', trim($busqueda), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_slice($palabras, 0, 5) as $palabra) {
            $this->groupStart()
                ->like('nombre', $palabra)
                ->orLike('apellido', $palabra)
                ->orLike('dni', $palabra)
                ->orLike('matricula', $palabra)
            ->groupEnd();
        }

        return $this->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->paginate(self::POR_PAGINA);
    }

    /**
     * Busca un usuario por DNI o correo para autenticación (HU-02).
     * Incluye las bajas lógicas para poder informar "cuenta inactiva" en lugar de "credenciales inválidas".
     */
    public function buscarPorIdentificador(string $identificador): ?Usuario
    {
        $identificador = self::normalizarIdentificador($identificador);

        if ($identificador === '') {
            return null;
        }

        return $this->withDeleted()
            ->groupStart()
                ->where('email', $identificador)
                ->orWhere('dni', $identificador)
            ->groupEnd()
            ->first();
    }

    /**
     * Normaliza lo que el usuario escribe en "Email o DNI":
     * un DNI con puntos o espacios ("30.123.456") queda "30123456"; un email queda en minúsculas.
     */
    public static function normalizarIdentificador(string $identificador): string
    {
        $identificador = trim($identificador);

        if (preg_match('/^[0-9.\s]+$/', $identificador) === 1) {
            return (string) preg_replace('/[.\s]/', '', $identificador);
        }

        return mb_strtolower($identificador);
    }

    /**
     * Enfermeros/as activos, para asignarles turnos (HU-13). Ordenados por apellido.
     *
     * @return list<Usuario>
     */
    public function enfermerosActivos(): array
    {
        return $this->where('rol', 'ENFERMERO')
            ->where('estado', 'ACTIVO')
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /**
     * Busca un usuario por id incluyendo bajas lógicas (para editar, restablecer o reactivar).
     */
    public function buscarIncluyendoBajas(int $id): ?Usuario
    {
        return $this->withDeleted()->find($id);
    }

    /**
     * Genera una contraseña temporal legible y segura (HU-04 y HU-05).
     * Evita caracteres ambiguos (0/O, 1/l/I) para que el admin pueda dictarla sin errores.
     */
    public static function generarClaveTemporal(int $largo = 10): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $clave    = '';
        $maximo   = strlen($alfabeto) - 1;

        for ($i = 0; $i < $largo; $i++) {
            $clave .= $alfabeto[random_int(0, $maximo)];
        }

        return $clave;
    }
}
