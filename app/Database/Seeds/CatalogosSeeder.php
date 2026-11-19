<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Sectores y tipos de turno de ejemplo: los mismos que crea database/turnoplaner.sql.
 *
 * Orden obligatorio: primero los sectores y después los tipos, porque cada tipo de turno pertenece a un
 * sector (tipos_turno.sector_id NOT NULL con FK a sectores). Los tipos se definen por NOMBRE de sector y
 * el id se busca en la base, así funciona aunque los ids no sean 1..7.
 *
 * Idempotente: no duplica sectores (por nombre) ni tipos (por sector + nombre, igual que el índice único).
 */
class CatalogosSeeder extends Seeder
{
    /** @var list<array{nombre: string, descripcion: string, es_critico: int, dotacion_minima: int}> */
    private const SECTORES = [
        ['nombre' => 'Internación general', 'descripcion' => 'Sala común de clínica médica y quirúrgica (Enfermería General).', 'es_critico' => 0, 'dotacion_minima' => 3],
        ['nombre' => 'Guardia', 'descripcion' => 'Guardia general y emergencias 24 hs.', 'es_critico' => 1, 'dotacion_minima' => 3],
        ['nombre' => 'UTI', 'descripcion' => 'Unidad de Terapia Intensiva de adultos.', 'es_critico' => 1, 'dotacion_minima' => 3],
        ['nombre' => 'Neonatología', 'descripcion' => 'Cuidados intensivos e intermedios neonatales.', 'es_critico' => 1, 'dotacion_minima' => 2],
        ['nombre' => 'Quirófano', 'descripcion' => 'Centro quirúrgico y recuperación postanestésica.', 'es_critico' => 1, 'dotacion_minima' => 2],
        ['nombre' => 'Salud Mental', 'descripcion' => 'Internación y guardia interdisciplinaria de salud mental.', 'es_critico' => 0, 'dotacion_minima' => 2],
        ['nombre' => 'Oncología', 'descripcion' => 'Hospital de día y cuidados oncológicos.', 'es_critico' => 0, 'dotacion_minima' => 2],
    ];

    /** [sector => [[nombre, inicio, fin, minutos, color], ...]] */
    private const TIPOS = [
        'Internación general' => [
            ['Mañana', '06:00:00', '14:00:00', 480, '#c98a00'],
            ['Tarde', '14:00:00', '22:00:00', 480, '#b34700'],
            ['Noche', '22:00:00', '06:00:00', 480, '#3d5a99'],
        ],
        'Guardia' => [
            ['Mañana', '06:00:00', '14:00:00', 480, '#c98a00'],
            ['Tarde', '14:00:00', '22:00:00', 480, '#b34700'],
            ['Noche', '22:00:00', '06:00:00', 480, '#3d5a99'],
        ],
        'UTI' => [
            ['Vespertino', '00:00:00', '06:00:00', 360, '#7b4ea3'],
            ['Mañana', '06:00:00', '12:00:00', 360, '#307f6d'],
            ['Tarde', '12:00:00', '18:00:00', 360, '#c98a00'],
            ['Noche', '18:00:00', '00:00:00', 360, '#3d5a99'],
        ],
        'Neonatología' => [
            ['Mañana', '06:00:00', '14:00:00', 480, '#c98a00'],
            ['Tarde', '14:00:00', '22:00:00', 480, '#b34700'],
            ['Noche', '22:00:00', '06:00:00', 480, '#3d5a99'],
        ],
        'Quirófano' => [
            ['Mañana', '06:00:00', '14:00:00', 480, '#307f6d'],
            ['Tarde', '14:00:00', '22:00:00', 480, '#b34700'],
            ['Guardia', '22:00:00', '06:00:00', 480, '#3d5a99'],
        ],
        'Salud Mental' => [
            ['Mañana', '06:00:00', '14:00:00', 480, '#c98a00'],
            ['Tarde', '14:00:00', '22:00:00', 480, '#b34700'],
            ['Noche', '22:00:00', '06:00:00', 480, '#3d5a99'],
        ],
        'Oncología' => [
            ['Mañana', '06:00:00', '14:00:00', 480, '#307f6d'],
            ['Tarde', '14:00:00', '22:00:00', 480, '#7b4ea3'],
        ],
    ];

    public function run(): void
    {
        $ahora = date('Y-m-d H:i:s');

        // 1) Sectores.
        foreach (self::SECTORES as $sector) {
            if ($this->db->table('sectores')->where('nombre', $sector['nombre'])->countAllResults() === 0) {
                $this->db->table('sectores')->insert($sector + ['estado' => 'ACTIVO', 'created_at' => $ahora]);
            }
        }

        // 2) Tipos de turno de cada sector.
        foreach (self::TIPOS as $nombreSector => $tipos) {
            $sector = $this->db->table('sectores')->select('id')->where('nombre', $nombreSector)->get()->getRow();
            if ($sector === null) {
                continue;
            }

            foreach ($tipos as [$nombre, $inicio, $fin, $minutos, $color]) {
                $existe = $this->db->table('tipos_turno')
                    ->where('sector_id', (int) $sector->id)
                    ->where('nombre', $nombre)
                    ->countAllResults() > 0;

                if (! $existe) {
                    $this->db->table('tipos_turno')->insert([
                        'sector_id'        => (int) $sector->id,
                        'nombre'           => $nombre,
                        'hora_inicio'      => $inicio,
                        'hora_fin'         => $fin,
                        'duracion_minutos' => $minutos,
                        'color'            => $color,
                        'estado'           => 'ACTIVO',
                        'created_at'       => $ahora,
                    ]);
                }
            }
        }
    }
}
