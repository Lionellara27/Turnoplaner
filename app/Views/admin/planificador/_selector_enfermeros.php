<?php
/**
 * Selector de enfermeros/as (DESIGN.md 4.10): radios reales agrupados en un <fieldset> con <legend> visible,
 * buscador y filtros. Lo usan el formulario de asignación (con las opciones ya armadas por el servidor)
 * y el modal del Planificador (vacío: app.js lo llena con GET admin/asignaciones/disponibilidad).
 * El orden y las etiquetas los decide el servidor: Disponible, Hora extra, No disponible (con motivo).
 *
 * @var string             $prefijo        "form" o "modal" (prefijo de los id)
 * @var string             $idCampo        id del fieldset (destino de los enlaces del resumen de errores)
 * @var list<string>       $especialidades
 * @var list<array>        $opciones       ver _opcion_enfermero.php
 * @var bool               $conEstados     true si las opciones traen la disponibilidad calculada
 * @var string             $ayudaId        id del texto de ayuda (aria-describedby)
 * @var int|null           $excluir        id de la asignación que se reasigna (no cuenta sus horas)
 * @var int|null           $actual         enfermero/a asignado actualmente
 * @var string             $error          error del servidor para el campo usuario_id
 */
$opciones   = $opciones ?? [];
$conEstados = $conEstados ?? false;
$excluir    = $excluir ?? null;
$actual     = $actual ?? null;
$error      = $error ?? '';
$ayudaId    = $ayudaId ?? '';
$total      = count($opciones);

$seleccionado = null;
foreach ($opciones as $o) {
    if (! empty($o['checked'])) {
        $seleccionado = $o;
    }
}
$describe = trim($ayudaId . ($error !== '' ? ' error-usuario_id' : ''));
?>
<fieldset class="selector-enfermeros<?= $error !== '' ? ' is-invalid' : '' ?>" id="<?= esc($idCampo, 'attr') ?>" tabindex="-1"
          data-selector-enfermeros data-filtro-lista data-prefijo="<?= esc($prefijo, 'attr') ?>"
          data-url-disponibilidad="<?= base_url('admin/asignaciones/disponibilidad') ?>"
          <?= $excluir !== null ? 'data-excluir="' . (int) $excluir . '"' : '' ?>
          <?= $actual !== null ? 'data-actual="' . (int) $actual . '"' : '' ?>
          data-mensaje="Seleccione el enfermero/a."
          <?= $describe !== '' ? 'aria-describedby="' . esc($describe, 'attr') . '"' : '' ?>>
    <legend class="form-label fw-semibold mb-2">Enfermero/a *</legend>
    <?php if ($error !== ''): ?>
        <div class="invalid-feedback d-block mt-0 mb-2" id="error-usuario_id"><?= esc($error) ?></div>
    <?php endif; ?>

    <div class="tp-filtro-barra p-3 mb-2">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="<?= esc($prefijo, 'attr') ?>-buscar" class="form-label small fw-semibold mb-1">Buscar</label>
                <input type="search" class="form-control" id="<?= esc($prefijo, 'attr') ?>-buscar" data-filtro-texto
                       placeholder="Apellido, nombre o matrícula" autocomplete="off" enterkeyhint="search">
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="<?= esc($prefijo, 'attr') ?>-especialidad" class="form-label small fw-semibold mb-1">Especialidad</label>
                <select class="form-select" id="<?= esc($prefijo, 'attr') ?>-especialidad" data-filtro-campo="especialidad">
                    <option value="" selected>Todas</option>
                    <?php foreach ($especialidades as $esp): ?>
                        <option value="<?= esc($esp, 'attr') ?>"><?= esc($esp) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label for="<?= esc($prefijo, 'attr') ?>-disponibilidad" class="form-label small fw-semibold mb-1">Disponibilidad</label>
                <select class="form-select" id="<?= esc($prefijo, 'attr') ?>-disponibilidad" data-filtro-grupo <?= $conEstados ? '' : 'disabled' ?>>
                    <option value="" selected>Todos</option>
                    <option value="disponible">Con horas disponibles</option>
                    <option value="extra">Hora extra</option>
                </select>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <p class="small text-body-secondary mb-0" role="status" data-filtro-contador data-singular="enfermero/a" data-plural="enfermeros/as"><?= $total > 0 ? $total . ($total === 1 ? ' enfermero/a' : ' enfermeros/as') : '' ?></p>
        <button type="button" class="btn btn-outline-secondary d-none" data-filtro-restablecer data-solo-con-filtro>
            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
        </button>
    </div>

    <div data-sel-estado></div>

    <div class="tp-enfermeros-scroll" data-filtro-items>
        <?php foreach ($opciones as $o): ?>
            <?= view('admin/planificador/_opcion_enfermero', ['o' => $o, 'prefijo' => $prefijo]) ?>
        <?php endforeach; ?>
    </div>

    <div class="text-center py-4 px-3 border rounded-3 d-none" data-filtro-vacio>
        <i class="bi bi-person-x fs-2 text-body-secondary d-block mb-1" aria-hidden="true"></i>
        <p class="small text-body-secondary mb-2">No hay enfermeros/as que coincidan con los filtros.</p>
        <button type="button" class="btn btn-outline-secondary" data-filtro-restablecer>
            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
        </button>
    </div>

    <p class="tp-seleccionado small mt-2 mb-0" data-sel-seleccionado>
        <?php if ($seleccionado !== null): ?>
            Seleccionado: <strong><?= esc((string) $seleccionado['nombre']) ?></strong>
        <?php else: ?>
            Ningún enfermero/a seleccionado.
        <?php endif; ?>
    </p>

</fieldset>
