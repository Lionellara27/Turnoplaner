<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Sector / servicio del hospital (HU-09, HU-10).
 */
class Sector extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'              => 'integer',
        'es_critico'      => 'integer',
        'dotacion_minima' => 'integer',
    ];

    public function estaActivo(): bool
    {
        return ($this->attributes['estado'] ?? '') === 'ACTIVO' && empty($this->attributes['deleted_at']);
    }

    public function esCritico(): bool
    {
        return (int) ($this->attributes['es_critico'] ?? 0) === 1;
    }

    /** "3 enfermeros/as por turno" / "1 enfermero/a por turno". */
    public function dotacionTexto(): string
    {
        $n = (int) ($this->attributes['dotacion_minima'] ?? 0);

        return $n . ($n === 1 ? ' enfermero/a' : ' enfermeros/as') . ' por turno';
    }
}
