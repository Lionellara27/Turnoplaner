<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Portal del enfermero/a: "Mi Cronograma".
 *
 * Es la pantalla de destino del rol ENFERMERO tras el login (HU-02) y tras activar
 * la cuenta (HU-01). El contenido real (turnos asignados) llega con HU-17, cuando
 * existan las tablas de asignaciones (HU-13). Por ahora muestra el estado vacío.
 */
class Cronograma extends BaseController
{
    public function index(): string
    {
        return view('cronograma/index', [
            'titulo' => 'Mi cronograma - TurnoPlaner',
            'nombre' => (string) session()->get('nombre'),
        ]);
    }
}
