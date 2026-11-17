<?php
/**
 * Bloque de un tipo de turno dentro de un día del calendario mensual del administrador.
 *
 * @var \App\Entities\Sector           $sector
 * @var \App\Entities\TipoTurno        $tipo
 * @var string                         $dia        Y-m-d
 * @var list<\App\Entities\Asignacion> $asignados
 * @var string                         $ahora      Y-m-d H:i:s
 * @var string                         $fechaMaxima Y-m-d
 */
$activos      = array_values(array_filter($asignados, static fn ($a): bool => !$a->enfermeroDeBaja()));
$cantidad     = count($activos);
$minimo       = (int)$sector->dotacion_minima;
$terminado    = \App\Models\AsignacionModel::calcularHorario($dia, $tipo)['fin'] <=$ahora;
$fueraVentana = $dia > ($fechaMaxima ?? \App\Models\AsignacionModel::fechaMaximaHabilitada());
$puedeAsignar = ! $terminado && !$fueraVentana && $tipo->estaActivo();$cuando       = esc($tipo->nombre) . ' del ' . esc(fecha_larga($dia));

$datosModal = 'data-modal-asignar data-sector="' . (int) $sector->id . '" data-tipo="' . (int) $tipo->id . '" data-fecha="' . esc($dia, 'attr')
    . '" data-descripcion="' . esc($tipo->nombre . ' (' .$tipo->rangoTexto() . ') del ' . fecha_larga($dia) . ' en ' .$sector->nombre, 'attr') . '"';

$claseCobertura = $cantidad >=$minimo ? 'border-success-subtle bg-success-subtle text-success-emphasis'
    : ($cantidad > 0 ? 'border-warning-subtle bg-warning-subtle text-warning-emphasis'
    : 'border-danger-subtle bg-danger-subtle text-danger-emphasis');
?>
<div class="border rounded-2 p-1 mb-1 bg-white shadow-2xs">
    <div class="d-flex justify-content-between align-items-center gap-1 flex-wrap">
        <span class="badge text-truncate" style="background-color: <?= esc($tipo->color, 'attr') ?>; color: <?= esc(\App\Entities\TipoTurno::textoSobre($tipo->color), 'attr') ?>; max-width: 110px;"
              title="<?= esc($tipo->nombre . ' · ' .$tipo->rangoTexto()) ?>">
            <?= esc($tipo->nombre) ?>
        </span>
        <span class="badge border <?= $claseCobertura ?>" style="font-size: 0.7rem;" title="Dotación cubierta / mínima">
            <?= $cantidad ?>/<?= $minimo ?>
        </span>
        <?php if ($puedeAsignar): ?>
            <?php $url = base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => (int) $sector->id, 'tipo' => (int) $tipo->id, 'fecha' =>$dia], '', '&amp;'); ?>
            <a href="<?= $url ?>" class="btn btn-outline-secondary btn-sm py-0 px-1 lh-1" <?= $datosModal ?> title="Asignar enfermero/a a <?= $cuando ?>">
                <i class="bi bi-plus" aria-hidden="true"></i><span class="visually-hidden">Asignar <?= $cuando ?></span>
            </a>
        <?php endif; ?>
    </div>

    <div class="text-body-secondary" style="font-size: 0.72rem;">
        <?= esc($tipo->rangoTexto()) ?>
    </div>

    <?php if ($asignados !== []): ?>
        <ul class="list-unstyled mb-0 mt-1" style="font-size: 0.78rem;">
            <?php foreach ($asignados as$a): ?>
                <li class="d-flex align-items-center justify-content-between gap-1 border-top pt-1 mt-1">
                    <span class="text-truncate">
                        <?php if ($a->esFutura()): ?>
                            <a href="<?= base_url('admin/asignaciones/' . $a->id . '/editar') ?>" class="text-decoration-none fw-medium" title="Reasignar turno">
                                <?= esc($a->nombreEnfermero()) ?>
                            </a>
                        <?php else: ?>
                            <span><?= esc($a->nombreEnfermero()) ?></span>
                        <?php endif; ?>
                        <?php if ($a->esExtra()): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;" title="Hora extra cubierta">HE</span>
                        <?php endif; ?>
                        <?php if ($a->enfermeroDeBaja()): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis" style="font-size: 0.65rem;">Baja</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($a->esFutura()): ?>
                        <button type="button" class="btn btn-link btn-sm p-0 text-danger lh-1"
                                data-quitar="<?= (int) $a->id ?>"
                                data-descripcion="<?= esc($a->nombreEnfermero() . ' · ' . $tipo->nombre . ' del ' . fecha_larga($dia), 'attr') ?>"
                                title="Quitar del turno">
                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                        </button>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>