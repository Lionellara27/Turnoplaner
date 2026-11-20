<?php
/**
 * Estado de una ausencia para el administrador: ícono + texto (nunca solo color).
 *
 * @var \App\Entities\Ausencia $a
 */
$modificador = match (true) {
    $a->estaCubierta()  => 'cubierta',
    $a->estaRechazada() => 'rechazada',
    default             => 'pendiente',
};
?>
<span class="tp-estado-ausencia tp-estado-ausencia--<?= $modificador ?>"><i class="bi <?= esc($a->iconoEstado(), 'attr') ?>" aria-hidden="true"></i><?= esc($a->estadoTexto()) ?></span>
