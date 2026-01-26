<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

require 'vendor/autoload.php';
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

$servidores = [
    'ubuntu' => ['host' => '127.0.0.1', 'puerto' => 2222, 'usuario' => 'root', 'clave' => 'root'],
    'debian' => ['host' => '127.0.0.1', 'puerto' => 2223, 'usuario' => 'root', 'clave' => 'root'],
    'alpine' => ['host' => '127.0.0.1', 'puerto' => 2224, 'usuario' => 'root', 'clave' => 'root']
];

// Comandos rápidos predefinidos
$comandos = [
    'ver_procesos' => 'ps aux',
    'espacio_disco' => 'df -h',
    'reiniciar_apache' => 'sudo systemctl restart apache2',
    'ver_usuarios' => 'who'
];

// Manejo de conexión personalizada
if (isset($_POST['conectar'])) {
    $_SESSION['conexion_personalizada'] = [
        'host' => $_POST['host'],
        'puerto' => $_POST['puerto'],
        'usuario' => $_POST['usuario'],
        'clave' => $_POST['clave']
    ];
    $_SESSION['nombre_servidor'] = 'Personalizado';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
} elseif (isset($_POST['servidor'])) {
    $_SESSION['conexion_personalizada'] = $servidores[$_POST['servidor']];
    $_SESSION['nombre_servidor'] = ucfirst($_POST['servidor']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Si ya hay conexión activa
$servidor = $_SESSION['conexion_personalizada'] ?? $servidores['ubuntu'];
$servidor_nombre = $_SESSION['nombre_servidor'] ?? 'Ubuntu';

$ssh = null; // Inicializamos para usar después
$error = null;

try {
    $ssh = new SSH2($servidor['host'], $servidor['puerto']);
    $ssh->setTimeout(5);
    if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
        throw new Exception("❌ Falló la autenticación SSH (usuario o contraseña incorrectos)");
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$sftp = new SFTP($servidor['host'], $servidor['puerto']);
if (!$sftp->login($servidor['usuario'], $servidor['clave'])) {
    $error = "❌ Falló la autenticación SFTP";
}

// Manejo de comandos vía AJAX
if (isset($_POST['ajax'])) {
    header('Content-Type: text/plain; charset=utf-8');

    if (!$ssh) {
        echo "❌ No hay conexión SSH activa.";
        exit();
    }

    // Ejecutar comando simple o predefinido
    if (isset($_POST['comando'])) {
        $cmdKey = $_POST['comando'];
        $cmd = $comandos[$cmdKey] ?? $cmdKey;
        $output = $ssh->exec($cmd);

        $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario'], $cmd]);

        echo htmlspecialchars($output);
        exit();
    }

    // Crear usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear_usuario') {
        $usuarioNuevo = $_POST['usuario'] ?? '';
        $claveNueva = $_POST['clave'] ?? '';
        if (!$usuarioNuevo || !$claveNueva) {
            echo "❌ Faltan datos para crear el usuario.";
            exit();
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $usuarioNuevo)) {
            echo "❌ Nombre de usuario inválido. Solo letras, números y guiones bajos permitidos.";
            exit();
        }
        $comandoAdd = "sudo useradd -m " . escapeshellarg($usuarioNuevo);
        $salidaAdd = $ssh->exec($comandoAdd);

        $comandoPass = "echo " . escapeshellarg($claveNueva . "\n" . $claveNueva) . " | sudo passwd " . escapeshellarg($usuarioNuevo);
        $salidaPass = $ssh->exec($comandoPass);

        if (strpos($salidaAdd, 'Error') === false && strpos($salidaPass, 'password updated successfully') !== false) {
            echo "✅ Usuario '$usuarioNuevo' creado correctamente.";
        } else {
            echo "❌ Error al crear usuario o establecer contraseña.\n" . htmlspecialchars($salidaAdd . "\n" . $salidaPass);
        }
        $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario'], "useradd $usuarioNuevo"]);

        exit();
    }

    // Eliminar usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_usuario') {
        $usuarioEliminar = $_POST['usuario'] ?? '';
        if (!$usuarioEliminar) {
            echo "❌ Debe especificar un nombre de usuario.";
            exit();
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $usuarioEliminar)) {
            echo "❌ Nombre de usuario inválido.";
            exit();
        }
        $cmdDel = "sudo deluser --remove-home " . escapeshellarg($usuarioEliminar);
        $salidaDel = $ssh->exec($cmdDel);

        if (strpos($salidaDel, 'Removing user') !== false || strpos($salidaDel, 'Deleted user') !== false || trim($salidaDel) === '') {
            echo "✅ Usuario '$usuarioEliminar' eliminado correctamente.";
        } else {
            echo "❌ Error al eliminar usuario.\n" . htmlspecialchars($salidaDel);
        }
        $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario'], "deluser $usuarioEliminar"]);

        exit();
    }

    // Cambiar contraseña usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
        $usuarioPass = $_POST['usuario'] ?? '';
        $clavePass = $_POST['clave'] ?? '';
        if (!$usuarioPass || !$clavePass) {
            echo "❌ Debe especificar usuario y nueva contraseña.";
            exit();
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $usuarioPass)) {
            echo "❌ Nombre de usuario inválido.";
            exit();
        }
        $cmdPass = "echo " . escapeshellarg($clavePass . "\n" . $clavePass) . " | sudo passwd " . escapeshellarg($usuarioPass);
        $salidaPass = $ssh->exec($cmdPass);

        if (strpos($salidaPass, 'password updated successfully') !== false) {
            echo "✅ Contraseña cambiada correctamente para '$usuarioPass'.";
        } else {
            echo "❌ Error al cambiar contraseña.\n" . htmlspecialchars($salidaPass);
        }
        $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario'], "passwd $usuarioPass"]);

        exit();
    }
}

// Manejo subida de archivo con SFTP
if (isset($_POST['subir_archivo']) && isset($_FILES['archivo'])) {
    if (!$sftp) {
        $error = "❌ No hay conexión SFTP activa.";
    } else {
        $archivo = $_FILES['archivo'];
        $rutaDestino = '/root/' . basename($archivo['name']); // Puedes cambiar la ruta si quieres

        if ($archivo['error'] === UPLOAD_ERR_OK) {
            $contenido = file_get_contents($archivo['tmp_name']);
            if ($sftp->put($rutaDestino, $contenido)) {
                $mensaje_subida = "✅ Archivo subido correctamente a $rutaDestino.";
            } else {
                $error = "❌ Falló la subida del archivo.";
            }
        } else {
            $error = "❌ Error en la subida del archivo: código " . $archivo['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Panel de Administración Remota SSH</title>
<style>
    body { font-family: Arial, sans-serif; background:#eee; }
    .error { color: red; }
    .success { color: green; }
    #resultado { white-space: pre-wrap; background:#fff; padding:10px; margin-top: 10px; border-radius:5px; max-height: 300px; overflow-y: auto;}
    button { margin: 3px; padding: 8px; }
    #formCrearUsuario, #formCambiarPass { display:none; margin-top: 10px; background:#ddd; padding: 10px; border-radius:5px; }
    label { display: block; margin: 5px 0 2px; }
</style>
<script>
function ejecutarComando(comando) {
    document.getElementById('resultado').textContent = "⏳ Ejecutando...";
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            comando: comando
        })
    })
    .then(res => res.text())
    .then(data => {
        document.getElementById('resultado').textContent = data;
    })
    .catch(() => {
        document.getElementById('resultado').textContent = "❌ Error de conexión.";
    });
}

function mostrarFormCrear() {
    document.getElementById('formCrearUsuario').style.display = 'block';
    document.getElementById('formCambiarPass').style.display = 'none';
}

function mostrarFormCambiarPass() {
    document.getElementById('formCambiarPass').style.display = 'block';
    document.getElementById('formCrearUsuario').style.display = 'none';
}

function crearUsuario() {
    const usuario = document.getElementById('nuevoUsuario').value.trim();
    const clave = document.getElementById('claveUsuario').value.trim();

    if (!usuario || !clave) {
        alert('Debe ingresar usuario y clave.');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            accion: 'crear_usuario',
            usuario: usuario,
            clave: clave
        })
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
        if (data.startsWith('✅')) {
            document.getElementById('formCrearUsuario').reset();
        }
    });
}

function eliminarUsuario() {
    const usuario = prompt("Ingrese el nombre del usuario a eliminar:");
    if (!usuario) return;

    if (!confirm(`¿Está seguro de eliminar al usuario '${usuario}'? Esta acción no se puede deshacer.`)) return;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            accion: 'eliminar_usuario',
            usuario: usuario
        })
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
    });
}

function cambiarPassword() {
    const usuario = document.getElementById('usuarioPass').value.trim();
    const clave = document.getElementById('clavePass').value.trim();

    if (!usuario || !clave) {
        alert('Debe ingresar usuario y nueva contraseña.');
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            ajax: 1,
            accion: 'cambiar_password',
            usuario: usuario,
            clave: clave
        })
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
        if (data.startsWith('✅')) {
            document.getElementById('formCambiarPass').reset();
        }
    });
}
</script>
</head>
<body>

<h2>Panel SSH - Servidor: <?=htmlspecialchars($servidor_nombre)?></h2>

<?php if ($error): ?>
    <p class="error"><?=htmlspecialchars($error)?></p>
<?php endif; ?>

<!-- Botones comandos predefinidos -->
<div>
    <button onclick="ejecutarComando('ver_procesos')">Ver procesos</button>
    <button onclick="ejecutarComando('espacio_disco')">Espacio disco</button>
    <button onclick="ejecutarComando('reiniciar_apache')">Reiniciar Apache</button>
    <button onclick="ejecutarComando('ver_usuarios')">Ver usuarios</button>
</div>

<!-- Acciones Usuarios -->
<div style="margin-top:20px;">
    <button onclick="mostrarFormCrear()">Crear Usuario</button>
    <button onclick="eliminarUsuario()">Eliminar Usuario</button>
    <button onclick="mostrarFormCambiarPass()">Cambiar contraseña</button>
</div>

<!-- Formulario Crear Usuario -->
<div id="formCrearUsuario">
    <h3>Crear Usuario</h3>
    <label for="nuevoUsuario">Usuario:</label>
    <input type="text" id="nuevoUsuario" name="nuevoUsuario" />
    <label for="claveUsuario">Contraseña:</label>
    <input type="password" id="claveUsuario" name="claveUsuario" />
    <button onclick="crearUsuario()">Crear</button>
</div>

<!-- Formulario Cambiar Contraseña -->
<div id="formCambiarPass">
    <h3>Cambiar contraseña usuario</h3>
    <label for="usuarioPass">Usuario:</label>
    <input type="text" id="usuarioPass" name="usuarioPass" />
    <label for="clavePass">Nueva contraseña:</label>
    <input type="password" id="clavePass" name="clavePass" />
    <button onclick="cambiarPassword()">Cambiar</button>
</div>

<!-- Subida archivo -->
<h3>Subir archivo al servidor</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="archivo" required />
    <button type="submit" name="subir_archivo">Subir archivo</button>
</form>

<?php if (isset($mensaje_subida)): ?>
    <p class="success"><?=htmlspecialchars($mensaje_subida)?></p>
<?php endif; ?>

<div id="resultado"></div>

</body>
</html>
