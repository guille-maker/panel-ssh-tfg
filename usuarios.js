// usuarios.js

// Validación simple al crear usuario
function validarCrearUsuario() {
    const usuario = document.getElementById('nuevoUsuario').value.trim();
    const clave = document.getElementById('nuevaClave').value;
    const regexUsuario = /^[a-z0-9_-]{3,16}$/;

    if (!regexUsuario.test(usuario)) {
        alert('El nombre de usuario debe tener entre 3 y 16 caracteres y solo letras minúsculas, números, guiones y guion bajo.');
        return false;
    }
    if (clave.length < 6) {
        alert('La contraseña debe tener al menos 6 caracteres.');
        return false;
    }
    return true;
}

// Confirmación para acciones críticas (bloquear, eliminar, forzar cambio de clave)
function confirmarAccion(event) {
    const boton = event.submitter;
    if (!boton) return true; // Por si no se puede detectar

    const accion = boton.value;
    const form = event.target;
    const usuario = form.querySelector('input[name="usuario_accion"]').value;

    let mensaje = '';
    switch(accion) {
        case 'eliminar_usuario':
            mensaje = `¿Seguro que deseas ELIMINAR el usuario "${usuario}"? Esta acción no se puede deshacer.`;
            break;
        case 'bloquear_usuario':
            mensaje = `¿Seguro que deseas BLOQUEAR el usuario "${usuario}"?`;
            break;
        case 'forzar_password':
            mensaje = `¿Seguro que deseas FORZAR el cambio de contraseña para "${usuario}"?`;
            break;
        case 'desbloquear_usuario':
            mensaje = `¿Seguro que deseas DESBLOQUEAR el usuario "${usuario}"?`;
            break;
        default:
            return true; // otras acciones no necesitan confirmación
    }

    return confirm(mensaje);
}

// Filtro en tiempo real para tabla de usuarios
function filtrarUsuarios() {
    const input = document.getElementById('buscarUsuario');
    const filtro = input.value.toLowerCase();
    const tabla = document.getElementById('tablaUsuarios');
    const filas = tabla.tBodies[0].rows;

    for (let i = 0; i < filas.length; i++) {
        const celdaUsuario = filas[i].cells[0];
        if (celdaUsuario) {
            const texto = celdaUsuario.textContent || celdaUsuario.innerText;
            filas[i].style.display = texto.toLowerCase().indexOf(filtro) > -1 ? '' : 'none';
        }
    }
}
