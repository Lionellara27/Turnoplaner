# Arquitectura de Software en CodeIgniter 4 (PHP 8.1+)

Esta guía técnica establece las reglas arquitectónicas y de desarrollo obligatorias para el proyecto **TurnoPlaner** sobre el framework **CodeIgniter 4** utilizando características modernas de **PHP 8.1+**.

---

## 1. Principios de Separación en Capas

El proyecto adopta estrictamente el patrón **MVC enriquecido con Entidades (MVC + Entities)**:
- **Rutas (`Config/Routes.php`):** Declaran los endpoints explícitos, verbos HTTP y asocian filtros.
- **Controladores (`app/Controllers/`):** Controladores delgados (*Thin Controllers*). Solo orquestan la petición HTTP, validan la entrada y delegan la persistencia a los Modelos.
- **Modelos (`app/Models/`):** Encapsulan el acceso a datos mediante el *Query Builder* de CodeIgniter 4 y gestionan la lógica de persistencia, validaciones de entidad y bajas lógicas.
- **Entidades (`app/Entities/`):** Representan los objetos del dominio con tipado estricto, mutadores, accesores y reglas de negocio del modelo de datos.
- **Vistas (`app/Views/`):** Fragmentos de presentación reutilizables que heredan de plantillas base (*Layouts*). Queda prohibida la ejecución de consultas SQL o lógica pesada dentro de las vistas.

---

## 2. Enrutamiento Estricto (`app/Config/Routes.php`)

1. **Auto-routing desactivado:** No depender del enrutamiento automático. Todas las rutas deben declararse explícitamente.
2. **Definición de verbos HTTP:** Usar `$routes->get()`, `$routes->post()`, `$routes->put()`, `$routes->delete()`.
3. **Agrupación y Filtros:** Emplear grupos de rutas para modularizar y aplicar filtros de seguridad (*RBAC*):

```php
// Rutas públicas de autenticación
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::doLogin');
$routes->get('logout', 'Auth::logout');

// Rutas protegidas para usuarios autenticados
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('inicio', 'Home::index');
    $routes->get('perfil', 'Usuarios::perfil');
    $routes->post('perfil', 'Usuarios::guardarPerfil');
});

// Rutas de administración (Filtro auth + role:ADMIN)
$routes->group('admin', ['filter' => ['auth', 'role:ADMIN']], static function ($routes) {
    // ABMC de Personal
    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios/crear', 'Usuarios::crear');
    $routes->post('usuarios/crear', 'Usuarios::guardar');
    $routes->get('usuarios/editar/(:num)', 'Usuarios::editar/$1');
    $routes->post('usuarios/editar/(:num)', 'Usuarios::actualizar/$1');
    $routes->post('usuarios/baja/(:num)', 'Usuarios::darDeBaja/$1');

    // ABMC de Sectores
    $routes->get('sectores', 'Sectores::index');
    // ABMC de Tipos de Turno
    $routes->get('turnos', 'TiposTurno::index');
    // Planificador Semanal
    $routes->get('planificador', 'Planificador::index');
});
```

---

## 3. Controladores Delgados (*Thin Controllers*)

### Reglas Obligatorias:
1. **Tipado Estricto:** Siempre incluir `declare(strict_types=1);` y tipar parámetros y valores de retorno (`string|ResponseInterface`).
2. **Obtención segura de datos:**
   - Para POST: `$this->request->getPost('campo')` o `$this->request->getPost()` saneado.
   - Para GET/Query String: `$this->request->getGet('filtro')`.
   - Para payloads JSON (AJAX): `$this->request->getJSON(assoc: true)`.
   - **PROHIBIDO:** Usar superglobales directas `$_POST`, `$_GET`, `$_REQUEST` o `$_SESSION`.
3. **Validación en el Controlador o Modelo:**
   - Emplear `$this->validate($reglas)` antes de procesar la petición.
   - En caso de error, retornar redirección con datos de entrada y errores de validación:
     ```php
     if (! $this->validate($rules)) {
         return $this->volverConErrores($this->validator->getErrors()); // no guarda contraseñas en sesión
     }
     ```
4. **Respuestas:**
   - Renderizado de vistas: `return view('carpeta/vista', $data);`.
   - Redirecciones: `return redirect()->to(base_url('ruta'))->with('success', 'Mensaje');`.
   - Respuestas API/AJAX: `return $this->response->setJSON(['status' => 'ok', 'data' => $resultado]);`.

### Ejemplo de Controlador Estándar:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use App\Entities\Usuario;
use CodeIgniter\HTTP\ResponseInterface;

class Usuarios extends BaseController
{
    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(): string
    {
        $data = [
            'titulo'   => 'Gestión de Personal de Enfermería',
            'usuarios' => $this->usuarioModel->obtenerActivosConFiltros(
                estado: $this->request->getGet('estado'),
                busqueda: $this->request->getGet('q')
            ),
        ];

        return view('usuarios/index', $data);
    }

    public function guardar(): ResponseInterface
    {
        $rules = [
            'nombre'           => 'required|min_length[2]|max_length[100]',
            'apellido'         => 'required|min_length[2]|max_length[100]',
            'dni'              => 'required|regex_match[/^[0-9]{7,10}$/]|is_unique[usuarios.dni]', // numeric acepta -1234567 y 1234567.5
            'matricula'        => 'required|alpha_numeric_space|is_unique[usuarios.matricula]',
            'mail'             => 'required|valid_email|is_unique[usuarios.mail]',
            'carga_horaria_max'=> 'required|is_natural_no_zero',
            'especialidad'     => 'permit_empty|string',
        ];

        if (! $this->validate($rules)) {
            return $this->volverConErrores($this->validator->getErrors()); // no guarda contraseñas en sesión
        }

        $usuario = new Usuario($this->request->getPost());
        // Lógica de inicio de cuenta: clave provisoria y bandera de cambio obligatorio
        $claveProvisoria = bin2hex(random_bytes(4)); // Ej: 'a3f89b1c'
        $usuario->setPassword($claveProvisoria);
        $usuario->debe_cambiar_pass = 1;
        $usuario->rol = 'ENFERMERO';
        $usuario->estado = 'ACTIVO';

        $this->usuarioModel->save($usuario);

        return redirect()->to(base_url('admin/usuarios'))
            ->with('success', "Enfermero/a dado de alta. Clave temporal asignada: {$claveProvisoria}");
    }
}
```

---

## 4. Modelos con Query Builder y Entidades (`app/Models/`)

### Reglas Obligatorias:
1. **Configuración del Modelo:**
   - `$table`: Nombre exacto de la tabla en base de datos.
   - `$primaryKey`: `id`.
   - `$returnType`: Clase de la entidad correspondiente (`App\Entities\...`).
   - `$useSoftDeletes`: `true` (Baja lógica estricta).
   - `$allowedFields`: Lista blanca exhaustiva de columnas editables mediante asignación masiva.
   - `$useTimestamps`: `true` (`created_at`, `updated_at`, `deleted_at`).
2. **Consultas Seguras con Query Builder:**
   - Encadenar métodos (`$this->where()`, `$this->join()`, `$this->orderBy()`).
   - Jamás interpolar variables directamente en cadenas SQL para evitar inyección SQL.

### Ejemplo de Modelo:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use App\Entities\Usuario;

class UsuarioModel extends Model
{
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Usuario::class;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nombre',
        'apellido',
        'dni',
        'matricula',
        'mail',
        'telefono',
        'password_hash',
        'rol',
        'especialidad',
        'carga_horaria_max',
        'estado',
        'debe_cambiar_pass',
    ];

    // Fechas
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function obtenerActivosConFiltros(?string $estado = null, ?string $busqueda = null): array
    {
        $builder = $this->builder();

        if ($estado !== null && $estado !== '') {
            $builder->where('estado', $estado);
        }

        if ($busqueda !== null && $busqueda !== '') {
            $builder->groupStart()
                ->like('nombre', $busqueda)
                ->orLike('apellido', $busqueda)
                ->orLike('dni', $busqueda)
                ->orLike('matricula', $busqueda)
            ->groupEnd();
        }

        return $this->where('deleted_at IS NULL')
                    ->orderBy('apellido', 'ASC')
                    ->findAll();
    }
}
```

---

## 5. Entidades de Dominio (`app/Entities/`)

Las Entidades permiten modelar datos limpios y encapsular lógica atómica de formato y reglas:

```php
<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Usuario extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'                => 'integer',
        'carga_horaria_max' => 'integer',
        'debe_cambiar_pass' => 'integer',
    ];

    /**
     * Mutador: Encripta la contraseña de forma automática
     */
    public function setPassword(string $pass): self
    {
        $this->attributes['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        return $this;
    }

    /**
     * Accesor: Nombre completo formateado
     */
    public function getNombreCompleto(): string
    {
        return trim("{$this->attributes['apellido']}, {$this->attributes['nombre']}");
    }

    public function esAdmin(): bool
    {
        return ($this->attributes['rol'] ?? '') === 'ADMIN';
    }

    public function debeCambiarPassword(): bool
    {
        return ((int) ($this->attributes['debe_cambiar_pass'] ?? 0)) === 1;
    }
}
```

---

## 6. Vistas y Jerarquía de Layouts (`app/Views/`)

1. **Heredar de Layout Base:** Toda vista debe abrir extendiendo la plantilla principal:
   ```php
   <?= $this->extend('layouts/main') ?>
   <?= $this->section('contenido') ?>
   <!-- Contenido HTML de la vista -->
   <?= $this->endSection() ?>
   ```
2. **Prohibición de Shell HTML:** No repetir etiquetas `<!DOCTYPE>`, `<html>`, `<head>`, `<body>` dentro de las vistas hijas.
3. **Escape obligatorio:** Todo dato dinámico renderizado debe escapar con `esc($variable)` para neutralizar XSS.
4. **Mensajería Estándar:** Renderizar siempre bloques de mensajes de alerta (`session()->getFlashdata('errors')`, `session()->getFlashdata('success')`).

---

## Patrones obligatorios del proyecto (revisión QA del 05/11/2026)

Utilidades de `app/Controllers/BaseController.php` que todo controlador nuevo debe usar:

| Método | Para qué |
| :--- | :--- |
| `datosRecortados()` | POST con los textos recortados. **Validar sobre esto** con `$this->validateData($datos, $reglas)`, no con `$this->validate()`: si no, "    x" pasa un `min_length[5]` y se guarda "x". Las contraseñas no se recortan. |
| `volverConErrores($errores, $destino = null, $tipo = 'errors')` | Reemplaza a `redirect()->back()->withInput()`. Vuelve con lo cargado **sin** contraseñas (`withInput()` guarda todo el POST en `writable/session/`). Con `$tipo = 'error'` muestra un mensaje general. |
| `reglaTelefono(bool)`, `reglaDomicilio(bool)`, `reglasNuevaPassword()` | Reglas compartidas: la misma validación en todas las pantallas que piden ese dato. |

Otras reglas:

1. **Rutas con ID:** usar el comodín `(:id)` (hasta 9 dígitos) definido en `Routes.php`, no `(:num)`: un número enorme con parámetro `int` da error 500 en vez de 404.
2. **Mensajes al usuario:** `success` para lo que se hizo, `info` para "no había cambios" o "ya estaba dado de baja", `error` para lo que falló. Nunca un mensaje de éxito cuando no se hizo nada.
3. **Acciones idempotentes:** antes de dar de baja o reactivar, verificar el estado actual; dos escrituras relacionadas van en una transacción (`transStart()` / `transComplete()`).
4. **Entity::hasChanged():** compara con `!==`. La base devuelve números como string (`numberNative = false`), así que al hacer `fill()` asignar strings (`(string) (int) $valor`) o siempre dará "hubo cambios".
5. **Vistas reutilizables por comandos o tests** (por ejemplo `spark test:hu05` renderiza `view('admin/users/create')` sin pasar todo): definir valores por defecto con `??` al principio de la vista.
6. **Helpers globales** de la interfaz en `app/Config/Autoload.php` (`$helpers = ['form_ui']`), no solo en el BaseController, así funcionan también en comandos `spark`.
