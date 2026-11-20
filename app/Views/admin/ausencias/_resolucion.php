<?php
/**
 * Resultado de la gestión de una ausencia: reemplazante, motivo del rechazo y quién la resolvió.
 *
 * @var \App\Entities\Ausencia $a
 */
$resolvio = trim(($a->resuelto_por_apellido ?? '') . ', ' . ($a->resuelto_por_nombre ?? ''), ', ');
?>
<?php if ($a->estaCubierta()): ?>
    <span class="d-block small"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Reemplazo: <strong><?= esc($a->nombreReemplazante() !== '' ? $a->nombreReemplazante() : 'sin datos') ?></strong></span>
<?php elseif ($a->estaRechazada() && $a->observacionAdmin() !== ''): ?>
    <span class="d-block small">Motivo del rechazo: <?= esc($a->observacionAdmin()) ?></span>
<?php endif; ?>
<?php if (! $a->estaPendiente() && $resolvio !== ''): ?>
    <span class="d-block small text-body-secondary">Resuelta por <?= esc($resolvio) ?><?= ! empty($a->resuelto_at) ? ' el ' . esc(fecha_ar((string) $a->resuelto_at, true)) : '' ?></span>
<?php endif; ?>
