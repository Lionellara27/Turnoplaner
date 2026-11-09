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

    /**
     * Estado del turno respecto de un momento (HU-17): 'proximo', 'en_curso' o 'finalizado'.
     */
    public function estadoTurno(string $ahora): string
    {
        $inicio = (string) ($this->attributes['inicio'] ?? '');
        $fin    = (string) ($this->attributes['fin'] ?? '');

        if ($fin <= $ahora) {
            return 'finalizado';
        }

        return $inicio <= $ahora ? 'en_curso' : 'proximo';
    }

    /** "06:00" a partir del inicio guardado. */
    public function horaInicio(): string
    {
        return substr((string) ($this->attributes['inicio'] ?? ''), 11, 5);
    }

    /** "14:00" a partir del fin guardado. */
    public function horaFin(): string
    {
        return substr((string) ($this->attributes['fin'] ?? ''), 11, 5);
    }

    /** El turno termina al día siguiente de su fecha (por ejemplo, la Noche). */
    public function terminaAlDiaSiguiente(): bool
    {
        return substr((string) ($this->attributes['fin'] ?? ''), 0, 10) > (string) ($this->attributes['fecha'] ?? '');
    }

    /** Color de la etiqueta del turno (saneado: siempre "#rrggbb"). */
    public function colorTipo(): string
    {
        $color = (string) ($this->attributes['tipo_color'] ?? '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : TipoTurno::COLOR_DEFECTO;
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
