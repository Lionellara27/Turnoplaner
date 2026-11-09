<?php
/**
 * Lista de días del cronograma (HU-17): tabla desde 1200 px y agenda por día en pantallas chicas
 * (mismo corte que el resto del sistema, DESIGN.md 4.4). Se usa una o dos veces por página
 * (en la vista mes, los días ya pasados van plegados en un <details>).
 *
 * @var list<string>                                  $dias
 * @var array<string, list<\App\Entities\Asignacion>> $porDia
 * @var array<string, \App\Entities\Asignacion>       $salidas  turno nocturno que termina ese día
 * @var string                                        $hoy, $ahora, $caption
 */
$pieza = static fn ($t, string $parte): string => view('cronograma/_turno', ['t' => $t, 'ahora' => $ahora, 'parte' => $parte]);
$notaSalida = static fn (string $dia): string => isset($salidas[$dia])
    ? 'Sale de ' . esc((string) $salidas[$dia]->tipo_nombre) . ' a las ' . esc($salidas[$dia]->horaFin())
    : '';
?>
<!-- Computadora: tabla -->
<div class="table-responsive border rounded-3 d-none d-xl-block mb-3">
    <table class="table align-middle mb-0">
        <caption class="visually-hidden"><?= esc($caption) ?></caption>
        <thead class="table-light">
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Sector</th>
                <th scope="col">Turno</th>
                <th scope="col">Horario</th>
                <th scope="col">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dias as $dia): ?>
                <?php
                $turnosDia = $porDia[$dia] ?? [];
                $esHoy     = $dia === $hoy;
                $nota      = $notaSalida($dia);
                ob_start(); ?>
                <th scope="row" class="fw-normal text-nowrap" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                    <span class="text-capitalize fw-semibold"><?= esc(dia_semana($dia)) ?></span> <?= esc(fecha_ar($dia)) ?>
                    <?php if ($esHoy): ?><span class="badge-hoy ms-1">Hoy</span><?php endif; ?>
                    <?php if ($nota !== '' && $turnosDia !== []): ?><div class="small text-body-secondary"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i><?= $nota ?></div><?php endif; ?>
                </th>
                <?php $celdaFecha = ob_get_clean(); ?>
                <?php if ($turnosDia === []): ?>
                    <tr class="<?= $esHoy ? 'cronograma__hoy' : '' ?>">
                        <?= $celdaFecha ?>
                        <td colspan="4" class="text-body-secondary">
                            Sin turno asignado<?php if ($nota !== ''): ?> · <i class="bi bi-moon-stars me-1" aria-hidden="true"></i><?= $nota ?><?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($turnosDia as $t): ?>
                        <tr class="<?= $esHoy ? 'cronograma__hoy' : '' ?>">
                            <?= $celdaFecha ?>
                            <td><?= esc((string) $t->sector_nombre) ?><?= (int) $t->sector_critico === 1 ? ' <span class="small text-body-secondary">(área crítica)</span>' : '' ?></td>
                            <td><?= $pieza($t, 'etiqueta') ?></td>
                            <td><?= $pieza($t, 'horario') ?></td>
                            <td><?= $pieza($t, 'estado') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Celular y tablet: agenda por día -->
<ul class="list-unstyled d-xl-none mb-3">
    <?php foreach ($dias as $dia): ?>
        <?php $turnosDia = $porDia[$dia] ?? []; $esHoy = $dia === $hoy; $nota = $notaSalida($dia); ?>
        <li class="dia-cronograma border rounded-3 mb-2<?= $esHoy ? ' dia-cronograma--hoy' : '' ?>">
            <h2 class="h6 fw-semibold px-3 py-2 mb-0<?= $turnosDia !== [] ? ' border-bottom' : '' ?>" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                <span class="text-nowrap"><span class="text-capitalize"><?= esc(dia_semana($dia)) ?></span> <?= esc(fecha_ar($dia)) ?></span>
                <?php if ($esHoy): ?><span class="badge-hoy ms-1">Hoy</span><?php endif; ?>
                <?php if ($turnosDia === []): ?><span class="d-block fw-normal small text-body-secondary">Sin turno asignado</span><?php endif; ?>
                <?php if ($nota !== ''): ?><span class="d-block fw-normal small text-body-secondary"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i><?= $nota ?></span><?php endif; ?>
            </h2>
            <?php foreach ($turnosDia as $t): ?>
                <div class="px-3 py-2">
                    <?= $pieza($t, 'frase') ?>
                    <div aria-hidden="true">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                            <?= $pieza($t, 'etiqueta') ?>
                            <?= $pieza($t, 'estado') ?>
                        </div>
                        <div><i class="bi bi-clock me-1"></i><?= $pieza($t, 'horario') ?></div>
                        <div class="small text-body-secondary"><i class="bi bi-hospital me-1"></i><?= esc((string) $t->sector_nombre) ?><?= (int) $t->sector_critico === 1 ? ' (área crítica)' : '' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </li>
    <?php endforeach; ?>
</ul>
