<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * HU-01: el enfermero/a completa su domicilio en el primer ingreso.
 */
class AddDomicilioToUsuarios extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('domicilio', 'usuarios')) {
            $this->forge->addColumn('usuarios', [
                'domicilio' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                    'after'      => 'telefono',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('domicilio', 'usuarios')) {
            $this->forge->dropColumn('usuarios', 'domicilio');
        }
    }
}
