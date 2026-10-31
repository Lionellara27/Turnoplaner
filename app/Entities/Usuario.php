<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Usuario extends Entity
{
    // Mapeo automático de columnas de MariaDB a atributos
    protected $attributes = [
        'id'               => null,
        'nombre'           => null,
        'apellido'         => null,
        'dni'              => null,
        'matricula'        => null,
        'email'            => null,
        'telefono'         => null,
        'especialidad'     => null,
        'turno_preferente' => null,
        'password'         => null,
        'rol'              => 'enfermero',
        'estado'           => 'pendiente',
        'created_at'       => null,
        'updated_at'       => null,
    ];

    // Mutator: Se ejecuta automáticamente al hacer $usuario->password = 'secret'
    public function setPassword(string $pass): self
    {
        $this->attributes['password'] = password_hash($pass, PASSWORD_BCRYPT);
        return $this;
    }
}