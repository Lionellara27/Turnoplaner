<?php
/**
 * Datos del turno de una ausencia: tipo (con su color), fecha, horario y sector (marcando las áreas críticas).
 *
 * @var \App\Entities\Ausencia $a
 */
?>
<span class="etiqueta-turno" style="--color-turno: <?= esc($a->colorTipo(), 'attr') ?>;"><?= esc((string) $a->tipo_nombre) ?></span>
<span class="d-block fw-semibold mt-1"><?= esc(ucfirst(fecha_larga((string) $a->fecha))) ?></span>
<span class="d-block small"><?= esc($a->horarioTexto()) ?></span>
<span class="d-block small">
    <i class="bi bi-hospital me-1" aria-hidden="true"></i><?= esc((string) $a->sector_nombre) ?>
    <?php if (! empty($a->sector_critico)): ?>
        <span class="badge tp-badge-critica"><i class="bi bi-heart-pulse-fill me-1" aria-hidden="true"></i>Área crítica</span>
    <?php endif; ?>
</span>
