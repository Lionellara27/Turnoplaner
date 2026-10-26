/* ==========================================================
   TurnoPlaner - JS base (vanilla, sin librerias)
   ========================================================== */

document.addEventListener('DOMContentLoaded', function () {

    // Las alertas se ocultan solas a los 4 segundos.
    document.querySelectorAll('.alerta').forEach(function (alerta) {
        setTimeout(function () {
            alerta.style.transition = 'opacity 0.4s';
            alerta.style.opacity = '0';
            setTimeout(function () { alerta.remove(); }, 400);
        }, 4000);
    });

    // Validacion del login del lado del cliente.
    // Por ahora NO bloquea el envio (el login todavia no valida credenciales).
    // Cuando se complete HU-02 hay que descomentar el "return" marcado abajo.
    var form = document.getElementById('form-login');
    if (form) {
        form.addEventListener('submit', function (e) {
            var usuario = form.querySelector('#usuario');
            var password = form.querySelector('#password');
            var hayError = false;

            [usuario, password].forEach(function (input) {
                input.classList.remove('invalido');
                var viejo = input.parentElement.querySelector('.campo__error');
                if (viejo) { viejo.remove(); }
                if (input.value.trim() === '') {
                    hayError = true;
                    input.classList.add('invalido');
                    var msg = document.createElement('small');
                    msg.className = 'campo__error';
                    msg.textContent = 'Este campo es obligatorio.';
                    input.parentElement.appendChild(msg);
                }
            });

            if (hayError) {
                // e.preventDefault();  // <- activar cuando el login valide de verdad
                // return;
            }
        });
    }
});
