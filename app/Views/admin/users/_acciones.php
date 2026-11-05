<?php
/**
 * Botones de acción de una fila del listado (HU-04, HU-07, HU-08).
 * En escritorio son íconos compactos; en celular ($conTexto = true) llevan texto y son más grandes.
 *
 * @var \App\Entities\Usuario $u
 * @var bool                  $conTexto
 */
$conTexto = $conTexto ?? false;
$nombre   = esc($u->getNombreCompleto());
$tam      = $conTexto ? '' : ' btn-sm';
$etiqueta = static fn (string $visible, string $oculto): string => $conTexto
    ? '<span class="ms-1">' . $visible . '</span>'
    : '<span class="visually-hidden">' . $oculto . '</span>';
?>
<?php if ($u->esEnfermero()): ?>
    <a href="<?= base_url('admin/usuarios/' . $u->id . '/editar') ?>" class="btn btn-outline-secondary<?= $tam ?>" title="Editar legajo">
        <i class="bi bi-pencil" aria-hidden="true"></i><?= $etiqueta('Editar', 'Editar a ' . $nombre) ?>
    </a>
    <?php if ($u->estaActivo()): ?>
        <button type="button" class="btn btn-outline-secondary<?= $tam ?>" title="Restablecer contraseña"
                data-bs-toggle="modal" data-bs-target="#modalRestablecer<?= (int) $u->id ?>">
            <i class="bi bi-key" aria-hidden="true"></i><?= $etiqueta('Clave', 'Restablecer contraseña de ' . $nombre) ?>
        </button>
        <button type="button" class="btn btn-outline-danger<?= $tam ?>" title="Dar de baja"
                data-bs-toggle="modal" data-bs-target="#modalBaja<?= (int) $u->id ?>">
            <i class="bi bi-person-x" aria-hidden="true"></i><?= $etiqueta('Baja', 'Dar de baja a ' . $nombre) ?>
        </button>
    <?php else: ?>
        <form action="<?= base_url('admin/usuarios/' . $u->id . '/reactivar') ?>" method="post" class="d-inline" data-enviando="Reactivando...">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-success<?= $tam ?>" title="Reactivar">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><?= $etiqueta('Reactivar', 'Reactivar a ' . $nombre) ?>
            </button>
        </form>
    <?php endif; ?>
<?php else: ?>
    <span class="text-body-secondary small" title="Las cuentas de administrador se gestionan desde Mi perfil">
        <i class="bi bi-shield-check" aria-hidden="true"></i><span class="<?= $conTexto ? 'ms-1' : 'visually-hidden' ?>">Cuenta de administrador: se gestiona desde Mi perfil</span>
    </span>
<?php endif; ?>
