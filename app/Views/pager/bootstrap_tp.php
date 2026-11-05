<?php
/**
 * Paginación con estilo Bootstrap 5 (HU-06). Registrada en app/Config/Pager.php como 'bootstrap_tp'.
 *
 * @var \CodeIgniter\Pager\PagerRenderer $pager
 */
$pager->setSurroundCount(2);
?>
<ul class="pagination pagination-sm mb-0 flex-wrap">
    <?php if ($pager->hasPrevious()): ?>
        <li class="page-item"><a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="Primera página">&laquo;</a></li>
        <li class="page-item"><a class="page-link" href="<?= $pager->getPrevious() ?>" aria-label="Página anterior">&lsaquo;</a></li>
    <?php endif ?>

    <?php foreach ($pager->links() as $link): ?>
        <li class="page-item<?= $link['active'] ? ' active' : '' ?>">
            <a class="page-link" href="<?= $link['uri'] ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>><?= $link['title'] ?></a>
        </li>
    <?php endforeach ?>

    <?php if ($pager->hasNext()): ?>
        <li class="page-item"><a class="page-link" href="<?= $pager->getNext() ?>" aria-label="Página siguiente">&rsaquo;</a></li>
        <li class="page-item"><a class="page-link" href="<?= $pager->getLast() ?>" aria-label="Última página">&raquo;</a></li>
    <?php endif ?>
</ul>
