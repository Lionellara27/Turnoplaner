<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * HU-09 / HU-10: sectores del hospital con dotación mínima. Mismo esquema que database/turnoplaner.sql.
 */
class CreateSectoresTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('sectores')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'          => ['type' => 'VARCHAR', 'constraint' => 80],
            'descripcion'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'es_critico'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'dotacion_minima' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'estado'          => ['type' => 'ENUM', 'constraint' => ['ACTIVO', 'BAJA'], 'default' => 'ACTIVO'],
            'created_at'      => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nombre', 'uq_sectores_nombre');
        $this->forge->addKey('estado', false, false, 'idx_sectores_estado');
        $this->forge->createTable('sectores', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('sectores', true);
    }
}
