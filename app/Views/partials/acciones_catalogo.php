<?php
/**
 * Botones de acción de una fila de catálogo (sectores, tipos de turno): editar, dar de baja o reactivar.
 * En escritorio son íconos compactos; en celular ($conTexto = true) llevan texto.
 *
 * @var int    $id
 * @var string $nombre    nombre del registro (para el texto accesible)
 * @var bool   $activo
 * @var string $ruta      por ejemplo 'admin/sectores'
 * @var string $prefijo   prefijo del id del modal, por ejemplo 'Sector'
 * @var bool   $conTexto
 */
$conTexto = $conTexto ?? false;
$tam      = $conTexto ? '' : ' btn-sm';
$nombreE  = esc($nombre);
$etiqueta = static fn (string $visible, string $oculto): string => $conTexto
    ? '<span class="ms-1">' . $visible . '</span><span class="visually-hidden"> ' . $nombreE . '</span>'
    : '<span class="visually-hidden">' . $oculto . '</span>';
?>
<a href="<?= base_url($ruta . '/' . $id . '/editar') ?>" class="btn btn-outline-secondary<?= $tam ?>" title="Editar">
    <i class="bi bi-pencil" aria-hidden="true"></i><?= $etiqueta('Editar', 'Editar ' . $nombreE) ?>
</a>
<?php if ($activo): ?>
    <button type="button" class="btn btn-outline-danger<?= $tam ?>" title="Dar de baja"
            data-bs-toggle="modal" data-bs-target="#modalBaja<?= esc($prefijo, 'attr') . (int) $id ?>">
        <i class="bi bi-slash-circle" aria-hidden="true"></i><?= $etiqueta('Baja', 'Dar de baja ' . $nombreE) ?>
    </button>
<?php else: ?>
    <form action="<?= base_url($ruta . '/' . $id . '/reactivar') ?>" method="post" class="d-inline" data-enviando="Reactivando...">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-success<?= $tam ?>" title="Reactivar">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><?= $etiqueta('Reactivar', 'Reactivar ' . $nombreE) ?>
        </button>
    </form>
<?php endif; ?>
