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

    // Contenedor del mensaje: en un input-group es el grupo; si no, el form-floating o el padre.
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

    // Reglas que el HTML no cubre: confirmación de contraseña.
    function validarExtra(input) {
        var selectorIgual = input.getAttribute('data-igual-a');
        if (selectorIgual) {
            var original = document.querySelector(selectorIgual);
            input.setCustomValidity(original && input.value !== original.value ? 'no-coincide' : '');
        }
    }

    // Errores que vienen del servidor: se enlaza el mensaje al campo para lectores de pantalla.
    document.querySelectorAll('.form-control.is-invalid, .form-select.is-invalid').forEach(function (input) {
        input.setAttribute('aria-invalid', 'true');
        var feedback = document.getElementById('error-' + input.id);
        if (feedback) { enlazarDescripcion(input, feedback.id); }
    });

    // Si hay resumen de errores, se lleva el foco ahí para que se lea primero.
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

    // Los formularios de los modales de la cuadrícula se envían por AJAX y manejan su propio estado.
    document.querySelectorAll('form[method="post"]:not([data-ajax-asignar]):not([data-ajax-quitar])').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (form.classList.contains('needs-validation')) { return; } // lo maneja la validación de abajo
            if (form.dataset.enviado === '1') { evento.preventDefault(); return; }
            form.dataset.enviado = '1';
            marcarEnviando(form);
        });
    });

    /* ---------- Formularios con validación del lado del cliente ---------- */
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        var campos = form.querySelectorAll('input, select, textarea');

        campos.forEach(function (input) {
            // Al corregir un campo, el error se quita en vivo.
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
        // Estado accesible: el ícono es decorativo, el lector de pantalla lee "cumplido" o "pendiente".
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
            // Igual que el servidor: si el fin es menor o igual al inicio, termina al día siguiente.
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

    /* ---------- Buscador que filtra las opciones de un select (enfermeros/as al asignar turno) ---------- */
    document.querySelectorAll('[data-filtrar-select]').forEach(function (buscador) {
        var select = document.querySelector(buscador.getAttribute('data-filtrar-select'));
        if (!select) { return; }
        var normalizar = function (texto) {
            return texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        };
        buscador.addEventListener('input', function () {
            var buscado = normalizar(buscador.value.trim());
            Array.prototype.forEach.call(select.options, function (opcion) {
                if (!opcion.value) { return; } // la opción "Elija un enfermero/a" siempre queda
                opcion.hidden = buscado !== '' && normalizar(opcion.textContent).indexOf(buscado) === -1;
            });
        });
    });

    /* ---------- Cuadrícula semanal: agregar y quitar personal con modal + AJAX (HU-14) ---------- */
    // Sin JavaScript, los botones siguen siendo links al formulario común (mejora progresiva).
    var modalAsignar = document.getElementById('modalAsignar');
    var modalQuitar = document.getElementById('modalQuitar');

    var mostrarErrores = function (contenedor, errores) {
        var lista = Object.keys(errores).map(function (campo) { return errores[campo]; });
        contenedor.innerHTML = '';
        var ul = document.createElement('ul');
        ul.className = 'mb-0 ps-3 small';
        lista.forEach(function (texto) {
            var li = document.createElement('li');
            li.textContent = texto; // textContent: nunca se interpreta HTML que venga del servidor
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
        var textoBoton = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Guardando...';
        errores.classList.add('d-none');

        fetch(url, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (respuesta) {
            return respuesta.json().then(function (datos) { return { estado: respuesta.status, datos: datos }; });
        }).then(function (r) {
            if (r.datos.ok) {
                window.location.reload(); // la cuadrícula se vuelve a dibujar con el mensaje de éxito
                return;
            }
            // El token CSRF se regenera en cada envío: se usa el nuevo para el próximo intento.
            if (r.datos.csrf) {
                document.querySelectorAll('input[name="csrf_test_name"]').forEach(function (campo) { campo.value = r.datos.csrf; });
            }
            mostrarErrores(errores, r.datos.errores || { general: 'No se pudo guardar. Intente nuevamente.' });
            boton.disabled = false;
            boton.innerHTML = textoBoton;
        }).catch(function () {
            // Si falla la red o la sesión venció, se sigue por el camino común (formulario completo o recarga).
            if (botonOriginal && botonOriginal.href) { window.location.href = botonOriginal.href; } else { window.location.reload(); }
        });
    };

    if (modalAsignar && window.bootstrap) {
        var instanciaAsignar = bootstrap.Modal.getOrCreateInstance(modalAsignar);
        var formAsignar = modalAsignar.querySelector('form[data-ajax-asignar]');
        var origenAsignar = null;

        document.querySelectorAll('[data-modal-asignar]').forEach(function (boton) {
            boton.addEventListener('click', function (evento) {
                evento.preventDefault();
                origenAsignar = boton;
                formAsignar.querySelector('[name="sector_id"]').value = boton.getAttribute('data-sector');
                formAsignar.querySelector('[name="tipo_turno_id"]').value = boton.getAttribute('data-tipo');
                formAsignar.querySelector('[name="fecha"]').value = boton.getAttribute('data-fecha');
                formAsignar.querySelector('[data-descripcion-turno]').textContent = 'Turno ' + boton.getAttribute('data-descripcion') + '.';
                formAsignar.querySelector('[data-errores-modal]').classList.add('d-none');
                formAsignar.querySelector('select').value = '';
                instanciaAsignar.show(boton);
            });
        });
        modalAsignar.addEventListener('shown.bs.modal', function () { formAsignar.querySelector('select').focus(); });
        modalAsignar.addEventListener('hidden.bs.modal', function () { if (origenAsignar) { origenAsignar.focus(); } });

        formAsignar.addEventListener('submit', function (evento) {
            evento.preventDefault();
            var select = formAsignar.querySelector('select');
            if (!select.value) {
                mostrarErrores(formAsignar.querySelector('[data-errores-modal]'), { usuario_id: 'Elija el enfermero/a.' });
                return;
            }
            enviarAjax(formAsignar, formAsignar.action, origenAsignar);
        });
    }

    if (modalQuitar && window.bootstrap) {
        var instanciaQuitar = bootstrap.Modal.getOrCreateInstance(modalQuitar);
        var formQuitar = modalQuitar.querySelector('form[data-ajax-quitar]');
        var origenQuitar = null;

        document.querySelectorAll('[data-quitar]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                origenQuitar = boton;
                formQuitar.action = formQuitar.getAttribute('data-base') + '/' + boton.getAttribute('data-quitar') + '/eliminar';
                formQuitar.querySelector('[data-descripcion-turno]').textContent = boton.getAttribute('data-descripcion');
                formQuitar.querySelector('[data-errores-modal]').classList.add('d-none');
                instanciaQuitar.show(boton);
            });
        });
        modalQuitar.addEventListener('hidden.bs.modal', function () { if (origenQuitar) { origenQuitar.focus(); } });

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

            // navigator.clipboard solo existe en HTTPS o localhost; en la intranet por http se selecciona el texto.
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
