<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Turno asignado a un enfermero/a (HU-13, HU-14).
 * Cuando viene de AsignacionModel::semanaDeSector() trae además el nombre del enfermero/a.
 */
class Asignacion extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'               => 'integer',
        'usuario_id'       => 'integer',
        'sector_id'        => 'integer',
        'tipo_turno_id'    => 'integer',
        'duracion_minutos' => 'integer',
    ];

    /**
     * Se puede reasignar o eliminar mientras el turno no haya terminado
     * (la mañana de hoy deja de ser editable a las 14:00).
     */
    public function esFutura(): bool
    {
        return (string) ($this->attributes['fin'] ?? '') > date('Y-m-d H:i:s');
    }

    /** "Pérez, Juan" si la consulta trajo el nombre. */
    public function nombreEnfermero(): string
    {
        $apellido = (string) ($this->attributes['apellido'] ?? '');
        $nombre   = (string) ($this->attributes['nombre'] ?? '');

        return trim("{$apellido}, {$nombre}", ', ');
    }

    /** El enfermero/a fue dado de baja después de asignarle el turno. */
    public function enfermeroDeBaja(): bool
    {
        return ($this->attributes['usuario_estado'] ?? 'ACTIVO') !== 'ACTIVO';
    }
}
