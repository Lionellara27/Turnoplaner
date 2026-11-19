<?php
/**
 * Fila de un tipo de turno dentro de un día del calendario mensual (tabla o lista por día).
 * El "+" es un link al formulario completo (funciona sin JavaScript); con JavaScript abre el modal.
 *
 * @var \App\Entities\Sector           $sector
 * @var \App\Entities\TipoTurno        $tipo
 * @var string                         $dia         Y-m-d
 * @var list<\App\Entities\Asignacion> $asignados
 * @var string                         $ahora       Y-m-d H:i:s
 * @var string                         $fechaMaxima Y-m-d
 * @var bool                           $conTexto    true en la lista por día: muestra horario y "Asignar"
 */
$conTexto     = $conTexto ?? false;
$activos      = array_values(array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja()));
$cantidad     = count($activos);
$minimo       = (int) $sector->dotacion_minima;
$terminado    = \App\Models\AsignacionModel::calcularHorario($dia, $tipo)['fin'] <= $ahora;
$fueraVentana = $dia > ($fechaMaxima ?? \App\Models\AsignacionModel::fechaMaximaHabilitada());
$puedeAsignar = ! $terminado && ! $fueraVentana && $tipo->estaActivo();
$numDia       = (int) substr($dia, 8, 2);
$url          = base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => (int) $sector->id, 'tipo' => (int) $tipo->id, 'fecha' => $dia], '', '&amp;');
$descripcion  = $tipo->nombre . ' (' . $tipo->rangoTexto() . ') del ' . fecha_larga($dia) . ' en ' . $sector->nombre;
?>
<li class="tp-shift-row" style="--color-turno: <?= esc($tipo->color, 'attr') ?>;">
    <div class="tp-shift-row__info">
        <span class="tp-shift-row__nombre"><?= esc($tipo->nombre) ?></span>
        <?php if ($conTexto): ?>
            <span class="tp-shift-row__horario"><?= esc($tipo->rangoTexto()) ?></span>
        <?php endif; ?>
        <?= view('admin/planificador/_cobertura', ['cubiertos' => $cantidad, 'minimo' => $minimo]) ?>
    </div>

    <?php if ($puedeAsignar): ?>
        <a href="<?= $url ?>" class="tp-shift-row__btn-add"
           data-modal-asignar data-sector="<?= (int) $sector->id ?>" data-tipo="<?= (int) $tipo->id ?>"
           data-fecha="<?= esc($dia, 'attr') ?>" data-descripcion="<?= esc($descripcion, 'attr') ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i><?php if ($conTexto): ?><span aria-hidden="true">Asignar</span><?php endif; ?>
            <span class="visually-hidden">Asignar turno <?= esc($tipo->nombre) ?> el día <?= $numDia ?> (<?= esc(fecha_larga($dia)) ?>)</span>
        </a>
    <?php endif; ?>
</li>
