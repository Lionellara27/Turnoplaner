<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Usuario extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'                => 'integer',
        'carga_horaria_max' => '?integer',
        'debe_cambiar_pass' => 'integer',
    ];

    /**
     * Mutador: Encripta la contraseña de forma automática usando BCRYPT/PASSWORD_DEFAULT
     */
    public function setPassword(string $pass): self
    {
        $this->attributes['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        return $this;
    }

    /**
     * Accesor: Nombre completo formateado (Apellido, Nombre)
     */
    public function getNombreCompleto(): string
    {
        $apellido = $this->attributes['apellido'] ?? '';
        $nombre   = $this->attributes['nombre'] ?? '';
        return trim("{$apellido}, {$nombre}", ', ');
    }

    public function esAdmin(): bool
    {
        return ($this->attributes['rol'] ?? '') === 'ADMIN';
    }

    public function esEnfermero(): bool
    {
        return ($this->attributes['rol'] ?? '') === 'ENFERMERO';
    }

    public function estaActivo(): bool
    {
        return ($this->attributes['estado'] ?? '') === 'ACTIVO';
    }

    public function debeCambiarPassword(): bool
    {
        return ((int) ($this->attributes['debe_cambiar_pass'] ?? 0)) === 1;
    }

    /**
     * Compatibilidad de atributos para mail / email
     */
    public function setMail(string $mail): self
    {
        $this->attributes['email'] = $mail;
        return $this;
    }

    public function getMail(): ?string
    {
        return $this->attributes['email'] ?? null;
    }
}

