<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * HU-11 / HU-12: tipos de turno (franjas horarias). Mismo esquema que database/turnoplaner.sql.
 */
class CreateTiposTurnoTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('tipos_turno')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'           => ['type' => 'VARCHAR', 'constraint' => 60],
            'hora_inicio'      => ['type' => 'TIME'],
            'hora_fin'         => ['type' => 'TIME'],
            'duracion_minutos' => ['type' => 'SMALLINT', 'unsigned' => true],
            'color'            => ['type' => 'CHAR', 'constraint' => 7, 'default' => '#307f6d'],
            'estado'           => ['type' => 'ENUM', 'constraint' => ['ACTIVO', 'BAJA'], 'default' => 'ACTIVO'],
            'created_at'       => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nombre', 'uq_tipos_turno_nombre');
        $this->forge->addKey('estado', false, false, 'idx_tipos_turno_estado');
        $this->forge->createTable('tipos_turno', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('tipos_turno', true);
    }
}
