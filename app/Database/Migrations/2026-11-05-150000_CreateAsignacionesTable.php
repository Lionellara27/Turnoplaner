<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * HU-13 / HU-14: asignaciones de turnos. Mismo esquema que database/turnoplaner.sql.
 */
class CreateAsignacionesTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('asignaciones')) {
            return;
        }

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'usuario_id'       => ['type' => 'INT', 'unsigned' => true],
            'sector_id'        => ['type' => 'INT', 'unsigned' => true],
            'tipo_turno_id'    => ['type' => 'INT', 'unsigned' => true],
            'fecha'            => ['type' => 'DATE'],
            'inicio'           => ['type' => 'DATETIME'],
            'fin'              => ['type' => 'DATETIME'],
            'duracion_minutos' => ['type' => 'SMALLINT', 'unsigned' => true],
            'creado_por'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['usuario_id', 'inicio'], false, false, 'idx_asignaciones_usuario_inicio');
        $this->forge->addKey(['sector_id', 'fecha'], false, false, 'idx_asignaciones_sector_fecha');
        $this->forge->addKey(['tipo_turno_id', 'fecha'], false, false, 'idx_asignaciones_tipo_fecha');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', '', '', 'fk_asignaciones_usuario');
        $this->forge->addForeignKey('sector_id', 'sectores', 'id', '', '', 'fk_asignaciones_sector');
        $this->forge->addForeignKey('tipo_turno_id', 'tipos_turno', 'id', '', '', 'fk_asignaciones_tipo_turno');
        $this->forge->addForeignKey('creado_por', 'usuarios', 'id', '', '', 'fk_asignaciones_creado_por');
        $this->forge->createTable('asignaciones', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('asignaciones', true);
    }
}
