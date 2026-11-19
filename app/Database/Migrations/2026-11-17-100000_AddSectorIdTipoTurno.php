<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSectorIdToTiposTurno extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('sector_id', 'tipos_turno')) {
            return;
        }

        $sectores = $this->db->table('sectores')
            ->select('id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $primerSectorId = $sectores !== [] ? (int) $sectores[0]['id'] : 1;

        $this->forge->addColumn('tipos_turno', [
            'sector_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
                'default'  => $primerSectorId,
                'after'    => 'id',
            ],
        ]);

        $this->db->query('ALTER TABLE tipos_turno MODIFY sector_id INT UNSIGNED NOT NULL');
        $this->db->query('ALTER TABLE tipos_turno DROP INDEX uq_tipos_turno_nombre');
        $this->db->query('ALTER TABLE tipos_turno ADD UNIQUE KEY uq_tipos_turno_nombre (sector_id, nombre)');
        $this->db->query('ALTER TABLE tipos_turno ADD KEY inx_tipos_turno_sector (sector_id)');
        $this->db->query('ALTER TABLE tipos_turno ADD CONSTRAINT fk_tipos_turno_sector FOREIGN KEY (sector_id) REFERENCES sectores (id)');

        if (count($sectores) > 1) {
            $turnosBase = $this->db->table('tipos_turno')
                ->where('sector_id', $primerSectorId)
                ->get()
                ->getResultArray();

            foreach (array_slice($sectores, 1) as $sec) {
                $secId = (int) $sec['id'];
                foreach ($turnosBase as $tb) {
                    $this->db->table('tipos_turno')->insert([
                        'sector_id'        => $secId,
                        'nombre'           => $tb['nombre'],
                        'hora_inicio'      => $tb['hora_inicio'],
                        'hora_fin'         => $tb['hora_fin'],
                        'duracion_minutos' => $tb['duracion_minutos'],
                        'color'            => $tb['color'],
                        'estado'           => $tb['estado'],
                        'created_at'       => $tb['created_at'],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->fieldExists('sector_id', 'tipos_turno')) {
            return;
        }

        $primerSector = $this->db->table('sectores')->selectMin('id')->get()->getRowArray();
        $primerId     = (int) ($primerSector['id'] ?? 1);

        $this->db->query(
            'DELETE FROM tipos_turno WHERE sector_id != ? AND id NOT IN (SELECT DISTINCT tipo_turno_id FROM asignaciones)',
            [$primerId]
        );

        $this->db->query('ALTER TABLE tipos_turno DROP FOREIGN KEY fk_tipos_turno_sector');
        $this->db->query('ALTER TABLE tipos_turno DROP INDEX uq_tipos_turno_nombre');
        $this->db->query('ALTER TABLE tipos_turno DROP INDEX inx_tipos_turno_sector');
        $this->db->query('ALTER TABLE tipos_turno ADD UNIQUE KEY uq_tipos_turno_nombre (nombre)');
        $this->forge->dropColumn('tipos_turno', 'sector_id');
    }
}