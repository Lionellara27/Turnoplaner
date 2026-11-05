# Seguridad, Autenticación y Filtros en CodeIgniter 4

Esta guía técnica describe los lineamientos y reglas de seguridad obligatorias para **TurnoPlaner**, incluyendo filtros de sesión, control de acceso basado en roles (RBAC), hashing de contraseñas y mitigación contra ataques CSRF y XSS.

> **Fuente de verdad del código:** esta skill describe reglas y explica el porqué. La implementación vigente está en los archivos citados en cada sección; ante una duda, leer el archivo antes de copiar un ejemplo.

---

## 0. Reglas de negocio de acceso

1. **No existe registro público.** Las cuentas las crea el ADMIN (HU-05) con una contraseña temporal y `debe_cambiar_pass = 1`. No crear rutas públicas que inserten usuarios.
2. **Estados de usuario:** `ACTIVO` | `BAJA`. La baja es lógica: `estado = 'BAJA'` + `deleted_at` (soft delete). El estado "Pendiente de activación" no es una columna: es `ACTIVO` con `debe_cambiar_pass = 1`.
3. **Primer ingreso (HU-01):** con `debe_cambiar_pass = 1` el usuario solo puede entrar a `activar-cuenta`; ahí define su contraseña y completa teléfono y domicilio.
4. **Columnas reales:** la tabla `usuarios` usa `email` (no `mail`) y `password_hash`.

---

## 1. Arquitectura de Filtros (`app/Config/Filters.php`)

Los filtros en CodeIgniter 4 actúan como middlewares que interceptan las peticiones antes (`before`) o después (`after`) de llegar al controlador.

### Aliases

```php
public array $aliases = [
    // ...los del framework...
    'auth' => \App\Filters\AuthFilter::class,
    'role' => \App\Filters\RoleFilter::class,
];
```

### Filtros globales (obligatorio)

```php
public array $globals = [
    'before' => [
        'csrf',          // todo POST sin token válido devuelve 403
        'invalidchars',  // rechaza caracteres de control y UTF-8 inválido
    ],
    'after' => [
        'toolbar',
        'secureheaders', // X-Frame-Options, etc.: evita clickjacking de las acciones del admin
    ],
];
```

**Verificación:** `curl -d "identificador=x&password=y" http://localhost:8080/login` debe responder **403**. Si responde otra cosa, el CSRF global está apagado.

---

## 2. Filtro de Autenticación (`app/Filters/AuthFilter.php`)

En **cada pedido** a una ruta con `'filter' => 'auth'`:

1. Exige `session('logueado')`; si no, redirige a `login` con un mensaje.
2. **Relee el usuario de la base** (`withDeleted()->find(usuario_id)`). Si no existe o está de `BAJA`, cierra la sesión: un enfermero dado de baja con la sesión abierta queda afuera en el siguiente clic.
3. **Compara la huella de credenciales** (`Usuario::huellaCredenciales()`, un SHA-256 recortado de `password_hash`) con la guardada en la sesión. Si la contraseña cambió (restablecimiento del admin, o cambio desde otro equipo), cierra la sesión. Sin esto, quien tenga una sesión vieja puede quedarse con la cuenta tras un restablecimiento.
4. Si `debe_cambiar_pass = 1`, solo deja pasar a `activar-cuenta` (`url_is('activar-cuenta')`).

**Cerrar sesión sin perder el mensaje:** usar `App\Libraries\SesionUsuario::cerrar()` (quita las claves y regenera el ID). **No** usar `session()->destroy()` antes de `->with('error', ...)`: el flashdata se escribe en una sesión ya destruida y el mensaje nunca aparece.

---

## 3. Control de Acceso Basado en Roles — RBAC (`app/Filters/RoleFilter.php`)

Asegura que los usuarios solo accedan a los recursos de su perfil (`ADMIN` o `ENFERMERO`). Si el rol no coincide, redirige al inicio de **su** rol (`inicio` para ADMIN, `mi-cronograma` para ENFERMERO) con el mensaje "Acceso denegado".

### Aplicación en Rutas (`app/Config/Routes.php`)

```php
// IDs: comodín propio de hasta 9 dígitos. Con (:num), un ID enorme llega como int fuera de rango y da error 500.
$routes->addPlaceholder('id', '[0-9]{1,9}');

// Solo Administradores ('auth' SIEMPRE antes que 'role')
$routes->group('admin', ['filter' => ['auth', 'role:ADMIN']], static function ($routes) {
    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios/(:id)/editar', 'Usuarios::editar/$1');
    $routes->post('usuarios/(:id)/restablecer', 'Usuarios::restablecer/$1');
});

// Portal de Enfermería
$routes->group('', ['filter' => ['auth', 'role:ENFERMERO']], static function ($routes) {
    $routes->get('mi-cronograma', 'Cronograma::index');
});

// Cerrar sesión: POST con CSRF (con GET, cualquier link externo puede desloguear al usuario)
$routes->post('logout', 'Auth::logout');
```

Las acciones que modifican datos (baja, reactivar, restablecer) son siempre **POST** con `csrf_field()`, nunca links GET.

---

## 4. Contraseñas y Login

### Reglas obligatorias

1. **Hash:** `password_hash($password, PASSWORD_DEFAULT)` vía `Usuario::setPassword()`. Prohibido `md5()`, `sha1()` o texto plano.
2. **Verificación:** `password_verify($ingresada, $usuario->password_hash)`.
3. **Política de contraseña nueva:** mínimo 8 caracteres, con letras y números (`BaseController::reglasNuevaPassword()`), distinta de la anterior/temporal.
4. **Regeneración de sesión:** al iniciar sesión y al cambiar la propia contraseña (`SesionUsuario::iniciar()` y `renovarCredenciales()`), contra la fijación de sesión.
5. **Contraseñas fuera de la sesión:** `redirect()->withInput()` guarda **todo el POST** (incluidas las contraseñas) en los archivos de `writable/session/`. En formularios con contraseñas usar `BaseController::volverConErrores()`, que vuelve con lo cargado **excepto** `password`, `password_confirm` y `password_actual`.
6. **Las contraseñas no se recortan (`trim`)**; el resto de los campos sí, **antes** de validar (`datosRecortados()` + `validateData()`).

### Flujo de `Auth::doLogin()` (ver `app/Controllers/Auth.php`)

1. Normaliza el identificador con `UsuarioModel::normalizarIdentificador()`: un DNI con puntos ("30.123.456") queda "30123456"; un email queda en minúsculas.
2. **Límite de intentos** con `service('throttler')`: 5 por minuto por **cuenta + IP** y 30 por minuto por IP. No limitar solo por IP: en un hospital muchas PCs salen con la misma IP pública y una enfermera que se equivoca bloquearía a todo el turno.
3. Busca con `buscarPorIdentificador()` (incluye bajas, para poder decir "cuenta inactiva").
4. **Siempre** ejecuta `password_verify`, contra un hash de relleno si el usuario no existe: así la respuesta tarda lo mismo y no se puede averiguar qué cuentas existen.
5. Mismo mensaje para usuario inexistente o clave incorrecta: "Usuario o contraseña incorrectos."
6. Cuenta en BAJA: "Cuenta inactiva o dada de baja. Contacte a la administración."
7. `SesionUsuario::iniciar($usuario)` y redirección: `activar-cuenta` si `debe_cambiar_pass = 1`; si no, `inicio` (ADMIN) o `mi-cronograma` (ENFERMERO).

El cambio de contraseña desde "Mi perfil" exige la contraseña actual y también tiene límite de intentos (una sesión robada no debe permitir adivinarla).

---

## 5. Protección contra CSRF (Cross-Site Request Forgery)

1. **Formularios HTML:** todo formulario POST incluye `<?= csrf_field() ?>`, también los de los modales y el de cerrar sesión.
2. **El token se regenera en cada envío** (`Config\Security::$regenerate = true`): un segundo clic sobre el mismo formulario da 403. Por eso todo formulario POST lleva `data-enviando` (ver `public/js/app.js`), que deshabilita el botón al enviar.
3. **Peticiones AJAX / jQuery:** un archivo `.js` estático **no ejecuta PHP**, así que `<?= csrf_hash() ?>` dentro de `app.js` no funciona. Publicar el token en el layout y leerlo desde JS:

```php
<!-- app/Views/layouts/main.php, dentro de <head> -->
<meta name="csrf-nombre" content="<?= csrf_header() ?>">
<meta name="csrf-token" content="<?= csrf_hash() ?>">
```

```javascript
// public/js/app.js
var nombre = document.querySelector('meta[name="csrf-nombre"]').content;
var token  = document.querySelector('meta[name="csrf-token"]').content;
$.ajaxSetup({ headers: { 'X-Requested-With': 'XMLHttpRequest', [nombre]: token } });
// Como el token se regenera, el controlador debe devolver csrf_hash() en cada respuesta JSON
// y el JS debe actualizar el meta antes del siguiente pedido.
```

---

## 6. Prevención de XSS e Inyección SQL

1. **Escape en Vistas:** toda variable dinámica se imprime con `esc()`:
   ```php
   <span><?= esc($usuario->nombre) ?></span>
   ```
2. **Escapar una sola vez:** `old()` **ya escapa** por defecto. Para repoblar un campo usar `esc(old('campo', $defecto, false))`. Con `esc(old('campo'))` el valor se escapa dos veces y un apellido como "O'Brien" se guarda como `O&#039;Brien` en el siguiente envío.
3. **Consultas a Base de Datos:** usar exclusivamente el Query Builder de CodeIgniter 4 (`where()`, `like()`, `insert()`, etc.), que parametriza los valores. Para búsquedas por varias palabras, un `groupStart()` por palabra; no armar SQL con `CONCAT` ni concatenar texto del usuario.
4. **Listas cerradas:** filtros y selects (estado, especialidad) se validan contra la lista permitida (`in_list[...]` o `in_array` contra `UsuarioModel::ESPECIALIDADES`); un valor desconocido se ignora.

---

## 7. Verificación

- Pruebas de punta a punta: `bash tests/e2e/turnoplaner_e2e.sh` (reinicia la base local). Cubren CSRF, roles, primer ingreso, corte de sesión por baja y por restablecimiento, límite de intentos, doble escape y que las contraseñas no queden en `writable/session/`.
- Toda HU nueva que toque autenticación o permisos suma sus casos a ese script.
