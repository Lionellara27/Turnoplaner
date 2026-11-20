<?php
/**
 * Acciones de una ausencia en el listado (tarjeta y tabla).
 * "Rechazar" abre un modal con JavaScript; sin JavaScript, el mismo link lleva a la pantalla de la ausencia,
 * que tiene el formulario de rechazo.
 *
 * @var \App\Entities\Ausencia $a
 */
$urlReemplazo = base_url('admin/ausencias/' . (int) $a->id . '/reemplazo');
$quien        = $a->nombreEnfermero() . ' (' . $a->tipo_nombre . ' del ' . fecha_ar((string) $a->fecha) . ')';
?>
<?php if ($a->puedeCubrirse()): ?>
    <a href="<?= $urlReemplazo ?>" class="btn btn-outline-secondary text-nowrap">
        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Asignar reemplazo<span class="visually-hidden"> para <?= esc($quien) ?></span>
    </a>
<?php elseif ($a->estaPendiente()): ?>
    <span class="small text-body-secondary"><i class="bi bi-lock me-1" aria-hidden="true"></i>El turno ya terminó: no admite reemplazo.</span>
<?php endif; ?>
<?php if ($a->puedeRechazarse()): ?>
    <a href="<?= $urlReemplazo ?>#rechazar" class="btn btn-outline-danger text-nowrap" role="button"
       data-bs-toggle="modal" data-bs-target="#modalRechazar<?= (int) $a->id ?>">
        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Rechazar<span class="visually-hidden"> la ausencia de <?= esc($quien) ?></span>
    </a>
<?php endif; ?>
<?php if (! $a->estaPendiente()): ?>
    <span class="small text-body-secondary">Sin acciones pendientes.</span>
<?php endif; ?>
