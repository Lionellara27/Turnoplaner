<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Ausencia;
use App\Models\AsignacionModel;
use App\Models\AusenciaModel;
use App\Models\UsuarioModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * HU-16: gestión de ausencias informadas (listado, reemplazo y rechazo) por el administrador.
 * La lógica (validaciones, transacciones y recálculo de horas) vive en AusenciaModel; acá solo se
 * leen los datos del pedido, se llama al modelo y se responde con la vista o una redirección con flashdata.
 */
class Ausencias extends BaseController
{
    private AusenciaModel $modelo;

    public function __construct()
    {
        $this->modelo = new AusenciaModel();
    }

    /** Listado con filtro de estado (?estado=PENDIENTE|CUBIERTA|RECHAZADA|TODAS; por defecto, pendientes). */
    public function index(): string
    {
        $estado = $this->textoGet('estado');
        $estado = array_key_exists($estado, AusenciaModel::FILTROS) ? $estado : AusenciaModel::PENDIENTE;

        // Cantidad por estado para las pestañas del filtro (una sola consulta agrupada).
        $conteos = array_fill_keys(array_keys(AusenciaModel::FILTROS), 0);
        foreach ($this->modelo->builder()->select('estado, COUNT(*) AS total')->groupBy('estado')->get()->getResultArray() as $fila) {
            $conteos[(string) $fila['estado']] = (int) $fila['total'];
            $conteos[AusenciaModel::FILTRO_TODAS] += (int) $fila['total'];
        }

        return view('admin/ausencias/index', [
            'titulo'    => 'Ausencias - TurnoPlaner',
            'estado'    => $estado,
            'conteos'   => $conteos,
            'ausencias' => $this->modelo->listarParaAdmin($estado),
        ]);
    }

    /** Pantalla "Asignar reemplazo": datos del turno y de la ausencia, candidatos disponibles y opción de rechazar. */
    public function reemplazo(int $id): string
    {
        $resultado = $this->modelo->candidatosReemplazo($id);
        $ausencia  = $resultado['ausencia'];
        if (! $ausencia instanceof Ausencia) {
            throw PageNotFoundException::forPageNotFound('No se encontró la ausencia.');
        }

        $semana   = AsignacionModel::lunesDe((string) $ausencia->fecha);
        $opciones = [];
        foreach ($resultado['candidatos'] as $orden => $fila) {
            $e        = $fila['enfermero'];
            $maximo   = (int) $fila['maximo_minutos'];
            $opciones[] = [
                'id'           => (int) $e->id,
                'nombre'       => $e->getNombreCompleto(),
                'especialidad' => (string) ($e->especialidad ?? ''),
                'matricula'    => (string) ($e->matricula ?? ''),
                'horas_texto'  => AsignacionModel::horasTexto((int) $fila['minutos_semana'])
                    . ($maximo > 0 ? ' de ' . AsignacionModel::horasTexto($maximo) : '') . ' en la semana del turno',
                'estado'       => (string) $fila['estado'],
                'motivo'       => '',
                'orden'        => $orden,
                'minutos'      => (int) $fila['minutos_semana'],
                'checked'      => (int) old('usuario_id') === (int) $e->id,
            ];
        }

        return view('admin/ausencias/reemplazo', [
            'titulo'         => 'Asignar reemplazo - TurnoPlaner',
            'ausencia'       => $ausencia,
            'opciones'       => $opciones,
            'excluidos'      => (int) $resultado['excluidos'],
            'error'          => $resultado['error'],
            'especialidades' => UsuarioModel::ESPECIALIDADES,
            'semana'         => $semana,
        ]);
    }

    /** Confirma el reemplazo elegido (el modelo vuelve a validar que siga disponible). */
    public function asignarReemplazo(int $id): RedirectResponse
    {
        $destino = base_url('admin/ausencias/' . $id . '/reemplazo');
        $elegido = $this->request->getPost('usuario_id');
        $elegido = is_string($elegido) && ctype_digit($elegido) ? (int) $elegido : 0;

        if ($elegido <= 0) {
            return $this->volverConErrores(['usuario_id' => 'Seleccione el enfermero/a que cubrirá el turno.'], $destino);
        }

        $resultado = $this->modelo->cubrirConReemplazo($id, $elegido, (int) session()->get('usuario_id'));
        if ($resultado['no_encontrada']) {
            throw PageNotFoundException::forPageNotFound('No se encontró la ausencia.');
        }

        if (! $resultado['ok']) {
            if (isset($resultado['errores']['reemplazante_id'])) {
                return $this->volverConErrores(['usuario_id' => $resultado['errores']['reemplazante_id']], $destino);
            }

            return redirect()->to($destino)->with('error', $resultado['errores']['general'] ?? 'No se pudo asignar el reemplazo.');
        }

        $ausencia = $this->modelo->buscarConDetalle($id);
        $mensaje  = 'Reemplazo asignado.';
        if ($ausencia instanceof Ausencia) {
            $mensaje = 'Reemplazo asignado: ' . $ausencia->nombreReemplazante() . ' cubrirá el turno ' . $ausencia->tipo_nombre
                . ' del ' . fecha_larga((string) $ausencia->fecha) . ' en ' . $ausencia->sector_nombre
                . '. El turno de ' . $ausencia->nombreEnfermero() . ' queda como "Ausente justificado (Cubierto)".';
        }

        return redirect()->to(base_url('admin/ausencias'))->with('success', $mensaje);
    }

    /** Rechaza la ausencia con una observación obligatoria (el enfermero/a deberá presentarse al turno). */
    public function rechazar(int $id): RedirectResponse
    {
        $datos       = $this->datosRecortados();
        $observacion = is_string($datos['observacion'] ?? null) ? $datos['observacion'] : '';

        $resultado = $this->modelo->rechazar($id, (int) session()->get('usuario_id'), $observacion);
        if ($resultado['no_encontrada']) {
            throw PageNotFoundException::forPageNotFound('No se encontró la ausencia.');
        }

        if (! $resultado['ok']) {
            if (isset($resultado['errores']['observacion'])) {
                // Vuelve a la pantalla de la ausencia, donde el formulario de rechazo funciona sin JavaScript.
                return $this->volverConErrores(['observacion' => $resultado['errores']['observacion']], base_url('admin/ausencias/' . $id . '/reemplazo') . '#rechazar');
            }

            return redirect()->to(base_url('admin/ausencias'))->with('error', $resultado['errores']['general'] ?? 'No se pudo rechazar la ausencia.');
        }

        $ausencia = $this->modelo->buscarConDetalle($id);
        $mensaje  = 'Ausencia rechazada.';
        if ($ausencia instanceof Ausencia) {
            $mensaje = 'Ausencia rechazada: ' . $ausencia->nombreEnfermero() . ' deberá presentarse al turno ' . $ausencia->tipo_nombre
                . ' del ' . fecha_larga((string) $ausencia->fecha) . ' en ' . $ausencia->sector_nombre . '. Se le mostrará el motivo del rechazo.';
        }

        return redirect()->to(base_url('admin/ausencias'))->with('success', $mensaje);
    }
}
