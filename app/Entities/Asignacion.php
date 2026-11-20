<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Turno asignado a un enfermero/a (HU-13, HU-14).
 * Cuando viene de AsignacionModel::semanaDeSector() trae además el nombre del enfermero/a.
 * Las consultas de AsignacionModel (mesDeSector, semanaDeSector, delEnfermero, proximoDelEnfermero,
 * siguienteProgramado) traen además la ausencia activa del turno (ausencia_id, ausencia_estado) y si el
 * turno es el reemplazo de una ausencia cubierta (reemplazo_de_ausencia_id). HU-16, HU-18, HU-20.
 */
class Asignacion extends Entity
{
    /** Minutos antes del inicio desde los que se puede marcar el ingreso (HU-20). */
    public const MINUTOS_ANTES_INGRESO = 30;

    /** Horas después del fin hasta las que se puede finalizar la guardia (HU-20). */
    public const HORAS_DESPUES_SALIDA = 4;

    /** Largo máximo de las observaciones del pase de guardia (HU-20). */
    public const MAX_OBSERVACIONES_PASE = 1000;

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'               => 'integer',
        'usuario_id'       => 'integer',
        'sector_id'        => 'integer',
        'tipo_turno_id'    => 'integer',
        'duracion_minutos' => 'integer',
        'es_extra'         => 'integer',
        'minutos_extra'    => 'integer',
    ];

    public function esExtra(): bool
    {
        return (int) ($this->attributes['es_extra'] ?? 0) === 1;
    }
    
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

    /** Matrícula profesional del enfermero/a si la consulta la trajo. */
    public function matriculaEnfermero(): string
    {
        $mat = trim((string) ($this->attributes['matricula'] ?? ''));

        return $mat !== '' ? $mat : 'Sin matrícula';
    }

    /** Especialidad del enfermero/a si la consulta la trajo. */
    public function especialidadEnfermero(): string
    {
        return trim((string) ($this->attributes['especialidad'] ?? ''));
    }

    /** Estado de la ausencia activa del turno ('PENDIENTE' o 'CUBIERTA'), o null si no tiene. */
    public function ausenciaEstado(): ?string
    {
        $estado = $this->attributes['ausencia_estado'] ?? null;

        return in_array($estado, ['PENDIENTE', 'CUBIERTA'], true) ? $estado : null;
    }

    /** Id de la ausencia activa del turno, o null. */
    public function ausenciaId(): ?int
    {
        $id = $this->attributes['ausencia_id'] ?? null;

        return $id !== null && $this->ausenciaEstado() !== null ? (int) $id : null;
    }

    /** El enfermero/a avisó que no puede cumplir este turno (ausencia PENDIENTE o CUBIERTA). */
    public function tieneAusenciaActiva(): bool
    {
        return $this->ausenciaEstado() !== null;
    }

    /** Ausencia en gestión: el administrador todavía no la resolvió. */
    public function ausenciaPendiente(): bool
    {
        return $this->ausenciaEstado() === 'PENDIENTE';
    }

    /** "Ausente justificado (Cubierto)": ya se asignó un reemplazo. */
    public function ausenteCubierto(): bool
    {
        return $this->ausenciaEstado() === 'CUBIERTA';
    }

    /** El turno fue creado para cubrir la ausencia de otro enfermero/a. */
    public function esReemplazo(): bool
    {
        return ! empty($this->attributes['reemplazo_de_ausencia_id']);
    }

    /**
     * Cuenta para la dotación del sector: el enfermero/a sigue activo y no avisó una ausencia
     * (PENDIENTE o CUBIERTA). Es lo que usan el planificador, el panel y el tablero de cobertura.
     */
    public function cuentaEnDotacion(): bool
    {
        return ! $this->enfermeroDeBaja() && ! $this->tieneAusenciaActiva();
    }

    /** Ya marcó el ingreso (HU-20). */
    public function tieneIngreso(): bool
    {
        return ! empty($this->attributes['checkin_at']);
    }

    /** Ya finalizó la guardia (HU-20). */
    public function tieneSalida(): bool
    {
        return ! empty($this->attributes['checkout_at']);
    }

    /** "06:58" del ingreso marcado, o '' si no marcó. */
    public function horaIngreso(): string
    {
        return $this->tieneIngreso() ? substr((string) $this->attributes['checkin_at'], 11, 5) : '';
    }

    /** "14:05" de la salida marcada, o '' si no marcó. */
    public function horaSalida(): string
    {
        return $this->tieneSalida() ? substr((string) $this->attributes['checkout_at'], 11, 5) : '';
    }

    /** Observaciones del pase de guardia ('' si no dejó). */
    public function observacionesPase(): string
    {
        return trim((string) ($this->attributes['observaciones_pase'] ?? ''));
    }

    /** Momento desde el que se puede marcar el ingreso ('Y-m-d H:i:s'). */
    public function aperturaIngreso(): string
    {
        $inicio = (string) ($this->attributes['inicio'] ?? '');

        return date('Y-m-d H:i:s', strtotime($inicio . ' -' . self::MINUTOS_ANTES_INGRESO . ' minutes'));
    }

    /** Momento hasta el que se puede finalizar la guardia ('Y-m-d H:i:s'). */
    public function cierreSalida(): string
    {
        $fin = (string) ($this->attributes['fin'] ?? '');

        return date('Y-m-d H:i:s', strtotime($fin . ' +' . self::HORAS_DESPUES_SALIDA . ' hours'));
    }

    /**
     * Se puede marcar el ingreso ahora: desde 30 min antes del inicio hasta el fin, sin ausencia
     * activa y sin ingreso previo. Requiere que la consulta haya traído ausencia_estado.
     */
    public function puedeMarcarIngreso(string $ahora): bool
    {
        return ! $this->tieneIngreso()
            && ! $this->tieneAusenciaActiva()
            && $ahora >= $this->aperturaIngreso()
            && $ahora < (string) ($this->attributes['fin'] ?? '');
    }

    /** Se puede finalizar la guardia ahora: con ingreso marcado, sin salida previa y hasta 4 h después del fin. */
    public function puedeMarcarSalida(string $ahora): bool
    {
        return $this->tieneIngreso()
            && ! $this->tieneSalida()
            && ! $this->tieneAusenciaActiva()
            && $ahora <= $this->cierreSalida();
    }
}
