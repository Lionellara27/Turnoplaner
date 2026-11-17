<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHorasExtrasToAsignaciones extends Migration
{
    public function up(): void
    {
        $campos = [];

        if (! $this->db->fieldExists('es_extra', 'asignaciones')) {
            $campos['es_extra'] = [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'duracion_minutos',
            ];
        }

        if (! $this->db->fieldExists('minutos_extra', 'asignaciones')) {
            $campos['minutos_extra'] = [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'default'  => 0,
                'null'     => false,
                'after'    => 'es_extra',
            ];
        }

        if ($campos !== []) {
            $this->forge->addColumn('asignaciones', $campos);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('minutos_extra', 'asignaciones')) {
            $this->forge->dropColumn('asignaciones', 'minutos_extra');
        }
        if ($this->db->fieldExists('es_extra', 'asignaciones')) {
            $this->forge->dropColumn('asignaciones', 'es_extra');
        }
    }
}