<?php

declare(strict_types=1);

/**
 * Helpers de interfaz para formularios (skill ux-ui-expert, sección 5).
 * Los errores llegan por flashdata 'errors' como [campo => mensaje].
 */

if (! function_exists('error_campo')) {
    /**
     * Mensaje de error de un campo, o cadena vacía.
     */
    function error_campo(string $campo): string
    {
        $errores = session()->getFlashdata('errors');

        return is_array($errores) && isset($errores[$campo]) ? (string) $errores[$campo] : '';
    }
}

if (! function_exists('clase_invalido')) {
    /**
     * Devuelve ' is-invalid' si el campo tiene error, para sumar a la clase del input.
     */
    function clase_invalido(string $campo): string
    {
        return error_campo($campo) !== '' ? ' is-invalid' : '';
    }
}

if (! function_exists('feedback_campo')) {
    /**
     * Bloque invalid-feedback de Bootstrap con el mensaje del servidor (ya escapado).
     */
    function feedback_campo(string $campo): string
    {
        $mensaje = error_campo($campo);

        return $mensaje === ''
            ? ''
            : '<div class="invalid-feedback" id="error-' . esc($campo, 'attr') . '">' . esc($mensaje) . '</div>';
    }
}

if (! function_exists('dia_semana')) {
    /**
     * Nombre del día en español: "lunes", "martes"... (sin depender de la configuración regional del servidor).
     */
    function dia_semana(mixed $fecha): string
    {
        $dias    = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
        $momento = $fecha instanceof DateTimeInterface ? $fecha : new DateTimeImmutable((string) $fecha);

        return $dias[(int) $momento->format('N')];
    }
}

if (! function_exists('fecha_larga')) {
    /**
     * "lunes 09/11/2026" (skill ux-ui-expert, sección 7: fechas dd/mm/aaaa).
     */
    function fecha_larga(mixed $fecha): string
    {
        return dia_semana($fecha) . ' ' . fecha_ar($fecha);
    }
}

if (! function_exists('fecha_ar')) {
    /**
     * Fecha en formato dd/mm/aaaa (skill ux-ui-expert, sección 7).
     */
    function fecha_ar(mixed $fecha, bool $conHora = false): string
    {
        if ($fecha === null || $fecha === '') {
            return '-';
        }

        $momento = $fecha instanceof DateTimeInterface ? $fecha : new DateTimeImmutable((string) $fecha);

        return $momento->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
