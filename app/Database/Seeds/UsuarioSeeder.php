<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Usuarios de prueba (los mismos que crea database/turnoplaner.sql).
 *
 * Como no hay registro público, sin al menos un ADMIN el sistema no se puede usar.
 * Uso: C:\xampp\php\php.exe spark migrate && C:\xampp\php\php.exe spark db:seed UsuarioSeeder
 *
 * Contraseñas solo para desarrollo:
 *   admin@turnoplaner.local     -> admin123
 *   enfermera@turnoplaner.local -> enfermero123
 *   nuevo@turnoplaner.local     -> enfermero123 (debe activar la cuenta)
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = date('Y-m-d H:i:s');

        $usuarios = [
            [
                'nombre' => 'Admin', 'apellido' => 'Sistema', 'dni' => '20000000', 'matricula' => null,
                'email' => 'admin@turnoplaner.local', 'telefono' => '2920000000', 'domicilio' => null,
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT), 'rol' => 'ADMIN',
                'especialidad' => null, 'carga_horaria_max' => null, 'estado' => 'ACTIVO', 'debe_cambiar_pass' => 0,
            ],
            [
                'nombre' => 'Maria', 'apellido' => 'Gomez', 'dni' => '30123456', 'matricula' => 'MP-1001',
                'email' => 'enfermera@turnoplaner.local', 'telefono' => '2920111111', 'domicilio' => 'Mitre 123, Viedma',
                'password_hash' => password_hash('enfermero123', PASSWORD_DEFAULT), 'rol' => 'ENFERMERO',
                'especialidad' => 'Cuidados Críticos / Terapia Intensiva (UTI)', 'carga_horaria_max' => 36,
                'estado' => 'ACTIVO', 'debe_cambiar_pass' => 0,
            ],
            [
                'nombre' => 'Juan', 'apellido' => 'Perez', 'dni' => '31234567', 'matricula' => 'MP-1002',
                'email' => 'nuevo@turnoplaner.local', 'telefono' => null, 'domicilio' => null,
                'password_hash' => password_hash('enfermero123', PASSWORD_DEFAULT), 'rol' => 'ENFERMERO',
                'especialidad' => 'Guardia y Emergencias', 'carga_horaria_max' => 40,
                'estado' => 'ACTIVO', 'debe_cambiar_pass' => 1,
            ],
        ];

        foreach ($usuarios as $usuario) {
            // Idempotente: si el DNI ya existe no lo duplica.
            if ($this->db->table('usuarios')->where('dni', $usuario['dni'])->countAllResults() === 0) {
                $this->db->table('usuarios')->insert($usuario + ['created_at' => $ahora, 'updated_at' => $ahora]);
            }
        }
    }
}
