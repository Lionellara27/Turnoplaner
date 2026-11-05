<?php
/**
 * Badge del tipo de área del sector: crítica o general (ícono + texto, nunca solo color).
 *
 * @var \App\Entities\Sector $s
 */
?>
<?php if ($s->esCritico()): ?>
    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>Área crítica</span>
<?php else: ?>
    <span class="badge bg-light text-dark border"><i class="bi bi-building me-1" aria-hidden="true"></i>General</span>
<?php endif; ?>
