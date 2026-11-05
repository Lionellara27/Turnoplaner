<?php
/**
 * Horario del tipo de turno: "22:00 → 06:00 (+1 día)". La flecha se oculta a los lectores de pantalla,
 * que leen "22:00 a 06:00, termina al día siguiente".
 *
 * @var \App\Entities\TipoTurno $t
 */
?>
<?= esc($t->inicio()) ?> <span aria-hidden="true">→</span><span class="visually-hidden">a</span> <?= esc($t->fin()) ?><?php if ($t->cruzaMedianoche()): ?> <span aria-hidden="true">(+1 día)</span><span class="visually-hidden">, termina al día siguiente</span><?php endif; ?>
