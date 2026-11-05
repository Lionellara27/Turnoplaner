<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Carga todos los datos de prueba: usuarios, tipos de turno y sectores.
 * Uso: C:\xampp\php\php.exe spark db:seed TurnoplanerSeeder
 */
class TurnoplanerSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('UsuarioSeeder');
        $this->call('CatalogosSeeder');
    }
}
