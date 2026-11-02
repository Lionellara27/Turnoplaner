<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsuariosTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('usuarios')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'nombre' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'apellido' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'dni' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                ],
                'matricula' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'telefono' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                ],
                'password_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'rol' => [
                    'type'       => 'ENUM',
                    'constraint' => ['ADMIN', 'ENFERMERO'],
                    'default'    => 'ENFERMERO',
                ],
                'especialidad' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'carga_horaria_max' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                    'null'       => true,
                    'default'    => 40,
                ],
                'estado' => [
                    'type'       => 'ENUM',
                    'constraint' => ['ACTIVO', 'BAJA', 'PENDIENTE'],
                    'default'    => 'ACTIVO',
                ],
                'debe_cambiar_pass' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                ],
                'updated_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                ],
                'deleted_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('dni', 'uq_usuarios_dni');
            $this->forge->addUniqueKey('matricula', 'uq_usuarios_matricula');
            $this->forge->addUniqueKey('email', 'uq_usuarios_email');
            $this->forge->addKey('estado', false, false, 'idx_usuarios_estado');
            $this->forge->addKey('rol', false, false, 'idx_usuarios_rol');

            $this->forge->createTable('usuarios', true, ['ENGINE' => 'InnoDB']);
        } else {
            // Si la tabla ya fue creada previamente, asegurarse de contar con deleted_at para soft deletes
            if (! $this->db->fieldExists('deleted_at', 'usuarios')) {
                $this->forge->addColumn('usuarios', [
                    'deleted_at' => [
                        'type'    => 'DATETIME',
                        'null'    => true,
                        'default' => null,
                        'after'   => 'updated_at',
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('usuarios')) {
            $this->forge->dropTable('usuarios', true);
        }
    }
}
