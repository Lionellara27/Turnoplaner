<?php
/**
 * Fila esbelta de un tipo de turno dentro de un día del calendario mensual.
 *
 * @var \App\Entities\Sector           $sector
 * @var \App\Entities\TipoTurno        $tipo
 * @var string                         $dia        Y-m-d
 * @var list<\App\Entities\Asignacion> $asignados
 * @var string                         $ahora      Y-m-d H:i:s
 * @var string                         $fechaMaxima Y-m-d
 */
$activos      = array_values(array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja()));
$cantidad     = count($activos);
$minimo       = (int) $sector->dotacion_minima;
$terminado    = \App\Models\AsignacionModel::calcularHorario($dia, $tipo)['fin'] <= $ahora;
$fueraVentana = $dia > ($fechaMaxima ?? \App\Models\AsignacionModel::fechaMaximaHabilitada());
$puedeAsignar = ! $terminado && ! $fueraVentana && $tipo->estaActivo();
$cuando       = esc($tipo->nombre) . ' del ' . esc(fecha_larga($dia));

$datosModal = 'data-modal-asignar data-sector="' . (int) $sector->id . '" data-tipo="' . (int) $tipo->id . '" data-fecha="' . esc($dia, 'attr')
    . '" data-descripcion="' . esc($tipo->nombre . ' (' . $tipo->rangoTexto() . ') del ' . fecha_larga($dia) . ' en ' . $sector->nombre, 'attr') . '"';

$claseCupo = $cantidad >= $minimo ? 'tp-cupo--ok'
    : ($cantidad > 0 ? 'tp-cupo--parcial' : 'tp-cupo--vacio');
?>
<div class="tp-shift-row" style="border-left: 4px solid <?= esc($tipo->color, 'attr') ?> !important;"
     title="<?= esc($tipo->nombre . ' (' . $tipo->rangoTexto() . ') · ' . $cantidad . '/' . $minimo . ' asignados') ?>">
    <span class="tp-shift-row__nombre text-truncate"><?= esc($tipo->nombre) ?></span>

    <div class="d-flex align-items-center gap-1 flex-shrink-0">
        <span class="tp-turno-pill__cupo <?= $claseCupo ?>"><?= $cantidad ?>/<?= $minimo ?></span>

        <?php if ($puedeAsignar): ?>
            <?php $url = base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => (int) $sector->id, 'tipo' => (int) $tipo->id, 'fecha' => $dia], '', '&amp;'); ?>
            <a href="<?= $url ?>" class="tp-shift-row__btn-add" <?= $datosModal ?>
               title="Asignar enfermero/a a <?= $cuando ?>">
                <i class="bi bi-plus" aria-hidden="true"></i><span class="visually-hidden">Asignar <?= $cuando ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>