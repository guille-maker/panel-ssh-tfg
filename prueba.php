<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$comandos = [
    'ver_procesos' => 'ps aux',
    'espacio_disco' => 'df -h',
    'reiniciar_apache' => 'sudo systemctl restart apache2',
    'ver_usuarios' => 'who'
];

// Conexión personalizada
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

$servidor = $_SESSION['conexion_personalizada'] ?? $servidores['ubuntu'];
$servidor_nombre = $_SESSION['nombre_servidor'] ?? 'Ubuntu';

$ssh = null;

if (isset($_SESSION['error_conexion'])) {
    $error = $_SESSION['error_conexion'];
    unset($_SESSION['error_conexion']);
} else {
    try {
        $ssh = new SSH2($servidor['host'], $servidor['puerto']);
        $ssh->setTimeout(5);
        if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
            $_SESSION['error_conexion'] = "❌ Falló la autenticación SSH (usuario o contraseña incorrectos)";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } catch (UnableToConnectException $e) {
        $_SESSION['error_conexion'] = "❌ No se pudo conectar al servidor SSH: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_conexion'] = "❌ Error inesperado: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$sftp = new SFTP($servidor['host'], $servidor['puerto']);
if (!$sftp->login($servidor['usuario'], $servidor['clave'])) {
    $_SESSION['error_conexion'] = "❌ Falló la autenticación SFTP";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

//
// 🧠 A PARTIR DE AQUÍ: Lógica AJAX
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: text/plain; charset=utf-8');

    $accion = $_POST['accion'] ?? '';
    $grupo = $_POST['grupo'] ?? '';

    if ($accion === 'crear_grupo' && $grupo) {
        $cmd = "sudo groupadd " . escapeshellarg($grupo);
        $salida = $ssh->exec($cmd);

        // Registrar log
        $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario'], $cmd]);

        echo "✅ Grupo '$grupo' creado.\n" . $salida;
    } else {
        echo "❌ Datos incompletos o acción no reconocida.";
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'ultimos_logins') {
        // Ejecutar el comando para mostrar los últimos 10 inicios de sesión
        $output = shell_exec('last -n 10');
        // Enviar la salida como texto plano
        echo nl2br(htmlspecialchars($output));
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'historial_usuario' && !empty($_POST['usuario'])) {
        $usuario = escapeshellarg($_POST['usuario']);
        $path = "/home/$usuario/.bash_history";

        // Comprobar que el archivo existe y es legible
        if (file_exists($path) && is_readable($path)) {
            $historial = file_get_contents($path);
            echo nl2br(htmlspecialchars($historial));
        } else {
            echo "No se pudo leer el historial del usuario o el archivo no existe.";
        }
        exit;
    }
}
// FIN de bloque AJAX
?>

<?php if (isset($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel SSH</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            height: 100vh;
            background-color: #f4f6f9;
        }
        aside {
            width: 230px;
            background: #1e1e2f;
            color: #fff;
            display: flex;
            flex-direction: column;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar-logo {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        nav {
            flex-grow: 1;
        }
        nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #c0c0c0;
            text-decoration: none;
            transition: 0.2s;
            border-left: 4px solid transparent;
        }
        nav a:hover {
            background: #2c2c3e;
            color: #ffffff;
            border-left: 4px solid #3498db;
        }
        nav a.active {
            background: #2c2c3e;
            color: #ffffff;
            border-left: 4px solid #3498db;
        }
        nav a i {
            margin-right: 10px;
        }
        main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<aside>
    <div class="sidebar-logo">🛠 Configuración</div>
    <nav>
        <a href="panel.php"><i>🖥️</i>Comandos</a>
        <a href="usuarios.php" class="active"><i>👤</i>Usuarios</a>
        <a href="archivos.php"><i>📂</i>Archivos</a>
        <a href="monitor.php"><i>📊</i>Monitor</a>
        <a href="logs.php"><i>📄</i>Logs</a>
        <a href="configuracion.php"><i>🔧</i>Configuración</a>
    </nav>
</aside>

<main>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de gestión de usuarios</title>
    <style>
         .panel-content form {
        margin-bottom: 10px;
    }

    .panel-content input[type="text"],
    .panel-content input[type="file"],
    .panel-content input[type="submit"],
    .panel-content select,
    .panel-content label,
    .panel-content button {
        display: inline-block;
        margin: 5px 5px 5px 0;
        vertical-align: middle;
    }

    .panel-content textarea {
        width: 100%;
        margin-top: 5px;
    }

    .panel-content h2 {
        margin: 10px 0 5px;
        font-size: 1em;
    }

    .panel-content hr {
        margin: 15px 0;
    }
        body {
            background-color: #0e101a;
            color: #d1d9ff;
            font-family: 'Segoe UI', sans-serif;
            padding: 0px;
        }

        h2, h3 {
            color: #a0c4ff;
        }

        form {
            background-color: #1a1f2e;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(90, 133, 255, 0.3);
        }

        label {
            display: block;
            margin-top: 10px;
            color: #cbd5ff;
        }

        input[type="text"], input[type="password"], input[type="number"], select {
            background-color: #0e162b;
            color: #ffffff;
            border: 1px solid #3f587e;
            padding: 8px;
            margin-top: 5px;
            border-radius: 8px;
            width: 100%;
        }

        input[type="submit"], button {
            background-color: #3a5dfb;
            border: none;
            color: white;
            padding: 8px 16px;
            margin-top: 10px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover, input[type="submit"]:hover {
            background-color: #5671fc;
        }

        pre {
            background-color: #1a1f2e;
            padding: 10px;
            border-radius: 10px;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        a {
            color: #7faaff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .conectado {
            color: #71ff71;
            font-weight: bold;
        }

        .oculto {
            display: none;
        }
    </style>
</head>
<body>
<button onclick="crearGrupo()">Crear Grupo</button>


    <h2>Panel de gestión de usuarios</h2>
    <p>👤 Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></p>
    <?php if ($ssh): ?>
    <p>🖧 Conectado a: <span class="conectado"><?= $servidor['host'] ?> como <?= $servidor['usuario'] ?> (<?= $servidor_nombre ?>)</span></p>
<?php elseif (isset($_SESSION['error_conexion'])): ?>
    <p style="color:red;"><?= htmlspecialchars($_SESSION['error_conexion']) ?></p>
    <?php unset($_SESSION['error_conexion']); ?>
<?php endif; ?>

    <button onclick="togglePersonalizado()">🔧 Mostrar/Ocultar conexión personalizada</button>

    <form method="post" id="formPersonalizado" class="oculto">
        <h3>🔌 Conectar a servidor personalizado</h3>
        <label>IP o Host:</label>
        <input type="text" name="host" placeholder="192.168.1.100" required>

        <label>Puerto (por defecto 22 o 2222):</label>
        <input type="number" name="puerto" value="2222" required>

        <label>Usuario:</label>
        <input type="text" name="usuario" required>

        <label>Contraseña:</label>
        <input type="password" name="clave" required>

        <input type="submit" name="conectar" value="Conectar">
    </form>

    <form method="post">
        <label for="servidor">🎯 O selecciona un servidor predefinido:</label>
        <select name="servidor" id="servidor">
            <option value="ubuntu">Ubuntu</option>
            <option value="debian">Debian</option>
            <option value="alpine">Alpine</option>
        </select>
        <input type="submit" value="Cambiar servidor">
    </form>
    <h2>👥Administración de usuarios</h2>
    <!-- Acciones remotas -->
<form id="acciones">
    <button type="button" onclick="ejecutarComando('who')">👥 Ver usuarios conectados</button>
    <button type="button" onclick="ejecutarComando('cat /etc/passwd')">📋 Listar usuarios</button>
    <button type="button" onclick="ejecutarComando('cat /etc/group')">👥 Listar grupos</button>
   
    <button type="button" onclick="eliminarUsuario()">❌ Eliminar usuario</button>
    <button type="button" onclick="cambiarPassword()">🔑 Cambiar contraseña usuario</button>
    <button type="button" onclick="accionUsuario('bloquear_usuario')">🔒 Bloquear usuario</button>
    <button type="button" onclick="accionUsuario('desbloquear_usuario')">🔓 Desbloquear usuario</button>
    <button type="button" onclick="accionUsuario('forzar_password')">🔐 Forzar cambio de contraseña</button>
    <button type="button" onclick="accionUsuario('expirar_usuario')">📅 Establecer caducidad</button>
    <button type="button" onclick="accionUsuario('ver_info_usuario')">ℹ️ Ver info usuario</button>
    <button type="button" onclick="verUltimosLogins()">ℹ️ Últimos login</button>
    <button type="button" onclick="VerHistorialUsuario()">ℹ️ Historial usuario</button>
</form>


<!-- Botón para mostrar/ocultar formulario de crear usuario -->
<button type="button" onclick="toggleCrearUsuario()">➕ Crear nuevo usuario</button>

<!-- Formulario oculto para crear usuario -->
<form id="formCrearUsuario" class="oculto" onsubmit="crearUsuario(event)">
    <h3>Crear nuevo usuario</h3>
    <label>Nombre de usuario:</label>
    <input type="text" id="nuevoUsuario" name="nuevoUsuario" required>
    
    <label>Contraseña:</label>
    <input type="password" id="nuevaClave" name="nuevaClave" required>
    
    <button type="submit">Crear usuario</button>
</form>

<section class="panel">
  <h2 >👥 Administración de grupos</h2>
  <form>
    <button onclick="crearGrupo()">➕ Crear grupo</button>
    <button onclick="eliminarGrupo()">🗑️ Eliminar grupo</button>
    <button onclick="listarGrupos()">📄 Listar grupos</button>
    <button onclick="agregarUsuarioAGrupo()">👤➕ Añadir usuario a grupo</button>
    <button onclick="quitarUsuarioDeGrupo()">👤➖ Quitar usuario de grupo</button>
    <button onclick="verGruposDeUsuario()">🔎 Ver grupos de un usuario</button>
    <button onclick="renombrarGrupo()">✏️ Renombrar grupo</button>
    <button onclick="cambiarGIDGrupo()">🔢 Cambiar GID de grupo</button>
    <button onclick="cambiarGrupoPrincipal()">👥 Cambiar grupo principal</button>
    <button onclick="verUIDGID()">🧾 Ver UID/GID</button>
    <button onclick="verArchivoGrupos()">📂 Ver /etc/group</button>
    <button onclick="verMiembrosGrupo()">👀 Ver miembros de grupo</button>
    </form>
</section>
<section class="panel-opciones extra-gestion">
    <h2>🔧 Funciones Avanzadas</h2>
    
    <div class="acciones-grupos">
        <h3>📤 Exportar Datos</h3>
        <button onclick="exportarDatos('usuarios', 'csv')">Usuarios CSV</button>
        <button onclick="exportarDatos('usuarios', 'json')">Usuarios JSON</button>
        <button onclick="exportarDatos('grupos', 'csv')">Grupos CSV</button>
        <button onclick="exportarDatos('grupos', 'json')">Grupos JSON</button>
    </div>

    <div class="acciones-logs">
        <h3>📝 Historial de Cambios</h3>
        <button onclick="verLogs()">Ver Logs</button>
        <div id="logContainer" class="log-box oculto"></div>
    </div>

    <div class="plantillas">
        <h3>📂 Plantillas</h3>
        <button onclick="crearDesdePlantilla('usuario')">Crear usuario desde plantilla</button>
        <button onclick="crearDesdePlantilla('grupo')">Crear grupo desde plantilla</button>
    </div>
</section>

    <h3>💻 Escribe un comando</h3>
    <form id="formComando">
        <input type="text" name="comando" id="inputComando" placeholder="Ej: ls -la" required>
        <input type="submit" value="Ejecutar">
    </form>

    <div id="resultado">
        <h3>📤 Resultado del comando:</h3>
        <pre id="salida">Esperando comando...</pre>
    </div>

    <p><a href="añadir_usuario.php">➕ Crear nuevo usuario administrador</a> | <a href="logout.php">⏏️ Cerrar sesión</a></p>

    <script>
function ejecutarComando(comando) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax=1&comando=' + encodeURIComponent(comando)
    })
    .then(res => res.text())
    .then(data => {
        document.getElementById('salida').textContent = data;
    });
}

function eliminarUsuario() {
    const usuario = prompt('Introduce el nombre de usuario a eliminar:');
    if (usuario) {
        ejecutarComando('sudo deluser ' + usuario);
    }
}

        function confirmarEliminar() {
            const select = document.getElementById('archivoSelect');
            const ruta = select.value;
            if (!ruta) return false;
            const archivo = select.options[select.selectedIndex].text;
            if (confirm(`¿Estás seguro de eliminar "${archivo}"?`)) {
                document.getElementById('rutaEliminar').value = ruta;
                document.getElementById('formArchivos').action = '';
                return true;
            }
            return false;
        }

function cambiarPassword() {
    const usuario = prompt('Introduce el nombre de usuario:');
    if (!usuario) return;

    const clave = prompt('Introduce la nueva contraseña:');
    if (!clave) return;

    fetch('usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            accion: 'cambiar_password',
            usuario: usuario,
            clave: clave
        })
    })
    .then(response => response.text())
    .then(data => {
        alert('Contraseña cambiada (o hubo un error). Revisa la página.');
        location.reload();
    });
}

document.getElementById('formComando').addEventListener('submit', function(e) {
    e.preventDefault();
    const cmd = document.getElementById('inputComando').value;
    ejecutarComando(cmd);
});

        function togglePersonalizado() {
            const form = document.getElementById('formPersonalizado');
            form.classList.toggle('oculto');
        }

        function ejecutarComando(comando) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ajax=1&comando=' + encodeURIComponent(comando)
            })
            .then(res => res.text())
            .then(data => {
                document.getElementById('salida').textContent = data;
            });
        }

        document.getElementById('formComando').addEventListener('submit', function(e) {
            e.preventDefault();
            const cmd = document.getElementById('inputComando').value;
            ejecutarComando(cmd);
        });
function toggleCrearUsuario() {
    const form = document.getElementById('formCrearUsuario');
    form.classList.toggle('oculto');
}

document.getElementById('formCrearUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    const usuario = document.getElementById('nuevoUsuario').value.trim();
    const clave = document.getElementById('nuevaClave').value.trim();
    
    if (!usuario || !clave) {
        alert('Por favor completa todos los campos');
        return;
    }

    // Enviar AJAX para crear usuario
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax=1&accion=crear_usuario&usuario=' + encodeURIComponent(usuario) + '&clave=' + encodeURIComponent(clave)
    })
    .then(res => res.text())
    .then(data => {
        document.getElementById('salida').textContent = data;
        // Opcional: limpiar formulario y ocultarlo
        document.getElementById('formCrearUsuario').classList.add('oculto');
        this.reset();
    })
    .catch(() => alert('Error en la comunicación'));
});

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


function togglePanel(header) {
    const content = header.nextElementSibling;
    content.style.display = (content.style.display === 'none') ? 'block' : 'none';
}

async function accionUsuario(accion) {
  const usuario = prompt("Usuario:");
  if (!usuario) return;

  let extra = '';
  if (accion === 'expirar_usuario') {
    extra = prompt("Introduce fecha de expiración (AAAA-MM-DD):");
    if (!extra) return;
  }

  const form = new FormData();
  form.append('ajax', '1'); // 👈 Necesario para que PHP lo trate como AJAX
  form.append('accion', accion);
  form.append('usuario', usuario);
  if (extra) form.append('extra', extra);

  try {
    const res = await fetch('usuarios.php', { method: 'POST', body: form });
    const txt = await res.text();
    alert(txt);
  } catch (err) {
    alert('❌ Error de red o del servidor.');
    console.error(err);
  }
}
function verUltimosLogins() {
  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'usuarios.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        document.getElementById('salida').innerHTML = xhr.responseText;
      } else {
        alert('Error al obtener los últimos inicios de sesión');
      }
    }
  };

  xhr.send('ajax=1&accion=ultimos_logins');
}
function verHistorialUsuario() {
  const usuario = prompt('Introduce el nombre del usuario para ver su historial:');
  if (!usuario) return;

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'usuarios.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        document.getElementById('salida').innerHTML = xhr.responseText;
      } else {
        alert('Error al obtener el historial del usuario');
      }
    }
  };

  xhr.send('accion=historial_usuario&usuario=' + encodeURIComponent(usuario));
}
function crearGrupo() {
    const grupo = prompt("Nombre del nuevo grupo:");
    if (!grupo) return;
    ejecutarComando(`sudo groupadd ${grupo}`);
}

function eliminarGrupo() {
    const grupo = prompt("Nombre del grupo a eliminar:");
    if (!grupo) return;
    if (confirm(`¿Seguro que deseas eliminar el grupo "${grupo}"?`)) {
        ejecutarComando(`sudo groupdel ${grupo}`);
    }
}

function listarGrupos() {
    ejecutarComando("cut -d: -f1 /etc/group");
}

function agregarUsuarioAGrupo() {
    const usuario = prompt("Nombre del usuario:");
    if (!usuario) return;
    const grupo = prompt("Nombre del grupo:");
    if (!grupo) return;
    ejecutarComando(`sudo usermod -aG ${grupo} ${usuario}`);
}

function quitarUsuarioDeGrupo() {
    const usuario = prompt("Nombre del usuario:");
    if (!usuario) return;
    const grupo = prompt("Nombre del grupo:");
    if (!grupo) return;
    ejecutarComando(`sudo gpasswd -d ${usuario} ${grupo}`);
}

function verGruposDeUsuario() {
    const usuario = prompt("Nombre del usuario:");
    if (!usuario) return;
    ejecutarComando(`groups ${usuario}`);
}
function verMiembrosGrupo() {
    const grupo = prompt("Nombre del grupo:");
    if (!grupo) return;
    ejecutarComando(`getent group ${grupo}`);
}
function verArchivoGrupos() {
    ejecutarComando("cat /etc/group");
}
function verUIDGID() {
    const usuario = prompt("Usuario:");
    if (!usuario) return;
    ejecutarComando(`id ${usuario}`);
}
function cambiarGrupoPrincipal() {
    const usuario = prompt("Usuario:");
    if (!usuario) return;
    const grupo = prompt("Nuevo grupo principal:");
    if (!grupo) return;
    ejecutarComando(`sudo usermod -g ${grupo} ${usuario}`);
}
function exportarDatos(tipo, formato) {
    fetch(`usuarios.php?exportar=${tipo}&formato=${formato}`)
        .then(res => res.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${tipo}.${formato}`;
            a.click();
            window.URL.revokeObjectURL(url);
        });
}

function verLogs() {
    fetch('usuarios.php?accion=ver_logs')
        .then(res => res.text())
        .then(data => {
            const logContainer = document.getElementById('logContainer');
            logContainer.innerHTML = data;
            logContainer.classList.toggle('oculto');
        });
}

function crearDesdePlantilla(tipo) {
    const plantilla = prompt(`Nombre de la plantilla para el ${tipo}:`);
    if (!plantilla) return;

    fetch('usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=1&accion=crear_desde_plantilla&tipo=${tipo}&plantilla=${encodeURIComponent(plantilla)}`
    })
    .then(res => res.text())
    .then(data => alert(data));
}

function crearGrupo() {
    const grupo = prompt("Nombre del grupo:");
    if (!grupo) return;

    fetch("prueba.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            ajax: "1",
            accion: "crear_grupo",
            grupo: grupo
        })
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
        console.log(data);
    })
    .catch(err => alert("❌ Error al crear el grupo: " + err));
}

</script>
</head>
<body>

</body>
</html>
</main>
