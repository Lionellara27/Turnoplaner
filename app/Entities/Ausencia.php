<?php

declare(strict_types=1);

namespace App\Entities;

use App\Models\AusenciaModel;
use CodeIgniter\Entity\Entity;

/**
 * Aviso de ausencia de un enfermero/a a un turno suyo (HU-18) y su resolución por el administrador:
 * reemplazo (HU-16) o rechazo.
 *
 * Cuando viene de AusenciaModel::listarParaAdmin() / listarDeUsuario() trae además los datos del turno,
 * del enfermero/a, del sector, del tipo de turno y del reemplazante (ver AusenciaModel::SELECT_DETALLE).
 */
class Ausencia extends Entity
{
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'id'                      => 'integer',
        'asignacion_id'           => 'integer',
        'usuario_id'              => 'integer',
        'reemplazo_asignacion_id' => '?integer',
        'resuelto_por'            => '?integer',
    ];

    public function estaPendiente(): bool
    {
        return ($this->attributes['estado'] ?? '') === AusenciaModel::PENDIENTE;
    }

    public function estaCubierta(): bool
    {
        return ($this->attributes['estado'] ?? '') === AusenciaModel::CUBIERTA;
    }

    public function estaRechazada(): bool
    {
        return ($this->attributes['estado'] ?? '') === AusenciaModel::RECHAZADA;
    }

    /** PENDIENTE o CUBIERTA: el turno no cuenta para la dotación ni para las horas. */
    public function esActiva(): bool
    {
        return in_array($this->attributes['estado'] ?? '', AusenciaModel::ESTADOS_ACTIVOS, true);
    }

    /** "Enfermedad", "Fuerza mayor", "Licencia médica" u "Otro". */
    public function motivoTexto(): string
    {
        return AusenciaModel::MOTIVOS[$this->attributes['motivo'] ?? ''] ?? 'Otro';
    }

    /** "Pendiente", "Cubierta" o "Rechazada" (para el administrador). */
    public function estadoTexto(): string
    {
        return AusenciaModel::ESTADOS[$this->attributes['estado'] ?? ''] ?? 'Pendiente';
    }

    /** Texto para el enfermero/a: "Aviso enviado, en gestión", "Reemplazo asignado" o "Rechazada: debe presentarse". */
    public function estadoParaEnfermero(): string
    {
        return AusenciaModel::ESTADOS_ENFERMERO[$this->attributes['estado'] ?? ''] ?? '';
    }

    /** Ícono Bootstrap del estado (siempre acompañado de texto en la vista). */
    public function iconoEstado(): string
    {
        return AusenciaModel::ICONOS_ESTADO[$this->attributes['estado'] ?? ''] ?? 'bi-hourglass-split';
    }

    /** Observación del administrador al rechazar ('' si no hay). */
    public function observacionAdmin(): string
    {
        return trim((string) ($this->attributes['observacion_admin'] ?? ''));
    }

    /** "Pérez, Juan" del enfermero/a que avisó, si la consulta lo trajo. */
    public function nombreEnfermero(): string
    {
        return trim(($this->attributes['enfermero_apellido'] ?? '') . ', ' . ($this->attributes['enfermero_nombre'] ?? ''), ', ');
    }

    /** "Pérez, Juan" del reemplazante, o '' si todavía no hay. */
    public function nombreReemplazante(): string
    {
        return trim(($this->attributes['reemplazante_apellido'] ?? '') . ', ' . ($this->attributes['reemplazante_nombre'] ?? ''), ', ');
    }

    /** "06:00" del inicio del turno, si la consulta lo trajo. */
    public function horaInicio(): string
    {
        return substr((string) ($this->attributes['inicio'] ?? ''), 11, 5);
    }

    /** "14:00" del fin del turno, si la consulta lo trajo. */
    public function horaFin(): string
    {
        return substr((string) ($this->attributes['fin'] ?? ''), 11, 5);
    }

    /** "22:00 a 06:00 (+1 día)" del turno, si la consulta lo trajo. */
    public function horarioTexto(): string
    {
        $fecha = (string) ($this->attributes['fecha'] ?? '');
        $fin   = (string) ($this->attributes['fin'] ?? '');

        return $this->horaInicio() . ' a ' . $this->horaFin() . (substr($fin, 0, 10) > $fecha ? ' (+1 día)' : '');
    }

    /** Color del tipo de turno (saneado). */
    public function colorTipo(): string
    {
        $color = (string) ($this->attributes['tipo_color'] ?? '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : TipoTurno::COLOR_DEFECTO;
    }

    /** El turno ya terminó (requiere 'fin' en la consulta). */
    public function turnoTerminado(?string $ahora = null): bool
    {
        return (string) ($this->attributes['fin'] ?? '') <= ($ahora ?? date('Y-m-d H:i:s'));
    }

    /** Se puede asignar un reemplazo: PENDIENTE y el turno todavía no terminó. */
    public function puedeCubrirse(?string $ahora = null): bool
    {
        return $this->estaPendiente() && ! $this->turnoTerminado($ahora);
    }

    /** Se puede rechazar: sigue PENDIENTE. */
    public function puedeRechazarse(): bool
    {
        return $this->estaPendiente();
    }
}
