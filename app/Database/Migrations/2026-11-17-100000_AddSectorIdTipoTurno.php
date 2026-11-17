<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSectorIdToTiposTurno extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('sector_id', 'tipos_turno')) {
            // Si ya había registros previos, les asignamos el primer sector existente para no romper la FK
            $primerSector = $this->db->table('sectores')->select('id')->orderBy('id', 'ASC')->get()->getRow();
            $defaultId    = $primerSector ? (int) $primerSector->id : 1;

            $this->forge->addColumn('tipos_turno', [
                'sector_id' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => false,
                    'default'  => $defaultId,
                    'after'    => 'id',
                ],
            ]);

            // Quitamos el default temporal y ajustamos índices + FK
            $this->db->query('ALTER TABLE tipos_turno MODIFY sector_id INT UNSIGNED NOT NULL');
            $this->db->query('ALTER TABLE tipos_turno DROP INDEX uq_tipos_turno_nombre');
            $this->db->query('ALTER TABLE tipos_turno ADD UNIQUE KEY uq_tipos_turno_sector_nombre (sector_id, nombre)');
            $this->db->query('ALTER TABLE tipos_turno ADD CONSTRAINT fk_tipos_turno_sector FOREIGN KEY (sector_id) REFERENCES sectores (id)');
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('sector_id', 'tipos_turno')) {
            $this->db->query('ALTER TABLE tipos_turno DROP FOREIGN KEY fk_tipos_turno_sector');
            $this->db->query('ALTER TABLE tipos_turno DROP INDEX uq_tipos_turno_sector_nombre');
            $this->db->query('ALTER TABLE tipos_turno ADD UNIQUE KEY uq_tipos_turno_nombre (nombre)');
            $this->forge->dropColumn('tipos_turno', 'sector_id');
        }
    }
}