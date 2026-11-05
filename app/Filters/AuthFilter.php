<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\SesionUsuario;
use App\Models\UsuarioModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro de autenticación (HU-01, HU-02 y HU-04).
 *
 * 1. Exige sesión iniciada.
 * 2. Verifica contra la base, en cada pedido, que la cuenta siga ACTIVA:
 *    si el admin la dio de baja con la sesión abierta, se cierra en el siguiente clic.
 * 3. Verifica que la contraseña no haya cambiado desde que se abrió la sesión
 *    (huella de credenciales). Si el admin la restableció, o el usuario la cambió
 *    desde otro equipo, las sesiones viejas se cierran.
 * 4. Con debe_cambiar_pass = 1 (primer ingreso o clave restablecida) solo deja
 *    entrar a la activación de cuenta.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('logueado')) {
            return redirect()->to(base_url('login'))
                ->with('error', 'Debe iniciar sesión para acceder al sistema.');
        }

        $usuario = (new UsuarioModel())->withDeleted()->find((int) $session->get('usuario_id'));

        if ($usuario === null || ! $usuario->estaActivo()) {
            SesionUsuario::cerrar();

            return redirect()->to(base_url('login'))
                ->with('error', 'Cuenta inactiva o dada de baja. Contacte a la administración.');
        }

        if (! hash_equals($usuario->huellaCredenciales(), (string) $session->get('huella'))) {
            SesionUsuario::cerrar();

            return redirect()->to(base_url('login'))
                ->with('error', 'Su contraseña cambió. Por seguridad, vuelva a iniciar sesión.');
        }

        $session->set('debe_cambiar_pass', $usuario->debeCambiarPassword() ? 1 : 0);

        if ($usuario->debeCambiarPassword() && ! url_is('activar-cuenta')) {
            return redirect()->to(base_url('activar-cuenta'))
                ->with('error', 'Por seguridad, debe activar su cuenta y definir su contraseña antes de continuar.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Sin acciones posteriores.
    }
}
