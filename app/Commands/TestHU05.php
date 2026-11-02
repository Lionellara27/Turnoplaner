<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Entities\Usuario;
use App\Models\UsuarioModel;
use Config\Database;
use Config\Services;
use Config\App;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;

class TestHU05 extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:hu05';
    protected $description = 'Verificación automatizada del corte vertical de HU-05 (Alta de enfermero/a)';

    public function run(array $params): void
    {
        CLI::write("=== INICIANDO AUDITORIA TECNICA VERTICAL HU-05 ===", 'green');

        $model = new UsuarioModel();
        $db = Database::connect();

        // 1. Limpieza de datos residuales de prueba
        $db->table('usuarios')->where('dni', '35999888')->delete();

        // 2. Test Entidad Usuario y Mutadores
        CLI::write("\n[1/6] Verificando Entidad Usuario y Hashing Seguro...", 'yellow');
        $usuario = new Usuario();
        $usuario->nombre            = 'Lucia';
        $usuario->apellido          = 'Fernandez';
        $usuario->dni               = '35999888';
        $usuario->matricula         = 'MP-TEST-999';
        $usuario->email             = 'lucia.test@hospital.gob.ar';
        $usuario->telefono          = '2920445566';
        $usuario->especialidad      = 'Cuidados Críticos / Terapia Intensiva (UTI)';
        $usuario->carga_horaria_max = 36;
        $usuario->rol               = 'ENFERMERO';
        $usuario->estado            = 'ACTIVO';
        $usuario->debe_cambiar_pass = 1;
        
        $claveTemporal = 'TempClave123!';
        $usuario->setPassword($claveTemporal);

        if (! password_verify($claveTemporal, $usuario->password_hash)) {
            CLI::error("ERROR: setPassword() no generó un hash verificable con password_verify().");
            return;
        }
        if ($usuario->getNombreCompleto() !== 'Fernandez, Lucia') {
            CLI::error("ERROR: getNombreCompleto() retorno formato inesperado: " . $usuario->getNombreCompleto());
            return;
        }
        if (! $usuario->debeCambiarPassword() || ! $usuario->estaActivo()) {
            CLI::error("ERROR: Metodos de estado de entidad no respondieron segun los atributos.");
            return;
        }
        CLI::write("  -> Entidad, accesores y mutador password_hash() validados correctamente.", 'green');

        // 3. Test Inserción y Persistencia
        CLI::write("\n[2/6] Verificando Persistencia en BD (UsuarioModel)...", 'yellow');
        $insertId = $model->insert($usuario);
        if (! $insertId) {
            CLI::error("ERROR al guardar usuario: " . print_r($model->errors(), true));
            return;
        }
        CLI::write("  -> Usuario insertado exitosamente con ID: {$insertId}", 'green');

        // 4. Test Recuperación de Registro y Criterios HU-05
        CLI::write("\n[3/6] Verificando Criterios de Aceptación en Registro Guardado...", 'yellow');
        /** @var Usuario|null $recuperado */
        $recuperado = $model->find($insertId);
        if (! $recuperado) {
            CLI::error("ERROR: No se pudo recuperar el usuario recién insertado.");
            return;
        }
        CLI::write("  - Estado inicial: {$recuperado->estado} (Esperado: ACTIVO)");
        CLI::write("  - Debe cambiar password: {$recuperado->debe_cambiar_pass} (Esperado: 1)");
        CLI::write("  - Carga horaria semanal: {$recuperado->carga_horaria_max} hs (Esperado: 36)");
        CLI::write("  - Especialidad: {$recuperado->especialidad}");
        CLI::write("  - Matrícula: {$recuperado->matricula}");

        if ($recuperado->estado !== 'ACTIVO' || $recuperado->debe_cambiar_pass !== 1 || $recuperado->carga_horaria_max !== 36) {
            CLI::error("ERROR: Los valores persistidos no coinciden con los criterios de HU-05.");
            return;
        }
        CLI::write("  -> Criterios de aceptación de legajo cumplidos al 100%.", 'green');

        // 5. Test Validaciones de Unicidad (DNI, Matrícula, Email)
        CLI::write("\n[4/6] Verificando Validación de Unicidad y Mensajes Específicos...", 'yellow');
        $duplicado = new Usuario([
            'nombre'            => 'Valeria',
            'apellido'          => 'Gomez',
            'dni'               => '35999888', // Duplicado
            'matricula'         => 'MP-TEST-999', // Duplicado
            'email'             => 'lucia.test@hospital.gob.ar', // Duplicado
            'carga_horaria_max' => 40,
            'rol'               => 'ENFERMERO',
            'estado'            => 'ACTIVO',
        ]);
        $duplicado->setPassword('OtraClave456!');

        $resultadoDuplicado = $model->save($duplicado);
        if ($resultadoDuplicado !== false) {
            CLI::error("ERROR: El modelo permitió guardar registros con DNI, Matrícula y Email duplicados.");
            return;
        }

        $errores = $model->errors();
        CLI::write("  -> Detección de duplicados exitosa. Errores emitidos por el modelo:");
        foreach ($errores as $campo => $msg) {
            CLI::write("     * [{$campo}]: {$msg}", 'cyan');
        }

        if (! isset($errores['dni']) || ! isset($errores['matricula']) || ! isset($errores['email'])) {
            CLI::error("ERROR: Faltan errores específicos para campos duplicados.");
            return;
        }
        CLI::write("  -> Validación de unicidad triple (DNI, Matrícula, Email) superada.", 'green');

        // 6. Test Renderizado de Vistas
        CLI::write("\n[5/6] Verificando Renderizado W3C y Helpers en Vistas...", 'yellow');
        $originalRequest = Services::request();
        $siteURI = new SiteURI(new App(), 'admin/usuarios/crear');
        $mockRequest = new IncomingRequest(new App(), $siteURI, null, new UserAgent());
        Services::injectMock('request', $mockRequest);

        $viewCreate = view('admin/users/create', ['titulo' => 'Alta de Enfermero/a']);
        if (! str_contains($viewCreate, 'name="carga_horaria_max"') || ! str_contains($viewCreate, 'csrf_test_name')) {
            CLI::error("ERROR: La vista create.php no contiene los campos requeridos o CSRF.");
            return;
        }

        $viewIndex = view('admin/users/index', ['titulo' => 'Personal', 'usuarios' => $model->findAll()]);
        if (! str_contains($viewIndex, 'Gestión de Personal de Enfermería')) {
            CLI::error("ERROR: La vista index.php no renderizó el encabezado esperado.");
            return;
        }
        Services::injectMock('request', $originalRequest);
        CLI::write("  -> Vistas create.php e index.php renderizan sin errores y con CSRF.", 'green');

        // 7. Test Baja Lógica Estricta (Soft Delete)
        CLI::write("\n[6/6] Verificando Baja Lógica Estricta (\$useSoftDeletes y estado='BAJA')...", 'yellow');
        $bajaOk = $model->darDeBajaLogica((int) $insertId);
        if (! $bajaOk) {
            CLI::error("ERROR: Falló el método darDeBajaLogica().");
            return;
        }

        // En búsqueda activa, find() no debe retornar nada debido a soft delete
        $busquedaActivo = $model->find($insertId);
        if ($busquedaActivo !== null) {
            CLI::error("ERROR: El usuario dado de baja sigue apareciendo en consultas estándar find().");
            return;
        }

        // Con withDeleted(), debe encontrarse con estado='BAJA' y deleted_at IS NOT NULL
        /** @var Usuario|null $registroBaja */
        $registroBaja = $model->withDeleted()->find($insertId);
        if (! $registroBaja) {
            CLI::error("ERROR: El registro fue eliminado físicamente en lugar de soft deleted!");
            return;
        }
        if ($registroBaja->estado !== 'BAJA') {
            CLI::error("ERROR: El campo estado no se actualizó a 'BAJA'.");
            return;
        }
        if (empty($registroBaja->deleted_at)) {
            CLI::error("ERROR: deleted_at no fue poblado en el soft delete.");
            return;
        }
        CLI::write("  - Estado tras baja: {$registroBaja->estado}");
        CLI::write("  - deleted_at: {$registroBaja->deleted_at}");
        CLI::write("  -> Baja lógica estricta comprobada. Sin DELETE físico.", 'green');

        // Limpieza final del registro de prueba
        $db->table('usuarios')->where('id', $insertId)->delete();

        CLI::write("\n=======================================================", 'green');
        CLI::write(">>> CORTE VERTICAL HU-05 AUDITADO Y 100% OPERATIVO <<<", 'green');
        CLI::write("=======================================================\n", 'green');
    }
}
