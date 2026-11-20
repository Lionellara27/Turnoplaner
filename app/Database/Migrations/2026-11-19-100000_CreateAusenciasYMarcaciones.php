<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * HU-16 / HU-18: tabla de ausencias (aviso del enfermero/a y reemplazo).
 * HU-20: marcación de ingreso, salida y pase de guardia en asignaciones.
 * Mismo esquema que database/turnoplaner.sql.
 */
class CreateAusenciasYMarcaciones extends Migration
{
    public function up(): void
    {
        $campos = [];

        if (! $this->db->fieldExists('checkin_at', 'asignaciones')) {
            $campos['checkin_at'] = ['type' => 'DATETIME', 'null' => true, 'default' => null, 'after' => 'creado_por'];
        }
        if (! $this->db->fieldExists('checkout_at', 'asignaciones')) {
            $campos['checkout_at'] = ['type' => 'DATETIME', 'null' => true, 'default' => null, 'after' => 'checkin_at'];
        }
        if (! $this->db->fieldExists('observaciones_pase', 'asignaciones')) {
            $campos['observaciones_pase'] = ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true, 'default' => null, 'after' => 'checkout_at'];
        }
        if ($campos !== []) {
            $this->forge->addColumn('asignaciones', $campos);
        }

        if ($this->db->tableExists('ausencias')) {
            return;
        }

        $this->forge->addField([
            'id'                      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'asignacion_id'           => ['type' => 'INT', 'unsigned' => true],
            'usuario_id'              => ['type' => 'INT', 'unsigned' => true],
            'motivo'                  => ['type' => 'ENUM', 'constraint' => ['ENFERMEDAD', 'FUERZA_MAYOR', 'LICENCIA_MEDICA', 'OTRO']],
            'descripcion'             => ['type' => 'VARCHAR', 'constraint' => 500],
            'estado'                  => ['type' => 'ENUM', 'constraint' => ['PENDIENTE', 'CUBIERTA', 'RECHAZADA'], 'default' => 'PENDIENTE'],
            'reemplazo_asignacion_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'resuelto_por'            => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'resuelto_at'             => ['type' => 'DATETIME', 'null' => true],
            'observacion_admin'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['asignacion_id', 'estado'], false, false, 'idx_ausencias_asignacion_estado');
        $this->forge->addKey('estado', false, false, 'idx_ausencias_estado');
        $this->forge->addKey('usuario_id', false, false, 'idx_ausencias_usuario');
        $this->forge->addKey('reemplazo_asignacion_id', false, false, 'idx_ausencias_reemplazo');
        $this->forge->addForeignKey('asignacion_id', 'asignaciones', 'id', '', '', 'fk_ausencias_asignacion');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', '', '', 'fk_ausencias_usuario');
        $this->forge->addForeignKey('reemplazo_asignacion_id', 'asignaciones', 'id', '', '', 'fk_ausencias_reemplazo');
        $this->forge->addForeignKey('resuelto_por', 'usuarios', 'id', '', '', 'fk_ausencias_resuelto_por');
        $this->forge->createTable('ausencias', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        // La tabla de ausencias referencia a asignaciones: se quita primero.
        $this->forge->dropTable('ausencias', true);

        foreach (['observaciones_pase', 'checkout_at', 'checkin_at'] as $campo) {
            if ($this->db->fieldExists($campo, 'asignaciones')) {
                $this->forge->dropColumn('asignaciones', $campo);
            }
        }
    }
}
