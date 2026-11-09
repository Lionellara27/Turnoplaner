<?php
/**
 * Una pieza de un turno del cronograma (HU-17), según $parte:
 *  - 'etiqueta': el tipo de turno con su color (excepción de estilo en línea, DESIGN.md 4.11).
 *  - 'horario':  "06:00 → 14:00"; la Noche muestra el día de salida: "22:00 → 06:00 del mar 10/11".
 *  - 'estado':   Programado / En curso / Finalizado (ícono + texto, nunca solo color).
 *  - 'frase':    una oración completa para lectores de pantalla (la agenda del celular oculta las piezas visuales).
 *
 * @var \App\Entities\Asignacion $t
 * @var string                   $ahora Y-m-d H:i:s
 * @var string                   $parte
 */
$estado = $t->estadoTurno($ahora);
$salida = substr((string) $t->fin, 0, 10);
$textos = ['proximo' => 'programado', 'en_curso' => 'en curso', 'finalizado' => 'finalizado'];
?>
<?php if ($parte === 'etiqueta'): ?>
    <span class="etiqueta-turno" style="--color-turno: <?= esc($t->colorTipo(), 'attr') ?>; --color-turno-texto: <?= esc(\App\Entities\TipoTurno::textoSobre($t->colorTipo()), 'attr') ?>;"><?= esc((string) $t->tipo_nombre) ?></span>
<?php elseif ($parte === 'horario'): ?>
    <span class="text-nowrap"><?= esc($t->horaInicio()) ?> <span aria-hidden="true">→</span><span class="visually-hidden">a</span> <?= esc($t->horaFin()) ?></span><?php if ($t->terminaAlDiaSiguiente()): ?> <span class="text-nowrap">del <?= esc(mb_substr(dia_semana($salida), 0, 3)) ?> <?= esc(date('d/m', strtotime($salida))) ?></span><?php endif; ?>
<?php elseif ($parte === 'frase'): ?>
    <span class="visually-hidden"><?= esc((string) $t->tipo_nombre) ?> en <?= esc((string) $t->sector_nombre) ?><?= (int) $t->sector_critico === 1 ? ' (área crítica)' : '' ?>, de <?= esc($t->horaInicio()) ?> a <?= esc($t->horaFin()) ?><?= $t->terminaAlDiaSiguiente() ? ' del ' . esc(fecha_larga($salida)) : '' ?>, <?= esc($textos[$estado]) ?>.</span>
<?php else: ?>
    <?php if ($estado === 'en_curso'): ?>
        <span class="estado-turno estado-turno--en-curso"><i class="bi bi-play-circle-fill" aria-hidden="true"></i>En curso</span>
    <?php elseif ($estado === 'proximo'): ?>
        <span class="estado-turno estado-turno--programado"><i class="bi bi-calendar-check" aria-hidden="true"></i>Programado</span>
    <?php else: ?>
        <span class="estado-turno estado-turno--finalizado"><i class="bi bi-check2-circle" aria-hidden="true"></i>Finalizado</span>
    <?php endif; ?>
<?php endif; ?>
