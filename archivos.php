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

$servidores = require 'servidores.php';

$comandos = [
    'ver_procesos' => 'ps aux',
    'espacio_disco' => 'df -h',
    'reiniciar_apache' => 'sudo systemctl restart apache2',
    'ver_usuarios' => 'who'
];

// Inicializar variables
$ssh = null;
$sftp = null;
$error = null;
$mensaje_subida = null;
$servidor_nombre = 'Desconocido';

// Manejo de conexión
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

// Establecer conexiones
try {
    // Conexión SSH
    $ssh = new SSH2($servidor['host'], $servidor['puerto']);
    $ssh->setTimeout(5);

    if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
        throw new Exception("Falló la autenticación SSH");
    }
    
    // Conexión SFTP
    $sftp = new SFTP($servidor['host'], $servidor['puerto']);
    if (!$sftp->login($servidor['usuario'], $servidor['clave'])) {
        throw new Exception("Falló la autenticación SFTP");
    }
    
} catch (Exception $e) {
    $error = "❌ Error de conexión: " . $e->getMessage();
}

// Inicializar ruta de trabajo
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = '/root';
}

// Procesar acciones solo si SFTP está conectado
if ($sftp) {
    $remoteDir = $_SESSION['cwd'];
    $archivos = $sftp->nlist($remoteDir) ?: [];

    // Manejo de acciones de archivos
    if (isset($_GET['descargar'])) {
        $archivo = $_GET['descargar'];
        $nombre = basename($archivo);
        $contenido = $sftp->get($archivo);
        
        if ($contenido !== false) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            echo $contenido;
            exit;
        } else {
            $error = "❌ No se pudo descargar el archivo";
        }
    }

    if (isset($_GET['editar'])) {
        $archivo = $_GET['editar'];
        $contenido = $sftp->get($archivo);
        // Mostrar formulario de edición más adelante
    }

    if (isset($_GET['ver'])) {
        $archivo = $_GET['ver'];
        $contenido = $sftp->get($archivo);
        // Mostrar contenido más adelante
    }

    if (isset($_GET['eliminar'])) {
        $archivo = $_GET['eliminar'];
        if ($sftp->delete($archivo)) {
            $_SESSION['mensaje'] = "✅ Archivo eliminado correctamente";
        } else {
            $_SESSION['mensaje'] = "❌ Error al eliminar el archivo";
        }
        header("Location: ?ruta_actual=" . urlencode($remoteDir));
        exit;
    }

    // Subida de archivos
    if (isset($_POST['subir_archivo']) && isset($_FILES['archivo'])) {
        $archivo = $_FILES['archivo'];
        $rutaDestino = $remoteDir . '/' . basename($archivo['name']);

        if ($archivo['error'] === UPLOAD_ERR_OK) {
            $contenido = file_get_contents($archivo['tmp_name']);
            if ($sftp->put($rutaDestino, $contenido)) {
                $mensaje_subida = "✅ Archivo subido correctamente a $rutaDestino";
            } else {
                $error = "❌ Falló la subida del archivo";
            }
        } else {
            $error = "❌ Error en la subida: código " . $archivo['error'];
        }
    }

    // Crear elementos (archivos/carpetas)
    if (isset($_POST['crear_elemento'])) {
        $nombre = trim($_POST['nuevo_elemento'] ?? '');
        $tipo = $_POST['tipo_elemento'] ?? '';
        $rutaCompleta = rtrim($remoteDir, '/') . '/' . $nombre;

        if ($nombre !== '' && $tipo !== '') {
            if ($tipo === 'archivo') {
                if (!$sftp->file_exists($rutaCompleta)) {
                    $sftp->put($rutaCompleta, '');
                    $_SESSION['mensaje'] = "📄 Archivo creado: $nombre";
                } else {
                    $_SESSION['mensaje'] = "⚠️ Ya existe un archivo con ese nombre";
                }
            } elseif ($tipo === 'carpeta') {
                if (!$sftp->is_dir($rutaCompleta)) {
                    $sftp->mkdir($rutaCompleta);
                    $_SESSION['mensaje'] = "📁 Carpeta creada: $nombre";
                } else {
                    $_SESSION['mensaje'] = "⚠️ Ya existe una carpeta con ese nombre";
                }
            }
        } else {
            $_SESSION['mensaje'] = "❌ El nombre no puede estar vacío";
        }
        header("Location: ?ruta_actual=" . urlencode($remoteDir));
        exit;
    }
}
if (isset($_POST['ajax']) && isset($_POST['cambiar_permisos']) && $sftp) {
    $archivo = $_POST['archivo'];
    $permisos = octdec($_POST['permisos']); // Convertir de octal a decimal
    
    if ($sftp->chmod($permisos, $archivo)) {
        echo "✅ Permisos cambiados correctamente a " . $_POST['permisos'];
    } else {
        echo "❌ No se pudieron cambiar los permisos";
    }
    exit();
}
// Manejo de AJAX para comandos
if (isset($_POST['ajax'])) {
    header('Content-Type: text/plain; charset=utf-8');

    if (!$ssh) {
        echo "❌ No hay conexión SSH activa";
        exit();
    }

    if (isset($_POST['comando'])) {
        $comando = trim($_POST['comando']);
        $cwd = $_SESSION['cwd'];
        
        // Comando cd
        if (preg_match('/^cd\s+(.*)/', $comando, $match)) {
            $nuevoDir = trim($match[1]);
            
            if ($nuevoDir === '..') {
                $_SESSION['cwd'] = dirname($cwd);
            } else {
                $nuevoAbsoluto = ($nuevoDir[0] === '/') ? $nuevoDir : $cwd . '/' . $nuevoDir;
                
                if ($sftp && $sftp->is_dir($nuevoAbsoluto)) {
                    $_SESSION['cwd'] = $nuevoAbsoluto;
                } else {
                    echo "❌ El directorio no existe: $nuevoDir";
                    exit;
                }
            }
            echo "📁 Directorio cambiado a: " . $_SESSION['cwd'];
            exit;
        }
        
        // Ejecutar comando normal
        $cmd = "cd " . escapeshellarg($cwd) . " && " . $comando;
        $output = $ssh->exec($cmd);

        $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario'], $cmd]);

        echo htmlspecialchars($output ?: "(Sin salida)");
        exit();
    }
}
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
        <a href="usuarios.php"><i>👤</i>Usuarios</a>
        <a href="archivos.php" class="active"><i>📂</i>Archivos</a>
        <a href="monitor.php"><i>📊</i>Monitor</a>
        <a href="procesos.php">🔍 Procesos</a>
        <a href="logs.php"><i>📄</i>Logs</a>
        <a href="configuracion.php"><i>🔧</i>Configuración</a>
        <a href="seguridad.php">🔐 Seguridad</a>
        <a href="conexion_multiserver.php"><i>🔗</i>Crons y paquetes</a>
    </nav>
</aside>

<main>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de administración</title>
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
    <h2>Panel de gestión de archivos</h2>
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
  <div class="panel">
    <button onclick="togglePanel(this)">📁 Gestión de Archivos</button>
    <div class="panel-content" style="display: block<?= (isset($_POST['subir_archivo']) || isset($_POST['crear_archivo']) || isset($_POST['crear_carpeta']) || isset($_POST['guardar_edicion']) || isset($_GET['editar']) || isset($_GET['eliminar']) || isset($_GET['descargar'])) ? 'block' : 'none' ?>;">

    <form method="get" id="formRuta">
    <label for="ruta_actual">Ruta actual:</label>
    <input type="text" name="ruta_actual" id="ruta_actual" value="<?= htmlspecialchars($remoteDir) ?>" required>
    <button type="submit">📂 Ir</button>
</form>

<hr>

<form method="get" id="formArchivos">
    <label for="archivoSelect">📁 Archivos en <?= htmlspecialchars($remoteDir) ?>:</label>
    <select name="archivo" id="archivoSelect" required>
        <option value="">-- Selecciona un archivo --</option>
        <?php foreach ($archivos as $archivo): ?>
            <?php if ($archivo === '.' || $archivo === '..') continue; ?>
            <?php $rutaCompleta = rtrim($remoteDir, '/') . '/' . $archivo; ?>
            <option value="<?= htmlspecialchars($rutaCompleta) ?>"><?= htmlspecialchars($archivo) ?></option>
        <?php endforeach; ?>
    </select>

    <div class="button-group">
        <button type="button" onclick="descargarArchivo()">📥 Descargar</button>
        <button type="button" onclick="editarArchivo()">✏️ Editar</button>
        <button type="button" onclick="cambiarPermisos()">🔐 Permisos</button>
        <button type="button" onclick="eliminarArchivo()">🗑️ Eliminar</button>
    </div>
</form>
    <input type="hidden" name="ruta_actual" value="<?= htmlspecialchars($remoteDir) ?>">
    <h3>💻 Escribe un comando</h3>
    <form id="formComando">
        <input type="text" name="comando" id="inputComando" placeholder="Ej: ls -la" required>
        <input type="submit" value="Ejecutar">
    </form>

    <div id="resultado">
        <h3>📤 Resultado del comando:</h3>
        <pre id="salida">Esperando comando...</pre>
    </div>
        <!-- Subida de archivos -->
        <h2>Subir archivo</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="archivo" required>
    <input type="submit" name="subir_archivo" value="Subir">
</form>
<div class="file-manager-form">
    <h3>Crear nuevo elemento</h3>
    <form method="post" class="unified-form">
        <!-- Ruta actual -->
        <input type="hidden" name="ruta_actual" value="<?= htmlspecialchars($remoteDir) ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="nuevo_elemento">Nombre del elemento</label>
                <input type="text" id="nuevo_elemento" name="nuevo_elemento" placeholder="Ingrese nombre" required>
            </div>
            
            <div class="form-group">
                <label for="tipo_elemento">Tipo de elemento</label>
                <select id="tipo_elemento" name="tipo_elemento" required>
                    <option value="archivo">Archivo</option>
                    <option value="carpeta">Carpeta</option>
                </select>
            </div>
        </div>
        
        <button type="submit" name="crear_elemento" class="submit-btn">
            <i class="fas fa-plus-circle"></i> Crear elemento
        </button>
    </form>
</div>


        <hr>
       
<div id="resultado-lista"></div>
        <?php
        $remoteDir = '/'; // Ajusta esto a tu servidor

        // Crear archivo vacío
        if (isset($_POST['crear_archivo']) && !empty($_POST['nuevo_archivo'])) {
            $nombreArchivo = basename($_POST['nuevo_archivo']);
            $rutaArchivo = $remoteDir . '/' . $nombreArchivo;
            $sftp->put($rutaArchivo, ""); // Crea archivo vacío
            echo "<p style='color:green;'>Archivo creado: $nombreArchivo</p>";
        }

        // Crear carpeta
        if (isset($_POST['crear_carpeta']) && !empty($_POST['nueva_carpeta'])) {
            $nombreCarpeta = basename($_POST['nueva_carpeta']);
            $rutaCarpeta = $remoteDir . '/' . $nombreCarpeta;
            if ($sftp->mkdir($rutaCarpeta)) {
                echo "<p style='color:green;'>Carpeta creada: $nombreCarpeta</p>";
            } else {
                echo "<p style='color:red;'>No se pudo crear la carpeta.</p>";
            }
        }

        // Guardar archivo editado
        if (isset($_POST['guardar_edicion']) && isset($_POST['ruta']) && isset($_POST['contenido'])) {
            $ruta = $_POST['ruta'];
            $contenido = $_POST['contenido'];
            $sftp->put($ruta, $contenido);
            echo "<p style='color:green;'>Archivo actualizado correctamente.</p>";
        }

        // Editar archivo
        if (isset($_GET['editar'])) {
            $archivoEditar = $_GET['editar'];
            $contenido = $sftp->get($archivoEditar);
            echo "<h4>Editando: $archivoEditar</h4>";
            echo '<form method="post">
                    <input type="hidden" name="ruta" value="' . htmlspecialchars($archivoEditar) . '">
                    <textarea name="contenido" rows="10" cols="80">' . htmlspecialchars($contenido) . '</textarea><br>
                    <input type="submit" name="guardar_edicion" value="Guardar cambios">
                  </form>';
        }
        
        ?>
    </div>
</div>
 

    <p><a href="añadir_usuario.php">➕ Crear nuevo usuario administrador</a> | <a href="logout.php">⏏️ Cerrar sesión</a></p>

    <script>
    // Ejecutar comando desde input o botones rápidos
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

    // Enviar comando desde formulario
    document.getElementById('formComando').addEventListener('submit', function(e) {
        e.preventDefault();
        const cmd = document.getElementById('inputComando').value;
        ejecutarComando(cmd);
    });

    // Mostrar/ocultar formulario personalizado
    function togglePersonalizado() {
        const form = document.getElementById('formPersonalizado');
        form.classList.toggle('oculto');
    }

    // Expansión/colapso de secciones tipo acordeón
    function togglePanel(header) {
        const content = header.nextElementSibling;
        content.style.display = (content.style.display === 'none') ? 'block' : 'none';
    }

    function verArchivo(ruta) {
    window.location.href = '?ver=' + encodeURIComponent(ruta) +
                           '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
}
function editarArchivo(ruta) {
    window.location.href = '?editar=' + encodeURIComponent(ruta) +
                           '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
}
function eliminarArchivo(ruta) {
    if (confirm("¿Estás seguro de que deseas eliminar este archivo?\n" + ruta)) {
        window.location.href = '?eliminar=' + encodeURIComponent(ruta) +
                               '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
    }
}

    // Listar archivos de un directorio
    function listarArchivos() {
        const ruta = document.getElementById('directorio').value;
        fetch('archivos.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax=1&listar_archivos=1&ruta=${encodeURIComponent(ruta)}`
        })
        .then(res => res.text())
        .then(html => document.getElementById('resultado-lista').innerHTML = html);
    }

    // Cambiar permisos (chmod)
    function cambiarPermisos() {
    const archivo = getSelectedFile();
    if (!archivo) return;
    
    // Mostrar un cuadro de diálogo para ingresar los permisos
    const permisos = prompt("Ingrese los permisos en formato octal (ej: 755 para rwxr-xr-x):", "644");
    
    if (permisos !== null) {
        // Validar el formato de los permisos (3-4 dígitos octales)
        if (/^[0-7]{3,4}$/.test(permisos)) {
            // Enviar la solicitud al servidor
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&cambiar_permisos=1&archivo=${encodeURIComponent(archivo)}&permisos=${permisos}`
            })
            .then(response => response.text())
            .then(mensaje => {
                alert(mensaje);
                // Recargar la lista de archivos para ver los cambios
                window.location.reload();
            })
            .catch(error => alert("Error: " + error));
        } else {
            alert("Formato inválido. Use 3 o 4 dígitos octales (0-7). Ejemplo: 755");
        }
    }
}
    function ejecutarComando(comando) {
    fetch('archivos.php', { // Cambia esto si usas otro archivo
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax=1&comando=' + encodeURIComponent(comando)
    })
    .then(res => res.text())
    .then(data => {
        document.getElementById('salida').textContent = data;
    });
}
function confirmarEliminar() {
    const archivo = document.getElementById('archivoSelect').value;
    if (!archivo) {
        alert('Selecciona un archivo para eliminar.');
        return false;
    }
    if (confirm('¿Estás seguro de que deseas eliminar este archivo?\n' + archivo)) {
        document.getElementById('formArchivos').action = '';
        document.getElementById('rutaEliminar').value = archivo;
        return true;
    }
    return false;
}

function validarArchivoSeleccionado() {
    const archivo = document.getElementById('archivoSelect').value;
    if (!archivo) {
        alert('Selecciona un archivo antes de realizar esta acción.');
        return false;
    }
    return true;
}
function obtenerRutaSeleccionada() {
    return document.getElementById('archivoSelect').value;
}

function obtenerRutaActual() {
    return document.getElementById('ruta_actual').value;
}

function setAccion(accion) {
    const select = document.getElementById('archivoSelect');
    const archivo = select.value;

    if (!archivo) {
        alert("Selecciona un archivo primero.");
        event.preventDefault();
        return false;
    }

    document.getElementById('accion').value = accion;
}

function confirmarEliminar() {
    const select = document.getElementById('archivoSelect');
    const archivo = select.value;

    if (!archivo) {
        alert("Selecciona un archivo para eliminar.");
        return false;
    }

    if (!confirm("¿Estás seguro de eliminar este archivo?")) {
        return false;
    }

    document.getElementById('accion').value = 'eliminar';
    return true;
}
function verArchivo(ruta) {
    fetch('archivos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            ver_archivo: 1,
            ruta: ruta
        })
    })
    .then(res => res.text())
    .then(data => {
        const contenedor = document.getElementById('resultado');
        contenedor.innerHTML = data;
    });
}

function getSelectedFile() {
    const select = document.getElementById('archivoSelect');
    if (!select.value) {
        alert('Selecciona un archivo primero');
        return null;
    }
    return select.value;
}

function descargarArchivo() {
    const archivo = getSelectedFile();
    if (archivo) {
        window.location.href = '?descargar=' + encodeURIComponent(archivo) + 
                              '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
    }
}

function editarArchivo() {
    const archivo = getSelectedFile();
    if (archivo) {
        window.location.href = '?editar=' + encodeURIComponent(archivo) + 
                              '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
    }
}

function verArchivo() {
    const archivo = getSelectedFile();
    if (archivo) {
        window.location.href = '?ver=' + encodeURIComponent(archivo) + 
                              '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
    }
}

function eliminarArchivo() {
    const archivo = getSelectedFile();
    if (archivo && confirm('¿Estás seguro de eliminar este archivo?\n' + archivo)) {
        window.location.href = '?eliminar=' + encodeURIComponent(archivo) + 
                              '&ruta_actual=' + encodeURIComponent(document.getElementById('ruta_actual').value);
    }
}
</script>

</head>
<body>

</body>
</html>
</main>

</body>
</html>
