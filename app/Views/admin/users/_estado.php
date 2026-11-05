<?php
/**
 * Badge de estado del usuario (DESIGN.md 4.4). Lo usan la tabla y las tarjetas del listado.
 *
 * @var \App\Entities\Usuario $u
 */
?>
<?php if (! $u->estaActivo()): ?>
    <span class="badge bg-secondary-subtle text-secondary-emphasis border"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Baja</span>
<?php elseif ($u->debeCambiarPassword()): ?>
    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Pendiente de activación</span>
<?php else: ?>
    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Activo</span>
<?php endif; ?>
