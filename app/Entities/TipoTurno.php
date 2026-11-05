<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Tipo de turno / franja horaria (HU-11, HU-12).
 */
class TipoTurno extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'               => 'integer',
        'duracion_minutos' => 'integer',
    ];

    /** Color por defecto si el dato guardado no es un hexadecimal válido. */
    public const COLOR_DEFECTO = '#307f6d';

    /** Texto oscuro del sistema (DESIGN.md: --color-texto). */
    private const TEXTO_OSCURO = '#1e2a32';

    /**
     * Color de la etiqueta, siempre un "#rrggbb" válido: se imprime dentro de un atributo style,
     * así que un valor manipulado en la base no puede inyectar CSS.
     */
    public function getColor(): string
    {
        $color = (string) ($this->attributes['color'] ?? '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : self::COLOR_DEFECTO;
    }

    public function estaActivo(): bool
    {
        return ($this->attributes['estado'] ?? '') === 'ACTIVO' && empty($this->attributes['deleted_at']);
    }

    /** Hora de inicio en formato HH:MM (la base guarda HH:MM:SS). */
    public function inicio(): string
    {
        return substr((string) ($this->attributes['hora_inicio'] ?? ''), 0, 5);
    }

    /** Hora de fin en formato HH:MM. */
    public function fin(): string
    {
        return substr((string) ($this->attributes['hora_fin'] ?? ''), 0, 5);
    }

    /** El turno termina al día siguiente (por ejemplo 22:00 a 06:00). */
    public function cruzaMedianoche(): bool
    {
        return $this->fin() !== '' && $this->fin() <= $this->inicio();
    }

    /** Rango para mostrar: "22:00 → 06:00 (+1 día)". */
    public function rangoTexto(): string
    {
        return $this->inicio() . ' → ' . $this->fin() . ($this->cruzaMedianoche() ? ' (+1 día)' : '');
    }

    /** Duración en horas (decimal), por ejemplo 8 o 7.5. */
    public function duracionHoras(): float
    {
        return ((int) ($this->attributes['duracion_minutos'] ?? 0)) / 60;
    }

    /** Duración para mostrar: "8 h" o "7 h 30 min". */
    public function duracionTexto(): string
    {
        $minutos = (int) ($this->attributes['duracion_minutos'] ?? 0);
        $horas   = intdiv($minutos, 60);
        $resto   = $minutos % 60;

        return $horas . ' h' . ($resto > 0 ? ' ' . $resto . ' min' : '');
    }

    /**
     * Color de texto legible sobre la etiqueta de color (para la cuadrícula, HU-14):
     * negro sobre colores claros y blanco sobre oscuros, según la luminancia (WCAG).
     */
    public function colorTexto(): string
    {
        return self::textoSobre($this->getColor());
    }

    public static function textoSobre(string $hex): string
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return '#ffffff';
        }

        $fondo  = self::luminancia($hex);
        $blanco = 1.05 / ($fondo + 0.05);
        $oscuro = ($fondo + 0.05) / (self::luminancia(self::TEXTO_OSCURO) + 0.05);

        return $blanco >= $oscuro ? '#ffffff' : self::TEXTO_OSCURO;
    }

    /** Luminancia relativa de un color "#rrggbb" (fórmula de WCAG 2.1). */
    private static function luminancia(string $hex): float
    {
        $hex   = ltrim($hex, '#');
        $canal = static function (string $c): float {
            $v = hexdec($c) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal(substr($hex, 0, 2)) + 0.7152 * $canal(substr($hex, 2, 2)) + 0.0722 * $canal(substr($hex, 4, 2));
    }
}
