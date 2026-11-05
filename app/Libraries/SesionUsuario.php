<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Entities\Usuario;

/**
 * Manejo de la sesión del usuario logueado (HU-01, HU-02, HU-03, HU-04).
 * Centraliza qué se guarda en sesión y cómo se cierra.
 */
final class SesionUsuario
{
    /** Claves de sesión que identifican al usuario logueado. */
    public const CLAVES = [
        'logueado', 'usuario_id', 'nombre', 'apellido', 'email', 'rol', 'debe_cambiar_pass', 'huella',
    ];

    /**
     * Inicia la sesión con un identificador nuevo (evita fijación de sesión).
     */
    public static function iniciar(Usuario $usuario): void
    {
        session()->regenerate(true);

        session()->set([
            'logueado'          => true,
            'usuario_id'        => (int) $usuario->id,
            'nombre'            => (string) $usuario->nombre,
            'apellido'          => (string) $usuario->apellido,
            'email'             => (string) $usuario->email,
            'rol'               => (string) $usuario->rol,
            'debe_cambiar_pass' => $usuario->debeCambiarPassword() ? 1 : 0,
            'huella'            => $usuario->huellaCredenciales(),
        ]);
    }

    /**
     * Tras cambiar la propia contraseña: nuevo identificador de sesión y huella actualizada,
     * así esta sesión sigue válida y las demás sesiones abiertas de la cuenta se cierran.
     */
    public static function renovarCredenciales(Usuario $usuario): void
    {
        session()->regenerate(true);
        session()->set([
            'huella'            => $usuario->huellaCredenciales(),
            'debe_cambiar_pass' => $usuario->debeCambiarPassword() ? 1 : 0,
        ]);
    }

    /**
     * Cierra la sesión del usuario. No usa session()->destroy() para que el mensaje
     * flash (por ejemplo "Sesión cerrada" o "Cuenta dada de baja") llegue al login.
     */
    public static function cerrar(): void
    {
        session()->remove(self::CLAVES);
        session()->regenerate(true);
    }
}
