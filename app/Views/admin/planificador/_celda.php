<?php
/**
 * Contenido de una celda de la cuadrícula: estado de cobertura, enfermeros/as asignados y botón para asignar.
 * La usan la tabla (computadora) y la vista por día (celular).
 *
 * @var \App\Entities\Sector           $sector
 * @var \App\Entities\TipoTurno        $tipo
 * @var string                         $dia        Y-m-d
 * @var list<\App\Entities\Asignacion> $asignados
 * @var string                         $ahora      Y-m-d H:i:s
 */
// Solo los enfermeros/as activos cuentan para la cobertura; los dados de baja se muestran aparte.
$activos  = array_values(array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja()));
$cantidad = count($activos);
$minimo   = (int) $sector->dotacion_minima;
$terminado = \App\Models\AsignacionModel::calcularHorario($dia, $tipo)['fin'] <= $ahora;
$puedeAsignar = ! $terminado && $tipo->estaActivo();
$cuando = esc($tipo->nombre) . ' del ' . esc(fecha_larga($dia));
// Datos para el modal de la cuadrícula (agregar / quitar con AJAX). Sin JavaScript, los links llevan al formulario común.
$datosModal = 'data-modal-asignar data-sector="' . (int) $sector->id . '" data-tipo="' . (int) $tipo->id . '" data-fecha="' . esc($dia, 'attr')
    . '" data-descripcion="' . esc($tipo->nombre . ' del ' . fecha_larga($dia) . ' en ' . $sector->nombre, 'attr') . '"';
?>
<div class="celda-turno">
    <?php if ($cantidad >= $minimo): ?>
        <span class="cobertura cobertura--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span aria-hidden="true">Cubierto <?= $cantidad ?>/<?= $minimo ?></span><span class="visually-hidden">Cubierto: <?= $cantidad ?> de <?= $minimo ?> enfermeros mínimos.</span></span>
    <?php elseif ($cantidad > 0): ?>
        <span class="cobertura cobertura--parcial"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span aria-hidden="true">Faltan <?= $minimo - $cantidad ?> · <?= $cantidad ?>/<?= $minimo ?></span><span class="visually-hidden">Faltan <?= $minimo - $cantidad ?> enfermeros: <?= $cantidad ?> de <?= $minimo ?> asignados.</span></span>
    <?php else: ?>
        <span class="cobertura cobertura--vacio"><i class="bi bi-x-circle-fill" aria-hidden="true"></i><span aria-hidden="true">Sin cubrir 0/<?= $minimo ?></span><span class="visually-hidden">Sin cubrir: 0 de <?= $minimo ?> enfermeros mínimos.</span></span>
    <?php endif; ?>

    <?php if ($asignados !== []): ?>
        <ul class="list-unstyled mb-0 mt-1 small celda-turno__nombres">
            <?php foreach ($asignados as $a): ?>
                <li>
                    <?php if ($a->esFutura()): ?>
                        <a href="<?= base_url('admin/asignaciones/' . $a->id . '/editar') ?>">
                            <?= esc($a->nombreEnfermero()) ?><span class="visually-hidden">: reasignar su turno <?= $cuando ?></span>
                        </a>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger celda-turno__quitar"
                                data-quitar="<?= (int) $a->id ?>"
                                data-descripcion="<?= esc($a->nombreEnfermero() . ' · ' . $tipo->nombre . ' del ' . fecha_larga($dia), 'attr') ?>"
                                title="Quitar del turno">
                            <i class="bi bi-x-circle" aria-hidden="true"></i><span class="visually-hidden">Quitar a <?= esc($a->nombreEnfermero()) ?> del turno <?= $cuando ?></span>
                        </button>
                    <?php else: ?>
                        <?= esc($a->nombreEnfermero()) ?>
                    <?php endif; ?>
                    <?php if ($a->enfermeroDeBaja()): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>De baja · no cuenta</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($puedeAsignar): ?>
        <?php $url = base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => (int) $sector->id, 'tipo' => (int) $tipo->id, 'fecha' => $dia], '', '&amp;'); ?>
        <?php if ($cantidad >= $minimo): ?>
            <a class="btn btn-link btn-sm p-0 mt-1" href="<?= $url ?>" <?= $datosModal ?>>
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar<span class="visually-hidden"> otro enfermero/a al turno <?= $cuando ?></span>
            </a>
        <?php else: ?>
            <a class="btn btn-outline-secondary btn-sm mt-2 w-100" href="<?= $url ?>" <?= $datosModal ?>>
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Asignar<span class="visually-hidden"> turno <?= $cuando ?></span>
            </a>
        <?php endif; ?>
    <?php elseif ($terminado): ?>
        <span class="d-block small text-body-secondary mt-1"><i class="bi bi-lock" aria-hidden="true"></i> Terminado<span class="visually-hidden">: no se puede modificar</span></span>
    <?php endif; ?>
</div>
