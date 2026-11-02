<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use App\Entities\Usuario;

class UsuarioModel extends Model
{
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

    // Reglas de validación internas del modelo
    protected $validationRules = [
        'nombre'            => 'required|min_length[2]|max_length[100]',
        'apellido'          => 'required|min_length[2]|max_length[100]',
        'dni'               => 'required|numeric|min_length[7]|max_length[10]|is_unique[usuarios.dni,id,{id}]',
        'matricula'         => 'permit_empty|regex_match[/^[a-zA-Z0-9\s\-\/]+$/]|max_length[20]|is_unique[usuarios.matricula,id,{id}]',
        'email'             => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,{id}]',
        'carga_horaria_max' => 'permit_empty|is_natural_no_zero',
        'rol'               => 'in_list[ADMIN,ENFERMERO]',
        'estado'            => 'in_list[ACTIVO,BAJA,PENDIENTE]',
    ];

    protected $validationMessages = [
        'dni' => [
            'required'   => 'El número de DNI es obligatorio.',
            'numeric'    => 'El DNI debe contener solo números.',
            'min_length' => 'El DNI debe contener al menos 7 dígitos.',
            'max_length' => 'El DNI no debe superar los 10 dígitos.',
            'is_unique'  => 'Ya existe un usuario registrado con el número de DNI ingresado.',
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
        ],
    ];

    /**
     * Baja lógica estricta: Actualiza estado a BAJA y ejecuta soft delete (deleted_at = NOW()).
     * PROHIBIDO EL DELETE FÍSICO.
     */
    public function darDeBajaLogica(int $id): bool
    {
        $this->update($id, ['estado' => 'BAJA']);
        return (bool) $this->delete($id);
    }

    /**
     * Reactivación de un usuario dado de baja.
     */
    public function reactivar(int $id): bool
    {
        return (bool) $this->update($id, [
            'estado'     => 'ACTIVO',
            'deleted_at' => null,
        ]);
    }

    /**
     * Obtiene los usuarios activos con filtros opcionales de búsqueda.
     *
     * @return Usuario[]
     */
    public function obtenerActivosConFiltros(?string $estado = null, ?string $busqueda = null): array
    {
        $builder = $this->builder();

        if ($estado !== null && $estado !== '') {
            $builder->where('estado', $estado);
        }

        if ($busqueda !== null && $busqueda !== '') {
            $builder->groupStart()
                ->like('nombre', $busqueda)
                ->orLike('apellido', $busqueda)
                ->orLike('dni', $busqueda)
                ->orLike('matricula', $busqueda)
                ->orLike('email', $busqueda)
            ->groupEnd();
        }

        return $this->where('deleted_at IS NULL')
                    ->orderBy('apellido', 'ASC')
                    ->findAll();
    }

    /**
     * Busca un usuario por DNI o Correo electrónico para autenticación.
     */
    public function buscarPorIdentificador(string $identificador): ?Usuario
    {
        return $this->groupStart()
                    ->where('email', $identificador)
                    ->orWhere('dni', $identificador)
                ->groupEnd()
                ->first();
    }
}
