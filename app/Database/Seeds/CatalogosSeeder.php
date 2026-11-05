<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Tipos de turno y sectores de ejemplo (los mismos que crea database/turnoplaner.sql).
 * Idempotente: no duplica por nombre.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = date('Y-m-d H:i:s');

        $tipos = [
            ['nombre' => 'Mañana', 'hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00', 'duracion_minutos' => 480, 'color' => '#c98a00'],
            ['nombre' => 'Tarde', 'hora_inicio' => '14:00:00', 'hora_fin' => '22:00:00', 'duracion_minutos' => 480, 'color' => '#b34700'],
            ['nombre' => 'Noche', 'hora_inicio' => '22:00:00', 'hora_fin' => '06:00:00', 'duracion_minutos' => 480, 'color' => '#3d5a99'],
            ['nombre' => 'Guardia especial', 'hora_inicio' => '08:00:00', 'hora_fin' => '20:00:00', 'duracion_minutos' => 720, 'color' => '#7b4ea3'],
        ];
        foreach ($tipos as $tipo) {
            if ($this->db->table('tipos_turno')->where('nombre', $tipo['nombre'])->countAllResults() === 0) {
                $this->db->table('tipos_turno')->insert($tipo + ['estado' => 'ACTIVO', 'created_at' => $ahora]);
            }
        }

        $sectores = [
            ['nombre' => 'UTI', 'descripcion' => 'Unidad de Terapia Intensiva de adultos.', 'es_critico' => 1, 'dotacion_minima' => 3],
            ['nombre' => 'Guardia', 'descripcion' => 'Guardia general y emergencias.', 'es_critico' => 1, 'dotacion_minima' => 2],
            ['nombre' => 'Neonatología', 'descripcion' => 'Cuidados neonatales e incubadoras.', 'es_critico' => 1, 'dotacion_minima' => 2],
            ['nombre' => 'Internación general', 'descripcion' => 'Sala de internación clínica y quirúrgica.', 'es_critico' => 0, 'dotacion_minima' => 2],
        ];
        foreach ($sectores as $sector) {
            if ($this->db->table('sectores')->where('nombre', $sector['nombre'])->countAllResults() === 0) {
                $this->db->table('sectores')->insert($sector + ['estado' => 'ACTIVO', 'created_at' => $ahora]);
            }
        }
    }
}
