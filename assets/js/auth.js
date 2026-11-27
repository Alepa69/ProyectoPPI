// Mostrar/ocultar contraseña en login y registro por 3 segundos
const btnVerLogin = document.getElementById('btnVerLoginClave');
const loginClave = document.getElementById('loginClave');
let timerLogin = null;

if (btnVerLogin && loginClave) {
    btnVerLogin.addEventListener('click', function () {
        if (timerLogin) clearTimeout(timerLogin);
        if (loginClave.type === 'password') {
            loginClave.type = 'text';
            btnVerLogin.innerHTML = '<i class="bi bi-eye-slash"></i>';
            timerLogin = setTimeout(function () {
                loginClave.type = 'password';
                btnVerLogin.innerHTML = '<i class="bi bi-eye"></i>';
            }, 3000);
        } else {
            loginClave.type = 'password';
            btnVerLogin.innerHTML = '<i class="bi bi-eye"></i>';
        }
    });
}

const btnVerClaves = document.getElementById('btnVerClaves');
const regClave = document.getElementById('regClave');
const regClave2 = document.getElementById('regClave2');
let timerVerClaves = null;

if (btnVerClaves && regClave && regClave2) {
    btnVerClaves.addEventListener('click', function () {
        const ocultas = regClave.type === 'password';
        if (timerVerClaves) clearTimeout(timerVerClaves);

        if (ocultas) {
            regClave.type = 'text';
            regClave2.type = 'text';
            btnVerClaves.innerHTML = '<i class="bi bi-eye-slash"></i> Ocultar contraseñas';
            timerVerClaves = setTimeout(function () {
                regClave.type = 'password';
                regClave2.type = 'password';
                btnVerClaves.innerHTML = '<i class="bi bi-eye"></i> Ver ambas contraseñas (3 s)';
            }, 3000);
        } else {
            regClave.type = 'password';
            regClave2.type = 'password';
            btnVerClaves.innerHTML = '<i class="bi bi-eye"></i> Ver ambas contraseñas (3 s)';
        }
    });
}

// Mostrar modal de éxito en registro si está presente
const modalExitoEl = document.getElementById('modalExito');
if (modalExitoEl && window.bootstrap) {
    const modal = new bootstrap.Modal(modalExitoEl);
    modal.show();
}
