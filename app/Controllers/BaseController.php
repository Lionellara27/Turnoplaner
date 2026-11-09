<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        $this->helpers = ['form', 'url', 'form_ui'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Parámetro GET siempre como texto. Si llega como arreglo (?vista[]=mes) se ignora:
     * castear un arreglo a string tira "Array to string conversion" y un error 500.
     */
    protected function textoGet(string $clave): string
    {
        $valor = $this->request->getGet($clave);

        return is_string($valor) ? trim($valor) : '';
    }

    /**
     * Fecha "AAAA-MM-DD" válida (formato exacto, día que existe y año entre 2000 y 2100), o null.
     * "2026-11-4", "2026-02-30" o "9999-12-31" no pasan.
     */
    protected function fechaValida(string $fecha): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($d === false || $d->format('Y-m-d') !== $fecha) {
            return null;
        }

        $anio = (int) $d->format('Y');

        return $anio >= 2000 && $anio <= 2100 ? $fecha : null;
    }

    /** Campos que nunca se guardan en sesión al volver a un formulario con errores. */
    private const CAMPOS_SENSIBLES = ['password', 'password_confirm', 'password_actual', 'csrf_test_name'];

    /** Teléfono: solo números, espacios, guiones, paréntesis y +, con al menos 6 dígitos. */
    protected const REGEX_TELEFONO = '/^(?=(?:\D*\d){6})[0-9+()\s\-]{6,30}$/';

    /**
     * POST con los textos recortados. Se valida sobre esto (no sobre el POST crudo)
     * para que "    x" no pase un min_length y después se guarde "x".
     *
     * @return array<string, mixed>
     */
    protected function datosRecortados(): array
    {
        return array_map(
            static fn ($valor) => is_string($valor) ? trim($valor) : $valor,
            (array) $this->request->getPost()
        );
    }

    /**
     * Vuelve al formulario con los errores y lo cargado, SIN guardar contraseñas en la sesión
     * (withInput() de CI4 guarda todo el POST en los archivos de sesión).
     *
     * @param array<string, string> $errores
     */
    protected function volverConErrores(array $errores, ?string $destino = null, string $tipo = 'errors'): RedirectResponse
    {
        $post = (array) $this->request->getPost();
        foreach (self::CAMPOS_SENSIBLES as $campo) {
            unset($post[$campo]);
        }

        session()->setFlashdata('_ci_old_input', ['get' => [], 'post' => $post]);

        $respuesta = $destino !== null ? redirect()->to($destino) : redirect()->back();

        return $respuesta->with($tipo, $tipo === 'errors' ? $errores : reset($errores));
    }

    /**
     * Regla del teléfono, obligatoria u opcional.
     *
     * @return array<string, mixed>
     */
    protected function reglaTelefono(bool $obligatorio): array
    {
        return [
            'rules'  => ($obligatorio ? 'required' : 'permit_empty') . '|max_length[30]|regex_match[' . self::REGEX_TELEFONO . ']',
            'errors' => [
                'required'    => 'El teléfono de contacto es obligatorio.',
                'max_length'  => 'El teléfono no puede superar los 30 caracteres.',
                'regex_match' => 'Ingrese un teléfono válido: al menos 6 números; puede usar espacios, guiones, paréntesis y +.',
            ],
        ];
    }

    /**
     * Regla del domicilio, obligatoria u opcional.
     *
     * @return array<string, mixed>
     */
    protected function reglaDomicilio(bool $obligatorio): array
    {
        return [
            'rules'  => ($obligatorio ? 'required' : 'permit_empty') . '|min_length[5]|max_length[150]',
            'errors' => [
                'required'   => 'El domicilio es obligatorio.',
                'min_length' => 'Ingrese un domicilio válido (calle, número y localidad).',
                'max_length' => 'El domicilio no puede superar los 150 caracteres.',
            ],
        ];
    }

    /**
     * Reglas compartidas para definir una contraseña nueva (HU-01, HU-03).
     * Mínimo 8 caracteres, con al menos una letra y un número.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function reglasNuevaPassword(string $campo = 'password', string $confirmacion = 'password_confirm'): array
    {
        return [
            $campo => [
                'label'  => 'Nueva contraseña',
                'rules'  => 'required|min_length[8]|max_length[72]|regex_match[/^(?=.*[A-Za-z])(?=.*\d).+$/]',
                'errors' => [
                    'required'    => 'La nueva contraseña es obligatoria.',
                    'min_length'  => 'La nueva contraseña debe tener al menos 8 caracteres.',
                    'max_length'  => 'La nueva contraseña no puede superar los 72 caracteres.',
                    'regex_match' => 'La nueva contraseña debe combinar letras y números.',
                ],
            ],
            $confirmacion => [
                'label'  => 'Confirmación',
                'rules'  => "required|matches[{$campo}]",
                'errors' => [
                    'required' => 'Debe repetir la nueva contraseña.',
                    'matches'  => 'La confirmación no coincide con la nueva contraseña.',
                ],
            ],
        ];
    }
}
