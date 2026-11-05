<?php
/**
 * Modal de confirmación de baja lógica de un registro de catálogo (sectores, tipos de turno).
 *
 * @var int    $id
 * @var string $nombre
 * @var string $ruta     por ejemplo 'admin/sectores'
 * @var string $prefijo  prefijo del id del modal, por ejemplo 'Sector'
 * @var string $detalle  texto que explica la consecuencia de la baja
 */
$idModal = 'modalBaja' . $prefijo . (int) $id;
?>
<div class="modal fade" id="<?= esc($idModal, 'attr') ?>" tabindex="-1" aria-labelledby="titulo<?= esc($idModal, 'attr') ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6" id="titulo<?= esc($idModal, 'attr') ?>"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Dar de baja</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">¿Dar de baja <strong><?= esc($nombre) ?></strong>?</p>
                <p class="small text-body-secondary mb-0"><?= esc($detalle) ?> Se puede reactivar cuando quiera.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= base_url($ruta . '/' . $id . '/baja') ?>" method="post" class="d-inline" data-enviando="Dando de baja...">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">Dar de baja</button>
                </form>
            </div>
        </div>
    </div>
</div>
