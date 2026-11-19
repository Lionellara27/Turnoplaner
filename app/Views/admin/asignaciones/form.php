<?php
/**
 * HU-13: asignar (alta) o reasignar (edición) un turno.
 *
 * @var \App\Entities\Asignacion|null   $asignacion  null al asignar
 * @var list<\App\Entities\Usuario>     $enfermeros  activos (ordenados por prioridad si se conoce turno y fecha)
 * @var list<\App\Entities\Sector>      $sectores    activos
 * @var list<\App\Entities\TipoTurno>   $tipos       activos
 * @var array<string, mixed>            $previo      valores iniciales
 * @var array<int, array{minutos:int, cumple_carga:bool, es_extra:bool, motivo:string}> $disponibilidad
 * @var string                          $calculoPara texto "Noche del lunes 09/11/2026" o vacío
 * @var string                          $fechaMaxima Y-m-d
 */
$disponibilidad = $disponibilidad ?? [];
$especialidades = $especialidades ?? \App\Models\UsuarioModel::ESPECIALIDADES;
$editando = $asignacion !== null;
$valor    = static fn (string $campo): string => (string) old($campo, (string) ($previo[$campo] ?? ''), false);
$accion   = $editando ? base_url('admin/asignaciones/' . $asignacion->id . '/editar') : base_url('admin/asignaciones/crear');
$volver   = base_url('admin/planificador') . '?' . http_build_query(array_filter([
    'sector' => (int) ($previo['sector_id'] ?? 0) ?: null,
    'mes'    => substr($valor('fecha') ?: date('Y-m-d'), 0, 7),
]), '', '&amp;');
$faltanDatos = $enfermeros === [] || $sectores === [] || $tipos === [];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white"><?= $editando ? 'Reasignar turno' : 'Asignar turno' ?></h1>
                <p class="mb-0 small subtitulo">Planificación mensual · Enfermero/a, zona, fecha y franja horaria</p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">

        <?php if ($faltanDatos): ?>
            <div class="text-center py-4">
                <i class="bi bi-exclamation-circle fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-2">Para asignar turnos hace falta tener cargado:</p>
                <ul class="list-unstyled mb-3">
                    <?php if ($enfermeros === []): ?><li><a href="<?= base_url('admin/usuarios/crear') ?>">al menos un enfermero/a activo</a></li><?php endif; ?>
                    <?php if ($sectores === []): ?><li><a href="<?= base_url('admin/sectores/crear') ?>">al menos un sector activo</a></li><?php endif; ?>
                    <?php if ($tipos === []): ?><li><a href="<?= base_url('admin/tipos-turno/crear') ?>">al menos un tipo de turno activo</a></li><?php endif; ?>
                </ul>
            </div>
        <?php else: ?>

        <div class="alerta alerta--info d-flex align-items-start gap-2 mb-4" role="note">
            <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
            <div class="small">
                La planificación mensual está habilitada hasta <strong>1 mes por adelantado</strong> (hasta el <?= esc(fecha_ar($fechaMaxima)) ?>).
                Tienen prioridad quienes aún no completaron su carga horaria; quienes ya la cumplieron pueden seguir tomando turnos como <strong>horas extras cubiertas</strong> (siempre que no se superpongan).
            </div>
        </div>

        <form action="<?= $accion ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando...">
            <?= csrf_field() ?>
            <p class="small text-body-secondary">Los campos con * son obligatorios.</p>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold mb-1 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="bi bi-person-badge me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>Seleccionar enfermero/a *</span>
                        <?php if ($calculoPara !== ''): ?>
                            <span class="badge rounded-pill" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25); font-size: 0.72rem;">
                                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Prioridad calculada para: <?= esc($calculoPara) ?>
                            </span>
                        <?php endif; ?>
                    </label>

                    <!-- Select nativo sincronizado para compatibilidad y validación -->
                    <select class="d-none<?= clase_invalido('usuario_id') ?>" id="usuario_id" name="usuario_id" required
                            aria-describedby="ayuda-enfermero" data-mensaje="Elija el enfermero/a.">
                        <option value="" <?= $valor('usuario_id') === '' || $valor('usuario_id') === '0' ? 'selected' : '' ?> disabled>Elija un enfermero/a</option>
                        <?php foreach ($enfermeros as $e): ?>
                            <?php
                            $info    = $disponibilidad[(int) $e->id] ?? null;
                            $noPuede = $info !== null && $info['motivo'] !== '' && $valor('usuario_id') !== (string) $e->id;
                            ?>
                            <option value="<?= (int) $e->id ?>" <?= $valor('usuario_id') === (string) $e->id ? 'selected' : '' ?> <?= $noPuede ? 'disabled' : '' ?>>
                                <?= esc($e->getNombreCompleto()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Barra de filtros del selector visual -->
                    <div class="tp-filtro-barra p-3 mb-2">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0 text-body-secondary">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                    </span>
                                    <input type="search" class="form-control border-start-0 ps-0" id="buscarEnfermeroForm"
                                           placeholder="Buscar por apellido, nombre o matrícula..." autocomplete="off"
                                           aria-label="Buscar enfermero/a">
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <select class="form-select form-select-sm" id="filtroEspecialidadForm" aria-label="Filtrar por especialidad">
                                    <option value="">Todas las especialidades</option>
                                    <?php foreach ($especialidades as $esp): ?>
                                        <option value="<?= esc($esp, 'attr') ?>"><?= esc($esp) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <select class="form-select form-select-sm" id="filtroCargaForm" aria-label="Filtrar por disponibilidad">
                                    <option value="todos">Todas las cargas</option>
                                    <option value="disponibles">Con horas disponibles</option>
                                    <option value="extras">Con horas extras</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: var(--color-borde-suave) !important;">
                            <span class="small text-body-secondary" id="contadorEnfermerosForm" style="font-size: 0.75rem;">
                                Mostrando <?= count($enfermeros) ?> enfermeros/as
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-body-secondary d-none d-sm-inline" style="font-size: 0.72rem;"><i class="bi bi-sort-down me-1" aria-hidden="true"></i>Ordenar:</span>
                                <select class="form-select form-select-sm py-0 px-2" id="ordenarEnfermeroForm" style="font-size: 0.75rem; width: auto;" aria-label="Ordenar enfermeros">
                                    <option value="prioridad" selected>Prioridad de guardia</option>
                                    <option value="carga-asc">Menor carga horaria</option>
                                    <option value="carga-desc">Mayor carga horaria</option>
                                    <option value="alfabetico">Alfabético (A - Z)</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none py-0 px-1 text-danger d-none" id="btnLimpiarFiltrosForm" style="font-size: 0.75rem;" title="Restablecer filtros">
                                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Listado interactivo de enfermeros/as en tarjetas -->
                    <div class="tp-enfermeros-scroll" id="listaEnfermerosForm" role="radiogroup" aria-label="Lista de enfermeros/as para asignar">
                        <?php
                        $enfermeroSeleccionadoTexto = 'Ningún enfermero/a seleccionado';
                        $ordenIndex = 0;
                        ?>
                        <?php foreach ($enfermeros as $e): ?>
                            <?php
                            $eid         = (int) $e->id;
                            $info        = $disponibilidad[$eid] ?? null;
                            $maximo      = (int) $e->carga_horaria_max;
                            $horasTexto  = $info !== null
                                ? \App\Models\AsignacionModel::horasTexto($info['minutos']) . " de {$maximo} h"
                                : "Tope: {$maximo} h/sem";
                            $noPuede     = $info !== null && $info['motivo'] !== '' && $valor('usuario_id') !== (string) $eid;
                            $esSeleccionado = $valor('usuario_id') === (string) $eid;
                            $tieneExtras = $info !== null && ($info['es_extra'] ?? false);
                            $iniciales   = mb_strtoupper(mb_substr($e->apellido ?? '', 0, 1) . mb_substr($e->nombre ?? '', 0, 1));
                            $minutosNum  = $info !== null ? (int) $info['minutos'] : 0;
                            $ordenIndex++;

                            if ($esSeleccionado) {
                                $enfermeroSeleccionadoTexto = esc($e->getNombreCompleto()) . (! empty($e->matricula) ? ' (Mat: ' . esc((string) $e->matricula) . ')' : '') . (! empty($e->especialidad) ? ' · ' . esc($e->especialidad) : '');
                            }
                            ?>
                            <div class="tp-enfermero-opcion mb-2<?= $esSeleccionado ? ' is-selected' : '' ?><?= $noPuede ? ' is-disabled' : '' ?>"
                                 role="radio"
                                 tabindex="<?= $noPuede ? '-1' : '0' ?>"
                                 aria-checked="<?= $esSeleccionado ? 'true' : 'false' ?>"
                                 aria-disabled="<?= $noPuede ? 'true' : 'false' ?>"
                                 data-id="<?= $eid ?>"
                                 data-nombre="<?= esc(mb_strtolower($e->getNombreCompleto()), 'attr') ?>"
                                 data-nombre-mostrar="<?= esc($e->getNombreCompleto(), 'attr') ?>"
                                 data-matricula="<?= esc(mb_strtolower((string) $e->matricula), 'attr') ?>"
                                 data-matricula-mostrar="<?= esc((string) $e->matricula, 'attr') ?>"
                                 data-especialidad="<?= esc($e->especialidad ?? '', 'attr') ?>"
                                 data-minutos="<?= $minutosNum ?>"
                                 data-tiene-extras="<?= $tieneExtras ? '1' : '0' ?>"
                                 data-disabled="<?= $noPuede ? '1' : '0' ?>"
                                 data-orden-prioridad="<?= $ordenIndex ?>">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="tp-avatar-circulo" aria-hidden="true"><?= esc($iniciales) ?></div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate small text-dark">
                                            <?= esc($e->getNombreCompleto()) ?>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-1 small text-body-secondary mt-1">
                                            <?php if (! empty($e->especialidad)): ?>
                                                <span class="badge tp-badge-especialidad"><?= esc($e->especialidad) ?></span>
                                            <?php endif; ?>
                                            <?php if (! empty($e->matricula)): ?>
                                                <span class="tp-matricula">Mat: <strong><?= esc((string) $e->matricula) ?></strong></span>
                                            <?php endif; ?>
                                            <span>· <?= esc($horasTexto) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                                    <div class="text-end">
                                        <?php if ($noPuede): ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border rounded-pill" style="font-size: 0.68rem;">
                                                <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>No disponible: <?= esc($info['motivo']) ?>
                                            </span>
                                        <?php elseif ($tieneExtras): ?>
                                            <span class="badge rounded-pill" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25); font-size: 0.68rem;">
                                                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Hora Extra
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill" style="font-size: 0.68rem;">
                                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Disponible / Prioridad
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tp-check-circulo" aria-hidden="true">
                                        <i class="bi bi-check-lg"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div id="sinResultadosForm" class="text-center py-4 border rounded-3 bg-light-subtle d-none">
                            <i class="bi bi-person-x fs-2 text-body-secondary d-block mb-1" aria-hidden="true"></i>
                            <p class="small text-body-secondary mb-1">No se encontraron enfermeros con los criterios de búsqueda.</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnRestablecerForm">
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
                            </button>
                        </div>
                    </div>

                    <!-- Enfermero seleccionado -->
                    <div class="mt-2 p-2 px-3 rounded-3 border bg-white d-flex align-items-center justify-content-between gap-2">
                        <div class="small">
                            <span class="text-body-secondary me-1">Seleccionado:</span>
                            <span id="textoEnfermeroSeleccionadoForm">
                                <?php if ($valor('usuario_id') !== '' && $valor('usuario_id') !== '0'): ?>
                                    <strong class="text-dark"><?= esc($enfermeroSeleccionadoTexto) ?></strong>
                                <?php else: ?>
                                    <span class="text-body-secondary fst-italic">Ningún enfermero/a seleccionado</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="text-body-secondary small fst-italic d-none d-md-inline" style="font-size: 0.72rem;">Hacé clic sobre una tarjeta para seleccionar</span>
                    </div>

                    <?= feedback_campo('usuario_id') ?>

                    <div id="ayuda-enfermero" class="form-text mt-1">
                        Ordenados por prioridad: primero aparecen quienes aún tienen horas disponibles dentro de su contrato y luego quienes pueden cubrir horas extras.
                        <?php if ($calculoPara !== ''): ?>
                            Calculado para <strong><?= esc($calculoPara) ?></strong>.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('sector_id') ?>" id="sector_id" name="sector_id" required data-mensaje="Elija el sector.">
                            <option value="" <?= in_array($valor('sector_id'), ['', '0'], true) ? 'selected' : '' ?> disabled>Elija un sector</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= (int) $s->id ?>" <?= $valor('sector_id') === (string) $s->id ? 'selected' : '' ?>>
                                    <?= esc($s->nombre) ?><?= $s->esCritico() ? ' (área crítica)' : '' ?> · mínimo <?= (int) $s->dotacion_minima ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="sector_id">Zona / Sector *</label>
                        <?= feedback_campo('sector_id') ?>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-floating">
                        <input type="date" class="form-control<?= clase_invalido('fecha') ?>" id="fecha" name="fecha"
                               value="<?= esc($valor('fecha')) ?>" min="<?= date('Y-m-d') ?>" max="<?= esc($fechaMaxima ?? '') ?>" required
                               aria-describedby="ayuda-fecha" data-mensaje="Elija una fecha válida dentro del período habilitado.">
                        <label for="fecha">Fecha de inicio del turno *</label>
                        <?= feedback_campo('fecha') ?>
                    </div>
                    <div id="ayuda-fecha" class="form-text">Habilitado hasta <?= esc(fecha_ar($fechaMaxima)) ?> (1 mes por adelantado).</div>
                </div>

                <div class="col-12">
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('tipo_turno_id') ?>" id="tipo_turno_id" name="tipo_turno_id" required data-mensaje="Elija el turno.">
                            <option value="" <?= in_array($valor('tipo_turno_id'), ['', '0'], true) ? 'selected' : '' ?> disabled>Elija un turno</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= (int) $t->id ?>" data-sector="<?= (int) $t->sector_id ?>"
                                        <?= $valor('tipo_turno_id') === (string) $t->id ? 'selected' : '' ?>>
                                    <?= esc($t->nombre) ?> · <?= esc($t->rangoTexto()) ?> · <?= esc($t->duracionTexto()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="tipo_turno_id">Turno de la zona (franja horaria) *</label>
                        <?= feedback_campo('tipo_turno_id') ?>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                <div class="d-flex flex-column flex-sm-row gap-2 order-2 order-sm-1 align-self-stretch align-self-sm-auto">
                    <a href="<?= $volver ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">Cancelar</a>
                    <?php if ($editando): ?>
                        <button type="button" class="btn btn-outline-danger px-4 py-2 fw-medium" data-bs-toggle="modal" data-bs-target="#modalEliminarTurno">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar turno
                        </button>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= $editando ? 'Guardar cambios' : 'Asignar turno' ?>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selSector = document.getElementById('sector_id');
    const selTipo   = document.getElementById('tipo_turno_id');
    if (!selSector || !selTipo) return;

    function filtrarTurnosPorSector() {
        const sectorId = selSector.value;
        let actualValido = false;

        Array.from(selTipo.options).forEach(opt => {
            if (!opt.value) return;
            const coincide = sectorId !== '' && opt.dataset.sector === sectorId;
            opt.hidden   = !coincide;
            opt.disabled = !coincide;
            if (opt.selected && coincide) {
                actualValido = true;
            }
        });

        if (!actualValido) {
            selTipo.value = '';
        }
    }

    selSector.addEventListener('change', filtrarTurnosPorSector);
    filtrarTurnosPorSector();

    /* ---------- Selector interactivo de enfermeros en formulario ---------- */
    const listaForm       = document.getElementById('listaEnfermerosForm');
    const inputBuscarForm = document.getElementById('buscarEnfermeroForm');
    const selectEspForm   = document.getElementById('filtroEspecialidadForm');
    const selectCargaForm = document.getElementById('filtroCargaForm');
    const selectOrdenForm = document.getElementById('ordenarEnfermeroForm');
    const contadorForm    = document.getElementById('contadorEnfermerosForm');
    const sinResForm      = document.getElementById('sinResultadosForm');
    const btnLimpiarForm  = document.getElementById('btnLimpiarFiltrosForm');
    const btnRestabForm   = document.getElementById('btnRestablecerForm');
    const selUsuario      = document.getElementById('usuario_id');
    const txtSeleccionado = document.getElementById('textoEnfermeroSeleccionadoForm');

    function filtrarYOrdenarForm() {
        if (!listaForm) return;
        const q     = (inputBuscarForm ? inputBuscarForm.value.trim().toLowerCase() : '');
        const esp   = (selectEspForm ? selectEspForm.value : '');
        const carga = (selectCargaForm ? selectCargaForm.value : 'todos');
        const orden = (selectOrdenForm ? selectOrdenForm.value : 'prioridad');

        const hayFiltro = q !== '' || esp !== '' || carga !== 'todos' || orden !== 'prioridad';
        if (btnLimpiarForm) {
            btnLimpiarForm.classList.toggle('d-none', !hayFiltro);
        }

        const items = Array.from(listaForm.querySelectorAll('.tp-enfermero-opcion'));
        let visibles = 0;

        items.forEach(function (card) {
            const nombre      = (card.getAttribute('data-nombre') || '').toLowerCase();
            const mat         = (card.getAttribute('data-matricula') || '').toLowerCase();
            const espItem     = card.getAttribute('data-especialidad') || '';
            const tieneExtras = card.getAttribute('data-tiene-extras') === '1';

            const matchTexto = !q || nombre.includes(q) || mat.includes(q);
            const matchEsp   = !esp || espItem === esp;
            let matchCarga   = true;
            if (carga === 'disponibles') {
                matchCarga = !tieneExtras;
            } else if (carga === 'extras') {
                matchCarga = tieneExtras;
            }

            const mostrar = matchTexto && matchEsp && matchCarga;
            card.classList.toggle('d-none', !mostrar);
            if (mostrar) {
                visibles++;
            }
        });

        items.sort(function (a, b) {
            if (orden === 'prioridad') {
                const pA = parseInt(a.getAttribute('data-orden-prioridad') || '0', 10);
                const pB = parseInt(b.getAttribute('data-orden-prioridad') || '0', 10);
                return pA - pB;
            }
            if (orden === 'alfabetico') {
                return (a.getAttribute('data-nombre') || '').localeCompare(b.getAttribute('data-nombre') || '', 'es', { sensitivity: 'base' });
            }
            const minA = parseInt(a.getAttribute('data-minutos') || '0', 10);
            const minB = parseInt(b.getAttribute('data-minutos') || '0', 10);
            if (orden === 'carga-asc') {
                return minA - minB;
            }
            if (orden === 'carga-desc') {
                return minB - minA;
            }
            return 0;
        });

        items.forEach(function (card) {
            listaForm.appendChild(card);
        });

        if (contadorForm) {
            contadorForm.textContent = 'Mostrando ' + visibles + (visibles === 1 ? ' enfermero/a' : ' enfermeros/as');
        }
        if (sinResForm) {
            sinResForm.classList.toggle('d-none', visibles > 0);
        }
    }

    function seleccionarEnfermeroForm(card) {
        if (!card || card.getAttribute('data-disabled') === '1') return;
        const id     = card.getAttribute('data-id');
        const nombre = card.getAttribute('data-nombre-mostrar') || '';
        const mat    = card.getAttribute('data-matricula-mostrar') || '';
        const esp    = card.getAttribute('data-especialidad') || '';

        listaForm.querySelectorAll('.tp-enfermero-opcion').forEach(function (c) {
            c.classList.remove('is-selected');
            c.setAttribute('aria-checked', 'false');
        });
        card.classList.add('is-selected');
        card.setAttribute('aria-checked', 'true');

        if (selUsuario) {
            selUsuario.value = id;
            selUsuario.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (txtSeleccionado) {
            txtSeleccionado.innerHTML = '<strong class="text-dark">' + nombre + '</strong>' +
                (mat ? ' · <span class="tp-matricula">Mat: ' + mat + '</span>' : '') +
                (esp ? ' · <span class="badge tp-badge-especialidad">' + esp + '</span>' : '');
        }
    }

    function restablecerFiltrosForm() {
        if (inputBuscarForm) inputBuscarForm.value = '';
        if (selectEspForm) selectEspForm.value = '';
        if (selectCargaForm) selectCargaForm.value = 'todos';
        if (selectOrdenForm) selectOrdenForm.value = 'prioridad';
        filtrarYOrdenarForm();
        if (inputBuscarForm) inputBuscarForm.focus();
    }

    if (inputBuscarForm) inputBuscarForm.addEventListener('input', filtrarYOrdenarForm);
    if (selectEspForm) selectEspForm.addEventListener('change', filtrarYOrdenarForm);
    if (selectCargaForm) selectCargaForm.addEventListener('change', filtrarYOrdenarForm);
    if (selectOrdenForm) selectOrdenForm.addEventListener('change', filtrarYOrdenarForm);
    if (btnLimpiarForm) btnLimpiarForm.addEventListener('click', restablecerFiltrosForm);
    if (btnRestabForm) btnRestabForm.addEventListener('click', restablecerFiltrosForm);

    if (listaForm) {
        listaForm.addEventListener('click', function (e) {
            const card = e.target.closest('.tp-enfermero-opcion');
            if (card) {
                seleccionarEnfermeroForm(card);
            }
        });
        listaForm.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const card = e.target.closest('.tp-enfermero-opcion');
                if (card) {
                    e.preventDefault();
                    seleccionarEnfermeroForm(card);
                }
            }
        });
    }
});
</script>

<?php if ($editando): ?>
    <div class="modal fade" id="modalEliminarTurno" tabindex="-1" aria-labelledby="tituloEliminarTurno" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6" id="tituloEliminarTurno"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">¿Eliminar el turno del <strong><?= esc(fecha_larga((string) $asignacion->fecha)) ?></strong>?</p>
                    <p class="small text-body-secondary mb-0">El enfermero/a deja de tenerlo en su cronograma y el sector puede quedar por debajo de su dotación mínima.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form action="<?= base_url('admin/asignaciones/' . $asignacion->id . '/eliminar') ?>" method="post" class="d-inline" data-enviando="Eliminando...">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger">Eliminar turno</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>