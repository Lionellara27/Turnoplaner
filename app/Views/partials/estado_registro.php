<?php
/**
 * Badge de estado de un registro de catálogo (sectores, tipos de turno). DESIGN.md 4.4.
 *
 * @var bool $activo
 */
?>
<?php if ($activo): ?>
    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Activo</span>
<?php else: ?>
    <span class="badge bg-secondary-subtle text-secondary-emphasis border"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Baja</span>
<?php endif; ?>
