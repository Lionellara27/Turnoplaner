/* ==========================================================
   TurnoPlaner - interacciones de interfaz (JS sin dependencias)
   Ver .agents/skills/ux-ui-expert.md (secciones 3, 5 y 6)

   Se carga en el <head> sin defer: lo único que corre en el acto es la preferencia del
   menú compacto (evita el parpadeo). Todo lo demás espera a DOMContentLoaded, cuando
   Bootstrap (al final del body) ya está disponible.

   Reglas: nada de innerHTML con datos (se arman nodos con createElement/textContent),
   sin <script> inline en las vistas (los datos llegan en <script type="application/json">
   o en atributos data-*), y después de cada respuesta AJAX se renueva el token CSRF.
   ========================================================== */

// Preferencia "menú compacto" antes de pintar (antes era un <script> inline en el layout)
(function () {
    try {
        if (localStorage.getItem('tp-menu-compacto') === '1') {
            document.documentElement.classList.add('menu-compacto');
        }
    } catch (e) { /* sin almacenamiento: menú normal */ }
}());

// Si el usuario vuelve con "Atrás", el navegador puede mostrar la página congelada
// con botones deshabilitados y spinners: se recarga para dejarla en estado limpio.
window.addEventListener('pageshow', function (evento) {
    if (evento.persisted) { window.location.reload(); }
});

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /* ==========================================================
       Utilidades
       ========================================================== */

    function el(etiqueta, clases, texto) {
        var nodo = document.createElement(etiqueta);
        if (clases) { nodo.className = clases; }
        if (texto !== undefined && texto !== null) { nodo.textContent = String(texto); }
        return nodo;
    }

    function icono(nombre, extra) {
        var i = el('i', 'bi ' + nombre + (extra ? ' ' + extra : ''));
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function oculto(texto) {
        return el('span', 'visually-hidden', texto);
    }

    function agregar(padre) {
        for (var i = 1; i < arguments.length; i++) {
            var hijo = arguments[i];
            if (hijo === null || hijo === undefined || hijo === false) { continue; }
            padre.appendChild(typeof hijo === 'string' ? document.createTextNode(hijo) : hijo);
        }
        return padre;
    }

    function vaciar(nodo) {
        while (nodo.firstChild) { nodo.removeChild(nodo.firstChild); }
    }

    function leerJson(id) {
        var nodo = document.getElementById(id);
        if (!nodo) { return null; }
        try { return JSON.parse(nodo.textContent); } catch (e) { return null; }
    }

    function normalizar(texto) {
        return String(texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // El token CSRF se regenera en cada envío: se actualizan TODOS los campos de la página
    function actualizarCsrf(token) {
        if (!token) { return; }
        document.querySelectorAll('input[name="csrf_test_name"]').forEach(function (campo) {
            campo.value = token;
        });
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) { meta.setAttribute('content', token); }
    }

    // Visible y enfocable: un offcanvas o modal cerrado usa visibility:hidden (tiene tamaño pero no recibe foco)
    function esVisible(nodo) {
        return !!(nodo && document.contains(nodo) && nodo.getClientRects().length > 0
            && !nodo.closest('.offcanvas:not(.show), .modal:not(.show)')
            && window.getComputedStyle(nodo).visibility !== 'hidden');
    }

    // Estado de cobertura (DESIGN 4.8): mismo marcado que app/Views/admin/planificador/_cobertura.php
    function coberturaChip(cubiertos, minimo) {
        var n = parseInt(cubiertos, 10) || 0;
        var m = parseInt(minimo, 10) || 0;
        var clase, nombreIcono, visible, textoOculto;
        if (n >= m) {
            clase = 'cobertura--ok';
            nombreIcono = 'bi-check-circle-fill';
            visible = 'Cubierto ' + n + '/' + m;
            textoOculto = 'Cubierto: ' + n + ' de ' + m + ' enfermeros mínimos.';
        } else if (n > 0) {
            var faltan = m - n;
            clase = 'cobertura--parcial';
            nombreIcono = 'bi-exclamation-triangle-fill';
            visible = 'Faltan ' + faltan + ' · ' + n + '/' + m;
            textoOculto = (faltan === 1 ? 'Falta 1 enfermero' : 'Faltan ' + faltan + ' enfermeros') + ': ' + n + ' de ' + m + ' asignados.';
        } else {
            clase = 'cobertura--vacio';
            nombreIcono = 'bi-x-circle-fill';
            visible = 'Sin cubrir 0/' + m;
            textoOculto = 'Sin cubrir: 0 de ' + m + ' enfermeros mínimos.';
        }
        var chip = el('span', 'cobertura ' + clase);
        var textoVisible = el('span', null, visible);
        textoVisible.setAttribute('aria-hidden', 'true');
        return agregar(chip, icono(nombreIcono), textoVisible, oculto(textoOculto));
    }

    // Marca de hora extra: "HE" visible y "hora extra" para lectores de pantalla
    function marcaExtra(texto) {
        var marca = el('span', 'badge-extra ms-1');
        if (texto) {
            marca.textContent = texto;
        } else {
            var visible = el('span', null, 'HE');
            visible.setAttribute('aria-hidden', 'true');
            agregar(marca, visible, oculto('hora extra'));
        }
        return marca;
    }

    /* ==========================================================
       Validación visual de formularios
       ========================================================== */

    function contenedorDe(input) {
        return input.closest('.input-group') || input.closest('.form-floating') || input.parentElement;
    }

    function enlazarDescripcion(nodo, id) {
        var actual = (nodo.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
        if (actual.indexOf(id) === -1) { actual.push(id); }
        nodo.setAttribute('aria-describedby', actual.join(' '));
    }

    function marcarInvalido(input, mensaje) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        var floating = input.closest('.input-group .form-floating');
        if (floating) { floating.classList.add('is-invalid'); }

        var contenedor = contenedorDe(input);
        var feedback = contenedor.querySelector(':scope > .invalid-feedback');
        if (!feedback) {
            feedback = el('div', 'invalid-feedback');
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

    // Grupo de radios (selector de enfermeros, color del tipo de turno): se marca el <fieldset>, no cada radio
    function marcarGrupoInvalido(radio, mensaje) {
        var grupo = radio.closest('fieldset') || radio.parentElement;
        grupo.classList.add('is-invalid');
        var id = 'error-' + radio.name;
        var feedback = document.getElementById(id);
        if (!feedback) {
            feedback = el('div', 'invalid-feedback mt-0 mb-2');
            feedback.id = id;
            var leyenda = grupo.querySelector(':scope > legend');
            if (leyenda) { leyenda.after(feedback); } else { grupo.appendChild(feedback); }
        }
        feedback.textContent = mensaje;
        feedback.classList.add('d-block');
        feedback.classList.remove('d-none');
        enlazarDescripcion(grupo, id);
    }

    function limpiarGrupoInvalido(radio) {
        var grupo = radio.closest('fieldset') || radio.parentElement;
        grupo.classList.remove('is-invalid');
        var feedback = document.getElementById('error-' + radio.name);
        if (feedback) {
            feedback.classList.remove('d-block');
            feedback.classList.add('d-none');
        }
    }

    function mensajePara(input) {
        var grupo = input.type === 'radio' ? input.closest('fieldset') : null;
        return input.getAttribute('data-mensaje') || (grupo && grupo.getAttribute('data-mensaje'))
            || input.validationMessage || 'Revise este campo.';
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
        var spinner = el('span', 'spinner-border spinner-border-sm me-2');
        spinner.setAttribute('aria-hidden', 'true');
        vaciar(boton);
        agregar(boton, spinner, texto);
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
        // Delegación: el selector de enfermeros puede reemplazar sus radios por AJAX
        form.addEventListener('input', function (evento) {
            var input = evento.target;
            if (!input.matches('input, select, textarea') || input.type === 'radio') { return; }
            validarExtra(input);
            if (input.classList.contains('is-invalid') && input.checkValidity()) {
                limpiarInvalido(input);
            }
        });
        form.addEventListener('change', function (evento) {
            var input = evento.target;
            if (input.type === 'radio' && input.checkValidity()) {
                limpiarGrupoInvalido(input);
            } else if (input.matches('select') && input.classList.contains('is-invalid') && input.checkValidity()) {
                limpiarInvalido(input);
            }
        });

        form.addEventListener('submit', function (evento) {
            if (form.dataset.enviado === '1') { evento.preventDefault(); return; }

            var primero = null;
            var gruposVistos = {};
            form.querySelectorAll('input, select, textarea').forEach(function (input) {
                if (input.type === 'hidden' || input.disabled) { return; }
                if (input.type === 'radio') {
                    if (gruposVistos[input.name]) { return; }
                    gruposVistos[input.name] = true;
                    if (!input.checkValidity()) {
                        marcarGrupoInvalido(input, mensajePara(input));
                        if (!primero) {
                            var grupo = input.closest('fieldset');
                            var radios = form.querySelectorAll('input[type="radio"][name="' + input.name + '"]');
                            primero = Array.prototype.find.call(radios, function (r) { return !r.disabled && esVisible(r.nextElementSibling); })
                                || grupo || input;
                        }
                    } else {
                        limpiarGrupoInvalido(input);
                    }
                    return;
                }
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
            var ico = boton.querySelector('.bi');
            if (ico) {
                ico.classList.toggle('bi-eye', !mostrar);
                ico.classList.toggle('bi-eye-slash', mostrar);
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
            item.appendChild(el('span', 'visually-hidden estado-regla'));
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
        buscador.addEventListener('input', function () {
            var buscado = normalizar(buscador.value.trim());
            Array.prototype.forEach.call(select.options, function (opcion) {
                if (!opcion.value) { return; }
                opcion.hidden = buscado !== '' && normalizar(opcion.textContent).indexOf(buscado) === -1;
            });
        });
    });

    /* ---------- Turnos del sector elegido (formulario de asignación) ---------- */
    document.querySelectorAll('select[data-filtrar-por-sector]').forEach(function (selTipo) {
        var selSector = document.querySelector(selTipo.getAttribute('data-filtrar-por-sector'));
        if (!selSector) { return; }
        function filtrarTurnos() {
            var sectorId = selSector.value;
            var actualValido = false;
            Array.prototype.forEach.call(selTipo.options, function (opcion) {
                if (!opcion.value) { return; }
                var coincide = sectorId !== '' && opcion.getAttribute('data-sector') === sectorId;
                opcion.hidden = !coincide;
                opcion.disabled = !coincide;
                if (opcion.selected && coincide) { actualValido = true; }
            });
            if (!actualValido && selTipo.value !== '') {
                selTipo.value = '';
                selTipo.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        selSector.addEventListener('change', filtrarTurnos);
        filtrarTurnos();
    });

    /* ==========================================================
       Componente de lista filtrable (por data-attributes)
       Lo usan el selector de enfermeros (formulario y modal) y la carga horaria del Planificador.
         [data-filtro-lista]            contenedor
           [data-filtro-texto]          input: busca en data-buscar de cada ítem (sin tildes)
           [data-filtro-campo="x"]      select: compara con data-x del ítem ("" = todos)
           [data-filtro-grupo]          select: valor que debe estar en data-grupos del ítem
           [data-filtro-orden]          select "campo:asc|desc": ordena por data-orden-campo
           [data-filtro-items]          contenedor de los [data-filtro-item]
           [data-filtro-contador]       texto con role="status" (data-singular / data-plural)
           [data-filtro-vacio]          estado vacío
           [data-filtro-restablecer]    botones que vuelven los filtros a su valor inicial
       ========================================================== */
    var filtros = new WeakMap();

    function FiltroLista(raiz) {
        var api = { alAplicar: null };
        var texto = raiz.querySelector('[data-filtro-texto]');
        var campos = raiz.querySelectorAll('[data-filtro-campo]');
        var grupo = raiz.querySelector('[data-filtro-grupo]');
        var orden = raiz.querySelector('[data-filtro-orden]');
        var lista = raiz.querySelector('[data-filtro-items]');
        var contador = raiz.querySelector('[data-filtro-contador]');
        var vacio = raiz.querySelector('[data-filtro-vacio]');
        var restablecer = raiz.querySelectorAll('[data-filtro-restablecer]');
        var selects = raiz.querySelectorAll('select[data-filtro-campo], select[data-filtro-grupo], select[data-filtro-orden]');

        function items() {
            return lista ? Array.prototype.slice.call(lista.querySelectorAll(':scope > [data-filtro-item]')) : [];
        }

        function selectsEnValorInicial() {
            return Array.prototype.every.call(selects, function (sel) {
                var opcion = sel.options[sel.selectedIndex];
                return !opcion || opcion.defaultSelected;
            });
        }

        api.aplicar = function () {
            var buscado = texto ? normalizar(texto.value.trim()) : '';
            var valorGrupo = grupo && !grupo.disabled ? grupo.value : '';
            var todos = items();
            var visibles = 0;

            todos.forEach(function (item) {
                var mostrar = true;
                if (buscado && normalizar(item.getAttribute('data-buscar') || item.textContent).indexOf(buscado) === -1) {
                    mostrar = false;
                }
                campos.forEach(function (campo) {
                    var valor = campo.value;
                    if (mostrar && valor && (item.getAttribute('data-' + campo.getAttribute('data-filtro-campo')) || '') !== valor) {
                        mostrar = false;
                    }
                });
                if (mostrar && valorGrupo) {
                    var grupos = (item.getAttribute('data-grupos') || '').split(' ');
                    if (grupos.indexOf(valorGrupo) === -1) { mostrar = false; }
                }
                item.hidden = !mostrar;
                if (mostrar) { visibles++; }
            });

            if (orden && orden.value) {
                var partes = orden.value.split(':');
                var clave = 'data-orden-' + partes[0];
                var signo = partes[1] === 'desc' ? -1 : 1;
                todos.sort(function (a, b) {
                    var va = a.getAttribute(clave) || '';
                    var vb = b.getAttribute(clave) || '';
                    var na = parseFloat(va);
                    var nb = parseFloat(vb);
                    if (!isNaN(na) && !isNaN(nb)) { return (na - nb) * signo; }
                    return va.localeCompare(vb, 'es', { sensitivity: 'base' }) * signo;
                });
                todos.forEach(function (item) { lista.appendChild(item); });
            }

            var hayFiltro = buscado !== '' || !selectsEnValorInicial();
            restablecer.forEach(function (boton) {
                if (boton.hasAttribute('data-solo-con-filtro')) {
                    boton.classList.toggle('d-none', !hayFiltro || visibles === 0);
                }
            });
            if (contador) {
                var total = todos.length;
                var nombre = total === 1 ? (contador.getAttribute('data-singular') || '') : (contador.getAttribute('data-plural') || '');
                contador.textContent = total === 0 ? '' : (visibles === total ? total + ' ' + nombre : 'Mostrando ' + visibles + ' de ' + total + ' ' + nombre);
            }
            if (vacio) { vacio.classList.toggle('d-none', visibles > 0 || todos.length === 0); }
            if (typeof api.alAplicar === 'function') { api.alAplicar(); }
        };

        api.restablecer = function () {
            if (texto) { texto.value = ''; }
            selects.forEach(function (sel) {
                Array.prototype.forEach.call(sel.options, function (opcion) { opcion.selected = opcion.defaultSelected; });
            });
            api.aplicar();
        };

        if (texto) {
            texto.addEventListener('input', api.aplicar);
            // Enter en el buscador no envía el formulario
            texto.addEventListener('keydown', function (evento) {
                if (evento.key === 'Enter') { evento.preventDefault(); }
            });
        }
        selects.forEach(function (sel) {
            sel.addEventListener('change', api.aplicar);
        });
        restablecer.forEach(function (boton) {
            boton.addEventListener('click', function () {
                api.restablecer();
                if (texto) { texto.focus(); }
            });
        });

        filtros.set(raiz, api);
        return api;
    }

    /* ==========================================================
       Selector de enfermeros/as (radios reales, DESIGN 4.10)
       Marcado de servidor: app/Views/admin/planificador/_selector_enfermeros.php
       ========================================================== */

    function iniciales(nombre) {
        var partes = String(nombre || '').trim().split(/[\s,]+/).filter(Boolean);
        return ((partes[0] || '').charAt(0) + (partes[1] || '').charAt(0)).toUpperCase();
    }

    // Mismo marcado que app/Views/admin/planificador/_opcion_enfermero.php
    function opcionEnfermero(prefijo, e, opciones) {
        var id = parseInt(e.id, 10);
        var idInput = prefijo + '-enf-' + id;
        var estado = e.estado || '';
        var esActual = opciones.actualId !== null && opciones.actualId !== '' && String(id) === String(opciones.actualId);
        var bloqueado = esActual && opciones.bloquearActual;
        var elegido = opciones.seleccionadoId !== null && String(id) === String(opciones.seleccionadoId);
        var deshabilitado = (estado === 'no_disponible' && !(elegido && !opciones.bloquearActual)) || bloqueado;
        var matricula = String(e.matricula || '').trim();
        var especialidad = String(e.especialidad || '').trim();
        var motivo = String(e.motivo || '').trim();

        var item = el('div', 'tp-enfermero-item');
        item.setAttribute('data-filtro-item', '');
        item.setAttribute('data-buscar', String(e.nombre || '') + ' ' + matricula);
        item.setAttribute('data-especialidad', especialidad);
        item.setAttribute('data-grupos', (estado + (esActual ? ' actual' : '')).trim());

        var radio = el('input', 'btn-check');
        radio.type = 'radio';
        radio.name = 'usuario_id';
        radio.id = idInput;
        radio.value = String(id);
        radio.required = true;
        radio.disabled = deshabilitado;
        radio.checked = elegido && !deshabilitado;
        radio.setAttribute('aria-labelledby', idInput + '-nombre');
        radio.setAttribute('aria-describedby', idInput + '-datos ' + idInput + '-estado');

        var label = el('label', 'tp-enfermero-opcion');
        label.htmlFor = idInput;

        var avatar = el('span', 'tp-avatar-circulo', iniciales(e.nombre));
        avatar.setAttribute('aria-hidden', 'true');
        var nombre = el('span', 'tp-enfermero-opcion__nombre', e.nombre);
        nombre.id = idInput + '-nombre';
        var datos = el('span', 'tp-enfermero-opcion__datos');
        datos.id = idInput + '-datos';
        if (especialidad) { datos.appendChild(el('span', 'badge tp-badge-especialidad', especialidad)); }
        datos.appendChild(el('span', 'tp-matricula', matricula ? 'Mat. ' + matricula : 'Sin matrícula'));
        if (e.horas_texto) { datos.appendChild(el('span', null, e.horas_texto)); }

        var cuerpo = agregar(el('span', 'tp-enfermero-opcion__cuerpo'), nombre, datos);
        var principal = agregar(el('span', 'tp-enfermero-opcion__principal'), avatar, cuerpo);

        var bloqueEstado = el('span', 'tp-enfermero-opcion__estado');
        bloqueEstado.id = idInput + '-estado';
        if (esActual) {
            agregar(bloqueEstado, agregar(el('span', 'estado-disponibilidad estado-disponibilidad--actual'),
                icono('bi-person-check'), bloqueado ? 'Asignado actualmente: no se puede volver a elegir' : 'Asignado actualmente'));
        }
        if (estado === 'disponible') {
            agregar(bloqueEstado, agregar(el('span', 'estado-disponibilidad estado-disponibilidad--disponible'), icono('bi-check-circle'), 'Disponible'));
        } else if (estado === 'extra') {
            agregar(bloqueEstado, agregar(el('span', 'estado-disponibilidad estado-disponibilidad--extra'), icono('bi-clock-history'),
                'Hora extra' + (motivo ? ': ' + motivo : '')));
        } else if (estado === 'no_disponible') {
            agregar(bloqueEstado, agregar(el('span', 'estado-disponibilidad estado-disponibilidad--no-disponible'),
                icono('bi-slash-circle'), 'No disponible' + (motivo ? ': ' + motivo : '')));
        }
        var check = agregar(el('span', 'tp-check-circulo'), icono('bi-check-lg'));
        check.setAttribute('aria-hidden', 'true');
        bloqueEstado.appendChild(check);

        agregar(label, principal, bloqueEstado);
        return agregar(item, radio, label);
    }

    var selectores = new WeakMap();

    function SelectorEnfermeros(fs) {
        var filtro = filtros.get(fs) || FiltroLista(fs);
        var lista = fs.querySelector('[data-filtro-items]');
        var estado = fs.querySelector('[data-sel-estado]');
        var seleccionado = fs.querySelector('[data-sel-seleccionado]');
        var grupo = fs.querySelector('[data-filtro-grupo]');
        var prefijo = fs.getAttribute('data-prefijo') || 'sel';
        var pedido = 0;
        var api = {};

        function radioElegido() {
            return lista.querySelector('input[type="radio"]:checked');
        }

        function actualizarSeleccionado() {
            if (!seleccionado) { return; }
            vaciar(seleccionado);
            var radio = radioElegido();
            if (!radio) {
                seleccionado.textContent = 'Ningún enfermero/a seleccionado.';
                return;
            }
            var nombre = document.getElementById(radio.id + '-nombre');
            agregar(seleccionado, 'Seleccionado: ', el('strong', null, nombre ? nombre.textContent : ''));
            var item = radio.closest('[data-filtro-item]');
            if (item && item.hidden) {
                agregar(seleccionado, ' (oculto por el filtro)');
            }
        }

        function mostrarEstado(tipo, mensaje, extras) {
            vaciar(estado);
            lista.removeAttribute('aria-busy');
            if (!tipo) { return; }
            if (tipo === 'cargando') {
                lista.setAttribute('aria-busy', 'true');
                var spinner = el('span', 'spinner-border spinner-border-sm me-2');
                spinner.setAttribute('aria-hidden', 'true');
                var aviso = agregar(el('p', 'small text-body-secondary mb-2'), spinner, mensaje);
                aviso.setAttribute('role', 'status');
                estado.appendChild(aviso);
                return;
            }
            var caja = agregar(el('div', 'alerta alerta--error small mb-2'), icono('bi-exclamation-triangle-fill', 'me-1'), mensaje);
            caja.setAttribute('role', 'alert');
            (extras || []).forEach(function (nodo) { agregar(caja, ' ', nodo); });
            estado.appendChild(caja);
        }

        api.pintar = function (enfermeros, opciones) {
            opciones = opciones || {};
            opciones.actualId = opciones.actualId !== undefined ? opciones.actualId : null;
            opciones.seleccionadoId = opciones.seleccionadoId !== undefined ? opciones.seleccionadoId : null;
            var fragmento = document.createDocumentFragment();
            enfermeros.forEach(function (e) {
                fragmento.appendChild(opcionEnfermero(prefijo, e, opciones));
            });
            vaciar(lista);
            lista.appendChild(fragmento);
            var conEstados = enfermeros.some(function (e) { return !!e.estado; });
            if (grupo) { grupo.disabled = !conEstados; }
            filtro.aplicar();
        };

        // GET admin/asignaciones/disponibilidad?sector_id&tipo_turno_id&fecha[&excluir]
        api.cargar = function (parametros, opciones) {
            var url = fs.getAttribute('data-url-disponibilidad');
            if (!url) { return; }
            opciones = opciones || {};
            var miPedido = ++pedido;
            var consulta = new URLSearchParams();
            Object.keys(parametros).forEach(function (clave) {
                if (parametros[clave] !== null && parametros[clave] !== undefined && parametros[clave] !== '') {
                    consulta.append(clave, parametros[clave]);
                }
            });
            mostrarEstado('cargando', 'Cargando enfermeros/as disponibles…');

            fetch(url + '?' + consulta.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (respuesta) {
                return respuesta.json().catch(function () { return null; }).then(function (datos) {
                    if (datos && datos.csrf) { actualizarCsrf(datos.csrf); }
                    if (!respuesta.ok || !datos || !datos.ok || !Array.isArray(datos.enfermeros)) {
                        var errores = datos && datos.errores ? Object.keys(datos.errores).map(function (k) { return datos.errores[k]; }) : [];
                        throw new Error(errores.join(' '));
                    }
                    return datos;
                });
            }).then(function (datos) {
                if (miPedido !== pedido) { return; }
                mostrarEstado(null);
                var elegido = radioElegido();
                api.pintar(datos.enfermeros, {
                    actualId: opciones.actualId,
                    bloquearActual: !!opciones.bloquearActual,
                    seleccionadoId: elegido ? elegido.value : (opciones.seleccionadoId || null)
                });
                if (typeof opciones.alCargar === 'function') { opciones.alCargar(datos); }
            }).catch(function (error) {
                if (miPedido !== pedido) { return; }
                var detalle = error && error.message && !/fetch|network|JSON/i.test(error.message) ? ' ' + error.message : '';
                var extras = [];
                var reintentar = el('button', 'btn btn-outline-secondary btn-sm ms-1', 'Reintentar');
                reintentar.type = 'button';
                reintentar.addEventListener('click', function () { api.cargar(parametros, opciones); });
                extras.push(reintentar);
                if (opciones.urlFormulario) {
                    var enlace = el('a', 'ms-1', 'Abrir el formulario completo');
                    enlace.href = opciones.urlFormulario;
                    extras.push(enlace);
                }
                mostrarEstado('error', 'No se pudo cargar la disponibilidad de los enfermeros/as.' + detalle, extras);
            });
        };

        api.limpiar = function () {
            pedido++;
            mostrarEstado(null);
            vaciar(lista);
            filtro.restablecer();
            actualizarSeleccionado();
        };

        api.elegido = radioElegido;

        filtro.alAplicar = actualizarSeleccionado;
        lista.addEventListener('change', function (evento) {
            if (evento.target.type === 'radio') {
                actualizarSeleccionado();
                fs.classList.remove('is-invalid');
            }
        });

        filtro.aplicar();
        selectores.set(fs, api);
        return api;
    }

    // Listas filtrables y selectores presentes al cargar la página
    document.querySelectorAll('[data-filtro-lista]').forEach(function (raiz) {
        if (raiz.hasAttribute('data-selector-enfermeros')) {
            SelectorEnfermeros(raiz);
        } else {
            FiltroLista(raiz).aplicar();
        }
    });

    /* ---------- Formulario de asignación: recalcula la disponibilidad al cambiar turno o fecha ---------- */
    document.querySelectorAll('form[data-form-asignacion]').forEach(function (form) {
        var fs = form.querySelector('[data-selector-enfermeros]');
        var selector = fs ? selectores.get(fs) : null;
        var selSector = form.querySelector('[name="sector_id"]');
        var selTipo = form.querySelector('[name="tipo_turno_id"]');
        var inputFecha = form.querySelector('[name="fecha"]');
        var nota = form.querySelector('[data-sel-calculo]');
        if (!selector || !selSector || !selTipo || !inputFecha) { return; }

        function recalcular() {
            var fechaValida = /^\d{4}-\d{2}-\d{2}$/.test(inputFecha.value);
            if (!selSector.value || !selTipo.value || !fechaValida) {
                if (nota) { nota.textContent = 'Seleccione sector, fecha y turno para ver la disponibilidad de cada enfermero/a.'; }
                return;
            }
            var opcionTipo = selTipo.options[selTipo.selectedIndex];
            var partesFecha = inputFecha.value.split('-');
            var textoCalculo = 'Disponibilidad calculada para ' + (opcionTipo ? opcionTipo.textContent.split('·')[0].trim() : 'el turno')
                + ' del ' + partesFecha[2] + '/' + partesFecha[1] + '/' + partesFecha[0] + '.';
            selector.cargar({
                sector_id: selSector.value,
                tipo_turno_id: selTipo.value,
                fecha: inputFecha.value,
                excluir: fs.getAttribute('data-excluir') || ''
            }, {
                actualId: fs.getAttribute('data-actual'),
                bloquearActual: false,
                alCargar: function () { if (nota) { nota.textContent = textoCalculo; } }
            });
        }

        selTipo.addEventListener('change', recalcular);
        inputFecha.addEventListener('change', recalcular);
    });

    /* ==========================================================
       Planificador: AJAX de asignar, reasignar y quitar
       ========================================================== */
    var modalAsignar = document.getElementById('modalAsignar');
    var modalQuitar = document.getElementById('modalQuitar');
    var drawerEl = document.getElementById('drawerDetalleDia');
    var ultimoDisparadorDia = null;   // botón "Ver día" que abrió el panel lateral

    // Devuelve el foco a un elemento visible: el que abrió el modal o, si quedó en el panel ya cerrado, el día
    function devolverFoco(origen) {
        if (esVisible(origen)) {
            origen.focus();
        } else if (esVisible(ultimoDisparadorDia)) {
            ultimoDisparadorDia.focus();
        }
    }

    function mostrarErrores(contenedor, errores) {
        var lista = Object.keys(errores).map(function (campo) { return errores[campo]; });
        vaciar(contenedor);
        var ul = el('ul', 'mb-0 ps-3 small');
        lista.forEach(function (texto) { ul.appendChild(el('li', null, texto)); });
        contenedor.appendChild(ul);
        contenedor.classList.remove('d-none');
        contenedor.setAttribute('tabindex', '-1');
        contenedor.focus();
    }

    var contenidoOriginal = new WeakMap();

    function restaurarBoton(boton) {
        if (!boton) { return; }
        boton.disabled = false;
        if (contenidoOriginal.has(boton)) {
            vaciar(boton);
            contenidoOriginal.get(boton).forEach(function (nodo) { boton.appendChild(nodo); });
            contenidoOriginal.delete(boton);
        }
    }

    function enviarAjax(form, url, enlaceAlternativo) {
        var boton = form.querySelector('button[type="submit"]');
        var errores = form.querySelector('[data-errores-modal]');

        if (boton) {
            // Se guardan los nodos originales (ícono y spans con id) para restaurarlos tal cual después de un error
            contenidoOriginal.set(boton, Array.prototype.slice.call(boton.childNodes));
            boton.disabled = true;
            var spinner = el('span', 'spinner-border spinner-border-sm me-2');
            spinner.setAttribute('aria-hidden', 'true');
            vaciar(boton);
            agregar(boton, spinner, 'Guardando...');
        }
        if (errores) { errores.classList.add('d-none'); }

        fetch(url, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
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
                var datos = null;
                try { datos = JSON.parse(texto); } catch (e) { datos = null; }
                if (datos && datos.csrf) { actualizarCsrf(datos.csrf); }
                if (datos === null) {
                    if (respuesta.ok) { window.location.reload(); return null; }
                    throw new Error('respuesta no JSON');
                }
                return datos;
            });
        }).then(function (datos) {
            if (!datos) { return; }
            if (datos.ok) {
                window.location.reload();
                return;
            }
            restaurarBoton(boton);
            if (errores) {
                mostrarErrores(errores, datos.errores || { general: 'No se pudo completar la operación. Intente nuevamente.' });
            }
        }).catch(function () {
            restaurarBoton(boton);
            if (errores) {
                mostrarErrores(errores, { general: 'Error de conexión o sesión vencida. Recargue la página e intente nuevamente.' });
                if (enlaceAlternativo) {
                    var enlace = el('a', 'd-inline-block mt-1', 'Abrir el formulario completo');
                    enlace.href = enlaceAlternativo;
                    errores.appendChild(enlace);
                }
            }
        });
    }

    if (modalAsignar && window.bootstrap) {
        var instanciaAsignar = bootstrap.Modal.getOrCreateInstance(modalAsignar);
        var formAsignar = modalAsignar.querySelector('form[data-ajax-asignar]');
        var fsModal = formAsignar.querySelector('[data-selector-enfermeros]');
        var selectorModal = fsModal ? (selectores.get(fsModal) || SelectorEnfermeros(fsModal)) : null;
        var erroresAsignar = formAsignar.querySelector('[data-errores-modal]');
        var origenAsignar = null;
        var enfermeroActualIdModal = null;

        var prepararModal = function (boton, reasignar) {
            origenAsignar = boton;
            var tituloEl = modalAsignar.querySelector('#tituloModalAsignar');
            var subEl = modalAsignar.querySelector('#subtituloModalAsignar');
            var bloqueReas = modalAsignar.querySelector('#bloqueReasignarActual');
            var nombreReas = modalAsignar.querySelector('#nombreEnfermeroActualReasignar');
            var asignacionId = boton.getAttribute('data-id') || '';

            restaurarBoton(formAsignar.querySelector('button[type="submit"]'));
            var textoBtn = modalAsignar.querySelector('#textoBtnSubmitAsignar');
            var iconoBtn = modalAsignar.querySelector('#iconoBtnSubmitAsignar');
            var iconoHead = modalAsignar.querySelector('#iconoModalAsignar');

            enfermeroActualIdModal = reasignar ? (boton.getAttribute('data-enfermero-actual-id') || null) : null;
            formAsignar.action = reasignar
                ? (formAsignar.getAttribute('data-base-asignaciones') + '/' + asignacionId + '/editar')
                : formAsignar.getAttribute('data-action-crear');

            formAsignar.querySelector('[name="sector_id"]').value = boton.getAttribute('data-sector') || '';
            formAsignar.querySelector('[name="tipo_turno_id"]').value = boton.getAttribute('data-tipo') || '';
            formAsignar.querySelector('[name="fecha"]').value = boton.getAttribute('data-fecha') || '';

            if (tituloEl) { tituloEl.textContent = reasignar ? 'Reasignar turno' : 'Asignar turno'; }
            if (subEl) {
                subEl.textContent = reasignar
                    ? 'Seleccione el enfermero/a que reemplazará al asignado actualmente.'
                    : 'Seleccione el enfermero/a según disponibilidad y carga horaria.';
            }
            if (bloqueReas) { bloqueReas.classList.toggle('d-none', !reasignar); }
            if (nombreReas) { nombreReas.textContent = boton.getAttribute('data-enfermero-actual-nombre') || '—'; }
            if (textoBtn) { textoBtn.textContent = reasignar ? 'Guardar reasignación' : 'Asignar turno'; }
            if (iconoBtn) { iconoBtn.className = reasignar ? 'bi bi-arrow-left-right me-1' : 'bi bi-person-check me-1'; }
            if (iconoHead) { iconoHead.className = reasignar ? 'bi bi-arrow-left-right' : 'bi bi-person-plus-fill'; }

            var descEl = formAsignar.querySelector('[data-descripcion-turno]');
            if (descEl) { descEl.textContent = boton.getAttribute('data-descripcion') || 'Turno seleccionado'; }

            erroresAsignar.classList.add('d-none');
            if (selectorModal) {
                selectorModal.limpiar();
                selectorModal.cargar({
                    sector_id: boton.getAttribute('data-sector') || '',
                    tipo_turno_id: boton.getAttribute('data-tipo') || '',
                    fecha: boton.getAttribute('data-fecha') || '',
                    excluir: reasignar ? asignacionId : ''
                }, {
                    actualId: enfermeroActualIdModal,
                    bloquearActual: reasignar,
                    urlFormulario: boton.getAttribute('href') || ''
                });
            }
            instanciaAsignar.show(boton);
        };

        document.addEventListener('click', function (evento) {
            var botonAsignar = evento.target.closest('[data-modal-asignar]');
            if (botonAsignar) {
                evento.preventDefault();
                prepararModal(botonAsignar, false);
                return;
            }
            var botonReasignar = evento.target.closest('[data-modal-reasignar]');
            if (botonReasignar) {
                evento.preventDefault();
                prepararModal(botonReasignar, true);
            }
        });

        modalAsignar.addEventListener('shown.bs.modal', function () {
            var buscador = modalAsignar.querySelector('[data-filtro-texto]');
            if (buscador) { buscador.focus(); }
        });

        modalAsignar.addEventListener('hidden.bs.modal', function () {
            restaurarBoton(formAsignar.querySelector('button[type="submit"]'));
            devolverFoco(origenAsignar);
        });

        formAsignar.addEventListener('submit', function (evento) {
            evento.preventDefault();
            var elegido = selectorModal ? selectorModal.elegido() : null;
            if (!elegido) {
                mostrarErrores(erroresAsignar, { usuario_id: 'Seleccione un enfermero/a de la lista.' });
                return;
            }
            if (enfermeroActualIdModal && elegido.value === String(enfermeroActualIdModal)) {
                mostrarErrores(erroresAsignar, { usuario_id: 'Seleccione un enfermero/a distinto del asignado actualmente.' });
                return;
            }
            enviarAjax(formAsignar, formAsignar.action, origenAsignar ? origenAsignar.getAttribute('href') : '');
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
            restaurarBoton(formQuitar.querySelector('button[type="submit"]'));
            formQuitar.action = formQuitar.getAttribute('data-base') + '/' + boton.getAttribute('data-quitar') + '/eliminar';
            formQuitar.querySelector('[data-descripcion-turno]').textContent = boton.getAttribute('data-descripcion') || '';
            formQuitar.querySelector('[data-errores-modal]').classList.add('d-none');
            instanciaQuitar.show(boton);
        });

        modalQuitar.addEventListener('hidden.bs.modal', function () {
            restaurarBoton(formQuitar.querySelector('button[type="submit"]'));
            devolverFoco(origenQuitar);
        });

        formQuitar.addEventListener('submit', function (evento) {
            evento.preventDefault();
            enviarAjax(formQuitar, formQuitar.action, null);
        });
    }

    /* ---------- Bloque de un tipo de turno (panel lateral del Planificador y detalle del día del Panel) ---------- */
    function bloqueTurno(turno, dia, conAcciones, sufijoId) {
        var seccion = el('section', 'tp-turno-bloque');
        seccion.style.setProperty('--color-turno', turno.color || '');
        var idTitulo = 'turno-' + sufijoId;
        seccion.setAttribute('aria-labelledby', idTitulo);

        var titulo = el('h3', 'h6 fw-bold mb-0', turno.nombre);
        titulo.id = idTitulo;
        seccion.appendChild(agregar(el('div', 'tp-turno-bloque__cabecera'),
            agregar(el('div', 'min-w-0'), titulo, el('div', 'small text-body-secondary', turno.horario)),
            coberturaChip(turno.cubiertos, turno.minimo)));

        var enfermeros = turno.enfermeros || [];
        if (enfermeros.length === 0) {
            seccion.appendChild(el('p', 'tp-turno-bloque__vacio mb-0', 'Sin enfermeros/as asignados.'));
        } else {
            var lista = el('ul', 'list-unstyled d-flex flex-column gap-2 mb-0');
            enfermeros.forEach(function (enf) {
                var fila = el('li', 'tp-enfermero-fila');
                var nombre = agregar(el('div', 'tp-enfermero-fila__nombre'), enf.nombre);
                if (enf.es_extra) { nombre.appendChild(marcaExtra()); }
                if (enf.de_baja) {
                    nombre.appendChild(agregar(el('span', 'badge bg-secondary-subtle text-secondary-emphasis border ms-1'),
                        icono('bi-slash-circle', 'me-1'), 'De baja · no cuenta'));
                }
                fila.appendChild(agregar(el('div', 'min-w-0'), nombre, el('div', 'tp-matricula', 'Mat. ' + (enf.matricula || '—'))));

                if (conAcciones) {
                    var queTurno = ' del turno ' + turno.nombre + ' del día ' + dia.titulo;
                    if (enf.es_futura) {
                        var acciones = el('div', 'd-flex gap-2 flex-shrink-0');
                        var reasignar = el('a', 'btn btn-outline-secondary btn-icono');
                        reasignar.href = enf.url_editar;
                        reasignar.setAttribute('data-modal-reasignar', '');
                        reasignar.setAttribute('data-id', enf.id);
                        reasignar.setAttribute('data-sector', dia.sectorId);
                        reasignar.setAttribute('data-tipo', turno.tipo_id);
                        reasignar.setAttribute('data-fecha', dia.fecha);
                        reasignar.setAttribute('data-enfermero-actual-id', enf.usuario_id || '');
                        reasignar.setAttribute('data-enfermero-actual-nombre', enf.nombre);
                        reasignar.setAttribute('data-descripcion', turno.desc_asignar || (turno.nombre + ' del ' + dia.titulo));
                        agregar(reasignar, icono('bi-arrow-left-right'), oculto('Reasignar a ' + enf.nombre + queTurno));

                        var quitar = el('button', 'btn btn-outline-danger btn-icono');
                        quitar.type = 'button';
                        quitar.setAttribute('data-quitar', enf.id);
                        quitar.setAttribute('data-descripcion', enf.desc_quitar || enf.nombre);
                        agregar(quitar, icono('bi-x-lg'), oculto('Quitar a ' + enf.nombre + queTurno));

                        fila.appendChild(agregar(acciones, reasignar, quitar));
                    } else {
                        fila.appendChild(agregar(el('span', 'small text-body-secondary text-nowrap'), icono('bi-lock', 'me-1'), 'Terminado'));
                    }
                }
                lista.appendChild(fila);
            });
            seccion.appendChild(lista);
        }

        if (conAcciones && turno.puede_asignar) {
            var asignar = el('a', 'btn btn-outline-secondary w-100 mt-2');
            asignar.href = turno.url_asignar;
            asignar.setAttribute('data-modal-asignar', '');
            asignar.setAttribute('data-sector', dia.sectorId);
            asignar.setAttribute('data-tipo', turno.tipo_id);
            asignar.setAttribute('data-fecha', dia.fecha);
            asignar.setAttribute('data-descripcion', turno.desc_asignar || '');
            agregar(asignar, icono('bi-person-plus', 'me-1'), 'Asignar enfermero/a', oculto(' al turno ' + turno.nombre + ' del día ' + dia.titulo));
            seccion.appendChild(asignar);
        }
        return seccion;
    }

    function pintarResumenDia(texto, estadoEl, dia) {
        texto.textContent = 'Dotación del día: ' + dia.totalAsignados + ' de ' + dia.totalRequerido + ' puestos mínimos';
        vaciar(estadoEl);
        estadoEl.appendChild(coberturaChip(dia.totalAsignados, dia.totalRequerido));
    }

    /* ---------- Panel lateral del día (Planificador) ---------- */
    var datosPlanificador = leerJson('datos-planificador');
    if (drawerEl && datosPlanificador && window.bootstrap) {
        var drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        var listaTurnosDia = document.getElementById('drawerListaTurnos');

        var abrirDia = function (fecha, disparador) {
            var dia = datosPlanificador[fecha];
            if (!dia) { return; }
            ultimoDisparadorDia = disparador || null;
            document.getElementById('drawerNum').textContent = dia.numDia;
            document.getElementById('drawerTituloDia').textContent = dia.titulo;
            document.getElementById('drawerTagHoy').classList.toggle('d-none', !dia.esHoy);
            pintarResumenDia(document.getElementById('drawerResumenTexto'), document.getElementById('drawerResumenEstado'), dia);

            vaciar(listaTurnosDia);
            dia.turnos.forEach(function (turno, indice) {
                listaTurnosDia.appendChild(bloqueTurno(turno, dia, true, 'drawer-' + indice));
            });
            drawer.show(disparador);
        };

        document.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-abrir-drawer]');
            if (boton) { abrirDia(boton.getAttribute('data-abrir-drawer'), boton); }
        });

        // Al abrir un modal desde el panel, el panel se cierra para no superponer fondos
        drawerEl.addEventListener('click', function (evento) {
            if (evento.target.closest('[data-modal-asignar], [data-modal-reasignar], [data-quitar]')) {
                drawer.hide();
            }
        });

        // Al cerrar el panel (sin abrir un modal), el foco vuelve al día que lo abrió
        drawerEl.addEventListener('hidden.bs.offcanvas', function () {
            if (document.querySelector('.modal.show')) { return; }
            if (!document.activeElement || document.activeElement === document.body || drawerEl.contains(document.activeElement)) {
                devolverFoco(ultimoDisparadorDia);
            }
        });
    }

    /* ---------- Ficha y guardias del mes de un enfermero/a (Planificador) ---------- */
    var datosEnfermeros = leerJson('datos-enfermeros-mes');
    var modalEnfEl = document.getElementById('modalDetalleEnfermero');
    if (datosEnfermeros && modalEnfEl && window.bootstrap) {
        var modalEnf = bootstrap.Modal.getOrCreateInstance(modalEnfEl);
        var origenEnf = null;
        var poner = function (id, texto) {
            var nodo = document.getElementById(id);
            if (nodo) { nodo.textContent = texto === null || texto === undefined ? '—' : texto; }
        };

        document.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-abrir-enfermero]');
            if (!boton) { return; }
            var info = datosEnfermeros[boton.getAttribute('data-abrir-enfermero')];
            if (!info) { return; }
            origenEnf = boton;

            poner('modalEnfTitulo', info.nombre);
            poner('modalEnfMatricula', info.matricula);
            poner('modalEnfEspecialidad', info.especialidad);
            poner('modalEnfTope', info.topeSemanal);
            poner('modalEnfDni', info.dni);
            poner('modalEnfEmail', info.email);
            poner('modalEnfTelefono', info.telefono);
            poner('modalEnfTotalMes', info.totalMes);
            poner('modalEnfNormalesMes', info.normalesMes);
            poner('modalEnfExtrasMes', info.extrasMes);
            var colSemana = document.getElementById('modalEnfSemanaCol');
            if (colSemana) { colSemana.classList.toggle('d-none', !info.semana); }
            poner('modalEnfSemana', info.semana);
            document.getElementById('modalEnfBtnEditar').href = info.urlPerfil;

            var lista = document.getElementById('modalEnfListaTurnos');
            vaciar(lista);
            if (!info.turnos || info.turnos.length === 0) {
                lista.appendChild(el('li', 'tp-turno-bloque__vacio text-center', 'No tiene guardias asignadas en este mes.'));
            } else {
                info.turnos.forEach(function (t) {
                    var item = el('li', 'tp-turno-bloque d-flex flex-wrap justify-content-between align-items-center gap-2');
                    item.style.setProperty('--color-turno', t.color || '');
                    var datos = agregar(el('div', 'min-w-0'),
                        el('div', 'fw-semibold small', t.dia_sem + ' ' + t.fecha + ' · ' + t.sector),
                        el('div', 'small text-body-secondary', t.turno + ' (' + t.horario + ')'));
                    var horas = agregar(el('div', 'text-end small'), el('span', 'fw-semibold', t.duracion));
                    if (t.extra) { horas.appendChild(marcaExtra('Extra: ' + t.extra)); }
                    lista.appendChild(agregar(item, datos, horas));
                });
            }
            modalEnf.show(boton);
        });

        modalEnfEl.addEventListener('hidden.bs.modal', function () { devolverFoco(origenEnf); });
    }

    /* ---------- Detalle del día en el Panel (solo lectura) ---------- */
    var datosPanel = leerJson('datos-panel-dias');
    var modalDiaEl = document.getElementById('modalDetalleDia');
    if (datosPanel && modalDiaEl && window.bootstrap) {
        var modalDia = bootstrap.Modal.getOrCreateInstance(modalDiaEl);
        var origenDia = null;

        document.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-abrir-dia]');
            if (!boton) { return; }
            var dia = datosPanel[boton.getAttribute('data-abrir-dia')];
            if (!dia) { return; }
            origenDia = boton;

            document.getElementById('modalDetalleTitulo').textContent = dia.titulo;
            document.getElementById('modalBadgeHoy').classList.toggle('d-none', !dia.esHoy);
            document.getElementById('modalBtnPlanificar').href = dia.urlPlanificar;
            pintarResumenDia(document.getElementById('modalResumenTexto'), document.getElementById('modalResumenEstado'), dia);

            var lista = document.getElementById('modalTurnosLista');
            vaciar(lista);
            dia.turnos.forEach(function (turno, indice) {
                lista.appendChild(bloqueTurno(turno, dia, false, 'panel-' + indice));
            });
            modalDia.show(boton);
        });

        modalDiaEl.addEventListener('hidden.bs.modal', function () { devolverFoco(origenDia); });
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
