<?php
/**
 * Etiqueta de un tipo de turno: su color de fondo con el nombre encima.
 * El texto se elige negro o blanco según el color (TipoTurno::colorTexto()), así siempre se lee.
 * La reutiliza la cuadrícula (HU-14).
 *
 * @var \App\Entities\TipoTurno $t
 */
?>
<span class="etiqueta-turno" style="--color-turno: <?= esc($t->color, 'attr') ?>; --color-turno-texto: <?= esc($t->colorTexto(), 'attr') ?>;">
    <?= esc($t->nombre) ?>
</span>
