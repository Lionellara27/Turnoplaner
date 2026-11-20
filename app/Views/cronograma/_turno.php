<?php
/**
 * Una pieza de un turno del cronograma (HU-17), según $parte:
 *  - 'etiqueta': el tipo de turno con su color (excepción de estilo en línea, DESIGN.md 4.11).
 *  - 'horario':  "06:00 → 14:00"; la Noche muestra el día de salida: "22:00 → 06:00 del mar 10/11".
 *  - 'estado':   Programado / En curso / Finalizado (ícono + texto, nunca solo color).
 *  - 'frase':    una oración completa para lectores de pantalla (la agenda del celular oculta las piezas visuales).
 *  - 'extra':    marca "HE" si el turno se registró como hora extra (con el texto completo para lectores de pantalla).
 *  - 'acciones': link "Informar ausencia" (HU-18) si el turno todavía no empezó y no tiene un aviso activo.
 * El estado muestra además el aviso de ausencia (HU-18): en gestión, reemplazo asignado o rechazado.
 *
 * @var \App\Entities\Asignacion                $t
 * @var string                                  $ahora Y-m-d H:i:s
 * @var string                                  $parte
 * @var array<int, \App\Entities\Ausencia>|null $ausencias [asignacion_id => aviso] (AusenciaModel::ultimaPorAsignaciones)
 */
$estado = $t->estadoTurno($ahora);
$salida = substr((string) $t->fin, 0, 10);
$textos = ['proximo' => 'programado', 'en_curso' => 'en curso', 'finalizado' => 'finalizado'];
$aviso  = ($ausencias ?? [])[(int) $t->id] ?? null;
// El aviso activo (PENDIENTE o CUBIERTA) reemplaza al estado del turno; el rechazado se muestra mientras el turno no terminó.
$avisoActivo    = $aviso !== null && $aviso->esActiva();
$avisoRechazado = $aviso !== null && $aviso->estaRechazada() && $estado !== 'finalizado';
$textoAviso     = '';
if ($avisoActivo) {
    $textoAviso = $aviso->estaCubierta() ? 'Reemplazo asignado — no debe presentarse' : $aviso->estadoParaEnfermero();
} elseif ($avisoRechazado) {
    $textoAviso = 'Aviso rechazado: debe presentarse al turno';
}
$puedeAvisar = (string) $t->inicio > $ahora && ! $avisoActivo && ! $t->tieneAusenciaActiva();
?>
<?php if ($parte === 'etiqueta'): ?>
    <span class="etiqueta-turno" style="--color-turno: <?= esc($t->colorTipo(), 'attr') ?>; --color-turno-texto: <?= esc(\App\Entities\TipoTurno::textoSobre($t->colorTipo()), 'attr') ?>;"><?= esc((string) $t->tipo_nombre) ?></span>
<?php elseif ($parte === 'horario'): ?>
    <span class="text-nowrap"><?= esc($t->horaInicio()) ?> <span aria-hidden="true">→</span><span class="visually-hidden">a</span> <?= esc($t->horaFin()) ?></span><?php if ($t->terminaAlDiaSiguiente()): ?> <span class="text-nowrap">del <?= esc(mb_substr(dia_semana($salida), 0, 3)) ?> <?= esc(date('d/m', strtotime($salida))) ?></span><?php endif; ?>
<?php elseif ($parte === 'frase'): ?>
    <span class="visually-hidden"><?= esc((string) $t->tipo_nombre) ?> en <?= esc((string) $t->sector_nombre) ?><?= (int) $t->sector_critico === 1 ? ' (área crítica)' : '' ?>, de <?= esc($t->horaInicio()) ?> a <?= esc($t->horaFin()) ?><?= $t->terminaAlDiaSiguiente() ? ' del ' . esc(fecha_larga($salida)) : '' ?>, <?= esc($textos[$estado]) ?><?= $t->esExtra() ? ', registrado como hora extra' : '' ?><?= $textoAviso !== '' ? '. ' . esc($textoAviso) : '' ?>.</span>
<?php elseif ($parte === 'extra'): ?>
    <?php if ($t->esExtra()): ?><span class="badge-extra"><span aria-hidden="true">HE</span><span class="visually-hidden">Hora extra</span></span><?php endif; ?>
<?php elseif ($parte === 'acciones'): ?>
    <?php if ($puedeAvisar): ?>
        <a class="btn btn-sm tp-accion-turno" href="<?= base_url('mis-ausencias/nueva/' . (int) $t->id) ?>">
            <i class="bi bi-person-x" aria-hidden="true"></i>Informar ausencia<span class="visually-hidden"> al turno <?= esc((string) $t->tipo_nombre) ?> del <?= esc(fecha_larga((string) $t->fecha)) ?></span>
        </a>
    <?php endif; ?>
<?php else: ?>
    <?php if ($avisoActivo): ?>
        <span class="estado-turno tp-estado-aviso tp-estado-aviso--<?= $aviso->estaCubierta() ? 'cubierta' : 'pendiente' ?>"><i class="bi <?= esc($aviso->iconoEstado(), 'attr') ?>" aria-hidden="true"></i><?= esc($textoAviso) ?></span>
    <?php elseif ($estado === 'en_curso'): ?>
        <span class="estado-turno estado-turno--en-curso"><i class="bi bi-play-circle-fill" aria-hidden="true"></i>En curso</span>
    <?php elseif ($estado === 'proximo'): ?>
        <span class="estado-turno estado-turno--programado"><i class="bi bi-calendar-check" aria-hidden="true"></i>Programado</span>
    <?php else: ?>
        <span class="estado-turno estado-turno--finalizado"><i class="bi bi-check2-circle" aria-hidden="true"></i>Finalizado</span>
    <?php endif; ?>
    <?php if ($avisoRechazado): ?>
        <span class="estado-turno tp-estado-aviso tp-estado-aviso--rechazada"><i class="bi bi-x-circle-fill" aria-hidden="true"></i><?= esc($textoAviso) ?></span>
    <?php endif; ?>
<?php endif; ?>
