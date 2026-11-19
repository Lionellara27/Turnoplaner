/* ==========================================================
   TurnoPlaner - interacciones de interfaz (JS sin dependencias)
   Ver .agents/skills/ux-ui-expert.md (secciones 3, 5 y 6)
   ========================================================== */

// Si el usuario vuelve con "Atrás", el navegador puede mostrar la página congelada
// con botones deshabilitados y spinners: se recarga para dejarla en estado limpio.
window.addEventListener('pageshow', function (evento) {
    if (evento.persisted) { window.location.reload(); }
});

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* ---------- Utilidades de validación visual ---------- */

    function contenedorDe(input) {
        return input.closest('.input-group') || input.closest('.form-floating') || input.parentElement;
    }

    function enlazarDescripcion(input, id) {
        var actual = (input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
        if (actual.indexOf(id) === -1) { actual.push(id); }
        input.setAttribute('aria-describedby', actual.join(' '));
    }

    function marcarInvalido(input, mensaje) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        var floating = input.closest('.input-group .form-floating');
        if (floating) { floating.classList.add('is-invalid'); }

        var contenedor = contenedorDe(input);
        var feedback = contenedor.querySelector(':scope > .invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.id = 'error-' + input.id;
            contenedor.appendChild(feedback);
        }
        feedback.textContent = mensaje;
        enlazarDescripcion(input, feedback.id);
    }

    function limpiarInvalido(input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        var floating = input.closest('.input-group .form-floating');
        if (floating) { floating.classList.remove('is-invalid'); }
    }

    function mensajePara(input) {
        return input.getAttribute('data-mensaje') || input.validationMessage || 'Revise este campo.';
    }

    function validarExtra(input) {
        var selectorIgual = input.getAttribute('data-igual-a');
        if (selectorIgual) {
            var original = document.querySelector(selectorIgual);
            input.setCustomValidity(original && input.value !== original.value ? 'no-coincide' : '');
        }
    }

    document.querySelectorAll('.form-control.is-invalid, .form-select.is-invalid').forEach(function (input) {
        input.setAttribute('aria-invalid', 'true');
        var feedback = document.getElementById('error-' + input.id);
        if (feedback) { enlazarDescripcion(input, feedback.id); }
    });

    var resumen = document.getElementById('resumen-errores');
    if (resumen) { resumen.focus(); }

    /* ---------- Estado "cargando" en todo formulario POST (evita doble envío) ---------- */
    function marcarEnviando(form) {
        var boton = form.querySelector('button[type="submit"]');
        if (!boton || boton.disabled) { return; }
        boton.disabled = true;
        var texto = form.getAttribute('data-enviando') || 'Enviando...';
        boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + texto;
    }

    document.querySelectorAll('form[method="post"]:not([data-ajax-asignar]):not([data-ajax-quitar])').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (form.classList.contains('needs-validation')) { return; }
            if (form.dataset.enviado === '1') { evento.preventDefault(); return; }
            form.dataset.enviado = '1';
            marcarEnviando(form);
        });
    });

    /* ---------- Formularios con validación del lado del cliente ---------- */
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        var campos = form.querySelectorAll('input, select, textarea');

        campos.forEach(function (input) {
            input.addEventListener('input', function () {
                validarExtra(input);
                if (input.classList.contains('is-invalid') && input.checkValidity()) {
                    limpiarInvalido(input);
                }
            });
        });

        form.addEventListener('submit', function (evento) {
            if (form.dataset.enviado === '1') { evento.preventDefault(); return; }

            var primero = null;
            campos.forEach(function (input) {
                if (input.type === 'hidden' || input.disabled) { return; }
                validarExtra(input);
                if (!input.checkValidity()) {
                    marcarInvalido(input, mensajePara(input));
                    if (!primero) { primero = input; }
                } else {
                    limpiarInvalido(input);
                }
            });

            if (primero) {
                evento.preventDefault();
                evento.stopPropagation();
                primero.focus();
                return;
            }

            form.dataset.enviado = '1';
            marcarEnviando(form);
        });
    });

    /* ---------- Mostrar / ocultar contraseña ---------- */
    document.querySelectorAll('[data-mostrar-clave]').forEach(function (boton) {
        boton.setAttribute('aria-pressed', 'false');
        boton.addEventListener('click', function () {
            var input = document.querySelector(boton.getAttribute('data-mostrar-clave'));
            if (!input) { return; }
            var mostrar = input.type === 'password';
            input.type = mostrar ? 'text' : 'password';
            boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
            boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
            var icono = boton.querySelector('.bi');
            if (icono) {
                icono.classList.toggle('bi-eye', !mostrar);
                icono.classList.toggle('bi-eye-slash', mostrar);
            }
        });
    });

    /* ---------- Requisitos de contraseña que se tildan en vivo ---------- */
    document.querySelectorAll('[data-requisitos]').forEach(function (input) {
        var lista = document.querySelector(input.getAttribute('data-requisitos'));
        if (!lista) { return; }
        var reglas = {
            largo: function (v) { return v.length >= 8; },
            letra: function (v) { return /[A-Za-z]/.test(v); },
            numero: function (v) { return /\d/.test(v); }
        };
        lista.querySelectorAll('[data-regla]').forEach(function (item) {
            var estado = document.createElement('span');
            estado.className = 'visually-hidden estado-regla';
            item.appendChild(estado);
        });
        function actualizar() {
            lista.querySelectorAll('[data-regla]').forEach(function (item) {
                var regla = reglas[item.getAttribute('data-regla')];
                var cumple = regla ? regla(input.value) : false;
                item.classList.toggle('cumple', cumple);
                item.querySelector('.estado-regla').textContent = cumple ? ' (cumplido)' : ' (pendiente)';
            });
        }
        input.addEventListener('input', actualizar);
        actualizar();
    });

    /* ---------- Duración del tipo de turno en vivo (HU-11) ---------- */
    var inicioTurno = document.querySelector('[data-duracion="inicio"]');
    var finTurno = document.querySelector('[data-duracion="fin"]');
    var cajaDuracion = document.getElementById('duracion-calculada');
    if (inicioTurno && finTurno && cajaDuracion) {
        var textoDuracion = cajaDuracion.querySelector('[data-duracion-texto]');
        var aMinutos = function (hora) {
            var partes = hora.split(':');
            return parseInt(partes[0], 10) * 60 + parseInt(partes[1], 10);
        };
        var calcularDuracion = function () {
            cajaDuracion.classList.remove('es-error');
            if (!inicioTurno.value || !finTurno.value) {
                textoDuracion.textContent = 'Complete el horario para ver la duración.';
                return;
            }
            var minutos = (aMinutos(finTurno.value) - aMinutos(inicioTurno.value) + 1440) % 1440;
            if (minutos === 0) {
                cajaDuracion.classList.add('es-error');
                textoDuracion.textContent = 'La hora de fin no puede ser igual a la de inicio.';
                return;
            }
            var horas = Math.floor(minutos / 60);
            var resto = minutos % 60;
            var texto = 'Duración: ' + horas + ' h' + (resto ? ' ' + resto + ' min' : '');
            if (aMinutos(finTurno.value) <= aMinutos(inicioTurno.value)) {
                texto += ' · cruza la medianoche (termina al día siguiente)';
            }
            textoDuracion.textContent = texto;
        };
        inicioTurno.addEventListener('input', calcularDuracion);
        finTurno.addEventListener('input', calcularDuracion);
        calcularDuracion();
    }

    /* ---------- Buscador que filtra las opciones de un select ---------- */
    document.querySelectorAll('[data-filtrar-select]').forEach(function (buscador) {
        var select = document.querySelector(buscador.getAttribute('data-filtrar-select'));
        if (!select) { return; }
        var normalizar = function (texto) {
            return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        };
        buscador.addEventListener('input', function () {
            var buscado = normalizar(buscador.value.trim());
            Array.prototype.forEach.call(select.options, function (opcion) {
                if (!opcion.value) { return; }
                opcion.hidden = buscado !== '' && normalizar(opcion.textContent).indexOf(buscado) === -1;
            });
        });
    });

    /* ---------- Planificador: agregar y quitar personal con modal + AJAX ---------- */
    var modalAsignar = document.getElementById('modalAsignar');
    var modalQuitar = document.getElementById('modalQuitar');

    var mostrarErrores = function (contenedor, errores) {
        var lista = Object.keys(errores).map(function (campo) { return errores[campo]; });
        contenedor.innerHTML = '';
        var ul = document.createElement('ul');
        ul.className = 'mb-0 ps-3 small';
        lista.forEach(function (texto) {
            var li = document.createElement('li');
            li.textContent = texto;
            ul.appendChild(li);
        });
        contenedor.appendChild(ul);
        contenedor.classList.remove('d-none');
        contenedor.setAttribute('tabindex', '-1');
        contenedor.focus();
    };

    var enviarAjax = function (form, url, botonOriginal) {
        var boton = form.querySelector('button[type="submit"]');
        var errores = form.querySelector('[data-errores-modal]');
        var textoOriginal = boton ? (boton.getAttribute('data-texto-original') || boton.textContent.trim()) : 'Guardar';

        if (boton) {
            boton.setAttribute('data-texto-original', textoOriginal);
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando...';
        }
        if (errores) {
            errores.classList.add('d-none');
        }

        function restaurarBoton() {
            if (boton) {
                boton.disabled = false;
                boton.textContent = textoOriginal;
            }
        }

        fetch(url, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        }).then(function (respuesta) {
            if (respuesta.status === 403) {
                window.location.reload();
                return null;
            }
            if (respuesta.redirected) {
                window.location.href = respuesta.url;
                return null;
            }
            return respuesta.text().then(function (texto) {
                try {
                    return { estado: respuesta.status, datos: JSON.parse(texto) };
                } catch (e) {
                    if (respuesta.ok) {
                        window.location.reload();
                        return null;
                    }
                    throw e;
                }
            });
        }).then(function (r) {
            if (!r) { return; }

            if (r.datos && r.datos.csrf) {
                document.querySelectorAll('input[name="csrf_test_name"]').forEach(function (campo) {
                    campo.value = r.datos.csrf;
                });
            }

            if (r.datos && r.datos.ok) {
                window.location.reload();
                return;
            }

            restaurarBoton();
            if (errores) {
                mostrarErrores(errores, (r.datos && r.datos.errores) || { general: 'No se pudo completar la operación. Intente nuevamente.' });
            }
        }).catch(function () {
            restaurarBoton();
            if (botonOriginal && botonOriginal.href) {
                window.location.href = botonOriginal.href;
            } else if (errores) {
                mostrarErrores(errores, { general: 'Error de conexión o sesión vencida. Recargue la página e intente nuevamente.' });
            }
        });
    };

    if (modalAsignar && window.bootstrap) {
        var instanciaAsignar = bootstrap.Modal.getOrCreateInstance(modalAsignar);
        var formAsignar = modalAsignar.querySelector('form[data-ajax-asignar]');
        var origenAsignar = null;

        var inputUsuarioModal   = formAsignar.querySelector('[name="usuario_id"]');
        var inputBuscarModal    = modalAsignar.querySelector('#buscarEnfermeroModal');
        var selectEspModal      = modalAsignar.querySelector('#filtroEspecialidadModal');
        var selectCargaModal    = modalAsignar.querySelector('#filtroCargaModal');
        var selectOrdenModal    = modalAsignar.querySelector('#ordenarEnfermeroModal');
        var contadorModal       = modalAsignar.querySelector('#contadorEnfermerosModal');
        var sinResultadosModal  = modalAsignar.querySelector('#sinResultadosModal');
        var btnLimpiarModal     = modalAsignar.querySelector('#btnLimpiarFiltrosModal');
        var btnRestablecerModal = modalAsignar.querySelector('#btnRestablecerModal');
        var textoSeleccionado   = modalAsignar.querySelector('#textoEnfermeroSeleccionado');
        var listaOpcionesModal  = modalAsignar.querySelector('#listaEnfermerosModal');

        function filtrarYOrdenarModal() {
            if (!listaOpcionesModal) { return; }
            var q     = (inputBuscarModal ? inputBuscarModal.value.trim().toLowerCase() : '');
            var esp   = (selectEspModal ? selectEspModal.value : '');
            var carga = (selectCargaModal ? selectCargaModal.value : 'todos');
            var orden = (selectOrdenModal ? selectOrdenModal.value : 'carga-asc');

            var hayFiltro = q !== '' || esp !== '' || carga !== 'todos' || orden !== 'carga-asc';
            if (btnLimpiarModal) {
                btnLimpiarModal.classList.toggle('d-none', !hayFiltro);
            }

            var items = Array.from(listaOpcionesModal.querySelectorAll('.tp-enfermero-opcion'));
            var visibles = 0;

            items.forEach(function (el) {
                var nombre      = (el.getAttribute('data-nombre') || '').toLowerCase();
                var mat         = (el.getAttribute('data-matricula') || '').toLowerCase();
                var espItem     = el.getAttribute('data-especialidad') || '';
                var tieneExtras = el.getAttribute('data-tiene-extras') === '1';

                var matchTexto = !q || nombre.indexOf(q) !== -1 || mat.indexOf(q) !== -1;
                var matchEsp   = !esp || espItem === esp;
                var matchCarga = true;
                if (carga === 'disponibles') {
                    matchCarga = !tieneExtras;
                } else if (carga === 'extras') {
                    matchCarga = tieneExtras;
                }

                var mostrar = matchTexto && matchEsp && matchCarga;
                el.classList.toggle('d-none', !mostrar);
                if (mostrar) {
                    visibles++;
                }
            });

            items.sort(function (a, b) {
                if (orden === 'alfabetico') {
                    return (a.getAttribute('data-nombre') || '').localeCompare(b.getAttribute('data-nombre') || '', 'es', { sensitivity: 'base' });
                }
                var minA = parseInt(a.getAttribute('data-minutos') || '0', 10);
                var minB = parseInt(b.getAttribute('data-minutos') || '0', 10);
                if (orden === 'carga-asc') {
                    return minA - minB;
                }
                if (orden === 'carga-desc') {
                    return minB - minA;
                }
                return 0;
            });

            items.forEach(function (el) {
                listaOpcionesModal.appendChild(el);
            });

            if (contadorModal) {
                contadorModal.textContent = 'Mostrando ' + visibles + (visibles === 1 ? ' enfermero/a' : ' enfermeros/as');
            }
            if (sinResultadosModal) {
                sinResultadosModal.classList.toggle('d-none', visibles > 0);
            }
        }

        function seleccionarOpcionModal(card) {
            if (!card) { return; }
            var id     = card.getAttribute('data-id');
            var nombre = card.getAttribute('data-nombre-mostrar') || '';
            var mat    = card.getAttribute('data-matricula-mostrar') || '';
            var esp    = card.getAttribute('data-especialidad') || '';

            listaOpcionesModal.querySelectorAll('.tp-enfermero-opcion').forEach(function (c) {
                c.classList.remove('is-selected');
                c.setAttribute('aria-checked', 'false');
            });
            card.classList.add('is-selected');
            card.setAttribute('aria-checked', 'true');

            if (inputUsuarioModal) {
                inputUsuarioModal.value = id;
            }
            var selectFallback = formAsignar.querySelector('select[name="usuario_id"]');
            if (selectFallback) {
                selectFallback.value = id;
            }

            if (textoSeleccionado) {
                textoSeleccionado.innerHTML = '<strong class="text-dark">' + nombre + '</strong>' +
                    (mat ? ' · <span class="tp-matricula">Mat: ' + mat + '</span>' : '') +
                    (esp ? ' · <span class="badge tp-badge-especialidad">' + esp + '</span>' : '');
            }

            formAsignar.querySelector('[data-errores-modal]').classList.add('d-none');
        }

        function limpiarSeleccionModal() {
            if (inputUsuarioModal) { inputUsuarioModal.value = ''; }
            var selectFallback = formAsignar.querySelector('select[name="usuario_id"]');
            if (selectFallback) { selectFallback.value = ''; }

            if (listaOpcionesModal) {
                listaOpcionesModal.querySelectorAll('.tp-enfermero-opcion').forEach(function (c) {
                    c.classList.remove('is-selected');
                    c.setAttribute('aria-checked', 'false');
                });
            }
            if (textoSeleccionado) {
                textoSeleccionado.innerHTML = '<span class="text-body-secondary fst-italic">Ningún enfermero/a seleccionado</span>';
            }
        }

        function restablecerFiltrosModal() {
            if (inputBuscarModal) { inputBuscarModal.value = ''; }
            if (selectEspModal) { selectEspModal.value = ''; }
            if (selectCargaModal) { selectCargaModal.value = 'todos'; }
            if (selectOrdenModal) { selectOrdenModal.value = 'carga-asc'; }
            filtrarYOrdenarModal();
            if (inputBuscarModal) { inputBuscarModal.focus(); }
        }

        if (inputBuscarModal) { inputBuscarModal.addEventListener('input', filtrarYOrdenarModal); }
        if (selectEspModal) { selectEspModal.addEventListener('change', filtrarYOrdenarModal); }
        if (selectCargaModal) { selectCargaModal.addEventListener('change', filtrarYOrdenarModal); }
        if (selectOrdenModal) { selectOrdenModal.addEventListener('change', filtrarYOrdenarModal); }
        if (btnLimpiarModal) { btnLimpiarModal.addEventListener('click', restablecerFiltrosModal); }
        if (btnRestablecerModal) { btnRestablecerModal.addEventListener('click', restablecerFiltrosModal); }

        if (listaOpcionesModal) {
            listaOpcionesModal.addEventListener('click', function (e) {
                var card = e.target.closest('.tp-enfermero-opcion');
                if (card) {
                    seleccionarOpcionModal(card);
                }
            });
            listaOpcionesModal.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    var card = e.target.closest('.tp-enfermero-opcion');
                    if (card) {
                        e.preventDefault();
                        seleccionarOpcionModal(card);
                    }
                }
            });
        }

        var enfermeroActualIdModal = null;

        function prepararModalAsignar(boton) {
            origenAsignar = boton;
            formAsignar.action = formAsignar.getAttribute('data-action-crear') || formAsignar.action;
            enfermeroActualIdModal = null;

            formAsignar.querySelector('[name="sector_id"]').value = boton.getAttribute('data-sector') || '';
            formAsignar.querySelector('[name="tipo_turno_id"]').value = boton.getAttribute('data-tipo') || '';
            formAsignar.querySelector('[name="fecha"]').value = boton.getAttribute('data-fecha') || '';

            var tituloEl   = modalAsignar.querySelector('#tituloModalAsignar');
            var subEl      = modalAsignar.querySelector('#subtituloModalAsignar');
            var bloqueReas = modalAsignar.querySelector('#bloqueReasignarActual');
            var textoBtn   = modalAsignar.querySelector('#textoBtnSubmitAsignar');
            var iconoBtn   = modalAsignar.querySelector('#iconoBtnSubmitAsignar');
            var iconoHead  = modalAsignar.querySelector('#iconoModalAsignar');

            if (tituloEl)  { tituloEl.textContent = 'Asignar turno de enfermería'; }
            if (subEl)     { subEl.textContent = 'Selección de personal según dotación, especialidad y carga horaria'; }
            if (bloqueReas){ bloqueReas.classList.add('d-none'); }
            if (textoBtn)  { textoBtn.textContent = 'Asignar guardia'; }
            if (iconoBtn)  { iconoBtn.className = 'bi bi-person-check me-1'; }
            if (iconoHead) { iconoHead.className = 'bi bi-person-plus-fill fs-5'; }

            var descTurno = boton.getAttribute('data-descripcion') || '';
            var descEl = formAsignar.querySelector('[data-descripcion-turno]');
            if (descEl) {
                descEl.textContent = descTurno ? 'Turno ' + descTurno + '.' : 'Turno seleccionado.';
            }

            formAsignar.querySelector('[data-errores-modal]').classList.add('d-none');
            limpiarSeleccionModal();
            restablecerFiltrosModal();
            instanciaAsignar.show(boton);
        }

        function prepararModalReasignar(boton) {
            origenAsignar = boton;
            var asigId = boton.getAttribute('data-id');
            var base = formAsignar.getAttribute('data-base-asignaciones') || (window.location.origin + '/admin/asignaciones');
            formAsignar.action = base + '/' + asigId + '/editar';

            enfermeroActualIdModal = boton.getAttribute('data-enfermero-actual-id') || null;
            var enfermeroActualNombre = boton.getAttribute('data-enfermero-actual-nombre') || 'Enfermero/a actual';

            formAsignar.querySelector('[name="sector_id"]').value = boton.getAttribute('data-sector') || '';
            formAsignar.querySelector('[name="tipo_turno_id"]').value = boton.getAttribute('data-tipo') || '';
            formAsignar.querySelector('[name="fecha"]').value = boton.getAttribute('data-fecha') || '';

            var tituloEl   = modalAsignar.querySelector('#tituloModalAsignar');
            var subEl      = modalAsignar.querySelector('#subtituloModalAsignar');
            var bloqueReas = modalAsignar.querySelector('#bloqueReasignarActual');
            var nombreReas = modalAsignar.querySelector('#nombreEnfermeroActualReasignar');
            var textoBtn   = modalAsignar.querySelector('#textoBtnSubmitAsignar');
            var iconoBtn   = modalAsignar.querySelector('#iconoBtnSubmitAsignar');
            var iconoHead  = modalAsignar.querySelector('#iconoModalAsignar');

            if (tituloEl)  { tituloEl.textContent = 'Reasignar turno de enfermería'; }
            if (subEl)     { subEl.textContent = 'Seleccione el nuevo enfermero/a que reemplazará al personal actual'; }
            if (bloqueReas){ bloqueReas.classList.remove('d-none'); }
            if (nombreReas){ nombreReas.textContent = enfermeroActualNombre; }
            if (textoBtn)  { textoBtn.textContent = 'Guardar reasignación'; }
            if (iconoBtn)  { iconoBtn.className = 'bi bi-arrow-left-right me-1'; }
            if (iconoHead) { iconoHead.className = 'bi bi-arrow-left-right fs-5'; }

            var descTurno = boton.getAttribute('data-descripcion') || '';
            var descEl = formAsignar.querySelector('[data-descripcion-turno]');
            if (descEl) {
                descEl.textContent = descTurno ? 'Turno ' + descTurno + '.' : 'Turno seleccionado.';
            }

            formAsignar.querySelector('[data-errores-modal]').classList.add('d-none');
            limpiarSeleccionModal();
            restablecerFiltrosModal();
            instanciaAsignar.show(boton);
        }

        document.addEventListener('click', function (evento) {
            var botonAsignar = evento.target.closest('[data-modal-asignar]');
            if (botonAsignar) {
                evento.preventDefault();
                prepararModalAsignar(botonAsignar);
                return;
            }

            var botonReasignar = evento.target.closest('[data-modal-reasignar]');
            if (botonReasignar) {
                evento.preventDefault();
                prepararModalReasignar(botonReasignar);
                return;
            }
        });

        modalAsignar.addEventListener('shown.bs.modal', function () {
            if (inputBuscarModal) {
                inputBuscarModal.focus();
            } else {
                var select = formAsignar.querySelector('select');
                if (select) { select.focus(); }
            }
        });

        modalAsignar.addEventListener('hidden.bs.modal', function () {
            var btn = formAsignar.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = false;
                btn.textContent = btn.getAttribute('data-texto-original') || 'Asignar guardia';
            }
            if (origenAsignar) { origenAsignar.focus(); }
        });

        formAsignar.addEventListener('submit', function (evento) {
            evento.preventDefault();
            var valorUsuario = inputUsuarioModal ? inputUsuarioModal.value : '';
            if (!valorUsuario) {
                var selFallback = formAsignar.querySelector('select[name="usuario_id"]');
                if (selFallback) { valorUsuario = selFallback.value; }
            }
            if (!valorUsuario) {
                mostrarErrores(formAsignar.querySelector('[data-errores-modal]'), { usuario_id: 'Seleccione un enfermero/a de la lista.' });
                return;
            }
            if (enfermeroActualIdModal && valorUsuario === enfermeroActualIdModal) {
                mostrarErrores(formAsignar.querySelector('[data-errores-modal]'), { usuario_id: 'Elija un enfermero/a diferente al actual para reasignar el turno.' });
                return;
            }
            enviarAjax(formAsignar, formAsignar.action, origenAsignar);
        });
    }

    if (modalQuitar && window.bootstrap) {
        var instanciaQuitar = bootstrap.Modal.getOrCreateInstance(modalQuitar);
        var formQuitar = modalQuitar.querySelector('form[data-ajax-quitar]');
        var origenQuitar = null;

        document.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-quitar]');
            if (!boton) { return; }
            evento.preventDefault();
            origenQuitar = boton;
            formQuitar.action = formQuitar.getAttribute('data-base') + '/' + boton.getAttribute('data-quitar') + '/eliminar';
            formQuitar.querySelector('[data-descripcion-turno]').textContent = boton.getAttribute('data-descripcion');
            formQuitar.querySelector('[data-errores-modal]').classList.add('d-none');
            instanciaQuitar.show(boton);
        });

        modalQuitar.addEventListener('hidden.bs.modal', function () {
            var btn = formQuitar.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = false;
                btn.textContent = btn.getAttribute('data-texto-original') || 'Quitar';
            }
            if (origenQuitar) { origenQuitar.focus(); }
        });

        formQuitar.addEventListener('submit', function (evento) {
            evento.preventDefault();
            enviarAjax(formQuitar, formQuitar.action, null);
        });
    }

    /* ---------- Menú lateral compacto (solo íconos) en computadora ---------- */
    var botonCompacto = document.querySelector('[data-menu-compacto]');
    if (botonCompacto) {
        var raiz = document.documentElement;
        var textoBoton = botonCompacto.querySelector('.menu-lateral__texto');
        var reflejarEstado = function () {
            var compacto = raiz.classList.contains('menu-compacto');
            botonCompacto.setAttribute('aria-pressed', compacto ? 'true' : 'false');
            botonCompacto.setAttribute('aria-label', compacto ? 'Agrandar menú' : 'Achicar menú');
            botonCompacto.title = compacto ? 'Agrandar menú' : 'Achicar menú';
            if (textoBoton) { textoBoton.textContent = compacto ? 'Agrandar menú' : 'Achicar menú'; }
        };
        reflejarEstado();
        botonCompacto.addEventListener('click', function () {
            raiz.classList.toggle('menu-compacto');
            try {
                localStorage.setItem('tp-menu-compacto', raiz.classList.contains('menu-compacto') ? '1' : '0');
            } catch (e) { /* sin almacenamiento: la preferencia dura hasta recargar */ }
            reflejarEstado();
        });
    }

    /* ---------- Copiar al portapapeles (credenciales temporales) ---------- */
    function seleccionarTexto(elemento) {
        var rango = document.createRange();
        rango.selectNodeContents(elemento);
        var seleccion = window.getSelection();
        seleccion.removeAllRanges();
        seleccion.addRange(rango);
    }

    document.querySelectorAll('[data-copiar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var origen = document.querySelector(boton.getAttribute('data-copiar'));
            var estado = document.querySelector(boton.getAttribute('data-estado'));
            if (!origen) { return; }

            function avisar(texto) { if (estado) { estado.textContent = texto; } }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(origen.textContent.trim()).then(function () {
                    avisar('Contraseña copiada.');
                }, function () {
                    seleccionarTexto(origen);
                    avisar('Contraseña seleccionada: presione Ctrl+C para copiarla.');
                });
            } else {
                seleccionarTexto(origen);
                avisar('Contraseña seleccionada: presione Ctrl+C para copiarla.');
            }
        });
    });
});