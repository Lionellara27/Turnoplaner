# Seguridad, Autenticación y Filtros en CodeIgniter 4

Esta guía técnica describe los lineamientos y reglas de seguridad obligatorias para **TurnoPlaner**, incluyendo filtros de sesión, control de acceso basado en roles (RBAC), hashing de contraseñas y mitigación contra ataques CSRF y XSS.

---

## 1. Arquitectura de Filtros (`app/Config/Filters.php`)

Los filtros en CodeIgniter 4 actúan como middlewares que interceptan las peticiones antes (`before`) o después (`after`) de llegar al controlador.

### Configuración de Aliases en `app/Config/Filters.php`:

```php
public array $aliases = [
    'csrf'          => \CodeIgniter\Filters\CSRF::class,
    'toolbar'       => \CodeIgniter\Filters\DebugToolbar::class,
    'honeypot'      => \CodeIgniter\Filters\Honeypot::class,
    'invalidchars'  => \CodeIgniter\Filters\InvalidChars::class,
    'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
    'auth'          => \App\Filters\AuthFilter::class,
    'role'          => \App\Filters\RoleFilter::class,
];
```

### Configuración Global de Seguridad:
Habilitar `csrf` e `invalidchars` en las peticiones globales entrantes:

```php
public array $globals = [
    'before' => [
        'csrf',
        'invalidchars',
    ],
    'after' => [
        'toolbar',
        'secureheaders',
    ],
];
```

---

## 2. Filtro de Autenticación (`AuthFilter.php`)

El filtro `AuthFilter` protege las rutas privadas, valida la existencia de sesión activa y gestiona el flujo de **primer acceso / cambio obligatorio de contraseña (HU-01 y HU-02)**.

```php
<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // 1. Verificar si el usuario está autenticado
        if (! $session->get('logueado')) {
            $session->setFlashdata('error', 'Debe iniciar sesión para acceder al sistema.');
            return redirect()->to(base_url('login'));
        }

        // 2. Interceptación de Primer Acceso (HU-01: debe_cambiar_pass == 1)
        if ((int) $session->get('debe_cambiar_pass') === 1) {
            $uriActual = trim($request->getUri()->getPath(), '/');
            
            // Rutas permitidas durante el estado de activación
            $rutasPermitidas = ['activar-cuenta', 'do-activar-cuenta', 'logout'];

            if (! in_array($uriActual, $rutasPermitidas, true)) {
                $session->setFlashdata('error', 'Por seguridad, debe activar su cuenta y cambiar su contraseña antes de continuar.');
                return redirect()->to(base_url('activar-cuenta'));
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action required after
    }
}
```

---

## 3. Control de Acceso Basado en Roles — RBAC (`RoleFilter.php`)

Asegura que los usuarios solo accedan a los recursos permitidos para su perfil (`ADMIN` o `ENFERMERO`).

```php
<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $rolUsuario = $session->get('rol');

        if (! $session->get('logueado') || empty($rolUsuario)) {
            return redirect()->to(base_url('login'));
        }

        // Argumentos pasados en la ruta, ej: ['ADMIN'] o ['ADMIN', 'ENFERMERO']
        $rolesPermitidos = $arguments ?? [];

        if (! empty($rolesPermitidos) && ! in_array($rolUsuario, $rolesPermitidos, true)) {
            $session->setFlashdata('error', 'Acceso denegado: No posee los permisos requeridos para esta sección.');
            
            // Redirige según el rol actual del usuario
            return ($rolUsuario === 'ADMIN')
                ? redirect()->to(base_url('inicio'))
                : redirect()->to(base_url('mi-cronograma'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
```

### Aplicación en Rutas:

```php
// Solo Administradores
$routes->group('admin', ['filter' => ['auth', 'role:ADMIN']], static function ($routes) {
    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('sectores', 'Sectores::index');
    $routes->get('planificador', 'Planificador::index');
});

// Portal de Enfermería
$routes->group('', ['filter' => ['auth', 'role:ENFERMERO']], static function ($routes) {
    $routes->get('mi-cronograma', 'Cronograma::index');
    $routes->post('ausencias/notificar', 'Ausencias::crear');
});
```

---

## 4. Hashing Seguro de Contraseñas

### Reglas Obligatorias:
1. **Algoritmo Estándar:** Usar siempre `password_hash($password, PASSWORD_DEFAULT)` de PHP. En PHP 8.1+ esto utiliza algoritmos BCRYPT robustos con salt generado criptográficamente.
2. **Prohibición:** Jamás utilizar algoritmos obsoletos o rotos como `md5()`, `sha1()` o texto plano.
3. **Verificación:** Comparar exclusivamente con `password_verify($passwordIngresada, $usuario->password_hash)`.
4. **Regeneración de Sesión:** Tras validar la contraseña con éxito, invocar inmediatamente `session()->regenerate(true)` para prevenir ataques de fijación de sesión (*Session Fixation*).

### Ejemplo de Flujo de Login en Controlador (`Auth::doLogin`):

```php
public function doLogin(): ResponseInterface
{
    $identifier = trim((string) $this->request->getPost('identificador')); // DNI o Email
    $password   = (string) $this->request->getPost('password');

    if ($identifier === '' || $password === '') {
        return redirect()->back()->withInput()->with('error', 'Ingrese su usuario/correo y contraseña.');
    }

    $usuarioModel = new UsuarioModel();
    // Buscar por Email o DNI
    $usuario = $usuarioModel->groupStart()
                                ->where('mail', $identifier)
                                ->orWhere('dni', $identifier)
                            ->groupEnd()
                            ->first();

    if (! $usuario || ! password_verify($password, $usuario->password_hash)) {
        return redirect()->back()->withInput()->with('error', 'Credenciales inválidas.');
    }

    if ($usuario->estado === 'BAJA') {
        return redirect()->back()->with('error', 'Cuenta inactiva o dada de baja. Contacte a la administración.');
    }

    // Regenerar ID de sesión para máxima seguridad
    session()->regenerate(true);

    // Cargar datos en sesión
    session()->set([
        'logueado'          => true,
        'usuario_id'        => $usuario->id,
        'nombre'            => $usuario->nombre,
        'apellido'          => $usuario->apellido,
        'email'             => $usuario->mail,
        'rol'               => $usuario->rol,
        'debe_cambiar_pass' => (int) $usuario->debe_cambiar_pass,
    ]);

    // Redirección condicional
    if ((int) $usuario->debe_cambiar_pass === 1) {
        return redirect()->to(base_url('activar-cuenta'));
    }

    return ($usuario->rol === 'ADMIN')
        ? redirect()->to(base_url('inicio'))
        : redirect()->to(base_url('mi-cronograma'));
}
```

---

## 5. Protección contra CSRF (Cross-Site Request Forgery)

1. **Formularios HTML:** Todo formulario que envíe peticiones POST/PUT/DELETE debe incluir el campo oculto de token CSRF:
   ```html
   <form action="<?= base_url('admin/usuarios/crear') ?>" method="post">
       <?= csrf_field() ?>
       <!-- campos del formulario -->
   </form>
   ```
2. **Peticiones AJAX / jQuery:** Enviar el token CSRF en los encabezados HTTP o en la carga de datos. Configuración recomendada en `public/js/app.js`:

```javascript
// Configuración global de AJAX con token CSRF de CodeIgniter 4
$.ajaxSetup({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
    }
});
```

---

## 6. Prevención de XSS e Inyección SQL

1. **Escape en Vistas:** Toda variable dinámica mostrada en una vista debe envolverse con `esc()`:
   ```html
   <span><?= esc($usuario->nombre) ?></span>
   ```
2. **Consultas a Base de Datos:** Usar exclusivamente los métodos del Query Builder de CodeIgniter 4 (`where()`, `like()`, `insert()`, etc.) que parametrizan automáticamente los valores mediante PDO/MySQLi.

