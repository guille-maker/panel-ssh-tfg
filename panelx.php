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

if (isset($_SESSION['error_conexion'])) {
    // Ya falló la conexión en un intento anterior, no la repetimos
    $error = $_SESSION['error_conexion'];
    unset($_SESSION['error_conexion']); // Se muestra solo una vez
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
    // Descargar archivo
if (isset($_GET['descargar'])) {
    $archivoRemoto = $_GET['descargar'];
    $nombre = basename($archivoRemoto);
    $contenido = $sftp->get($archivoRemoto);

    if ($contenido !== false) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        echo $contenido;
        exit;
    } else {
        echo "❌ No se pudo descargar el archivo.";
    }
}

// Eliminar archivo
if (isset($_GET['eliminar'])) {
    $archivoRemoto = $_GET['eliminar'];
    if ($sftp->delete($archivoRemoto)) {
        echo "✅ Archivo eliminado: " . htmlspecialchars($archivoRemoto);
    } else {
        echo "❌ No se pudo eliminar el archivo.";
    }
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
// Manejo eliminación de archivo con SFTP
if (isset($_POST['eliminar_archivo']) && !empty($_POST['ruta_eliminar'])) {
    $ruta = $_POST['ruta_eliminar'];
    if ($sftp->delete($ruta)) {
        echo "<p style='color:green;'>Archivo eliminado: $ruta</p>";
    } else {
        echo "<p style='color:red;'>No se pudo eliminar el archivo.</p>";
    }
}
$remoteDir = '/root';  // Cambia a la ruta que desees listar

$archivos = $sftp->nlist($remoteDir);

if (!is_array($archivos)) {
    $archivos = [];  // Evitar error en foreach
}

    $remoteDir = '/root'; // Ruta que quieras mostrar
    $archivos = $sftp->nlist($remoteDir);
    
    if (!is_array($archivos)) {
        $archivos = [];  // Evita errores si la ruta no existe o no se puede acceder
    }
    ?>
    
<?php if (isset($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<!DOCTYPE html>
<!-- HTML -->
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
            padding: 20px;
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
    <h2>Panel de administración remota</h2>
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

    <!-- Acciones remotas -->
<form id="acciones">
    <button type="button" onclick="ejecutarComando('ps aux')">🧠 Ver procesos</button>
    <button type="button" onclick="ejecutarComando('df -h')">💽 Espacio en disco</button>
    <button type="button" onclick="ejecutarComando('sudo systemctl restart apache2')">🔄 Reiniciar Apache</button>
    <button type="button" onclick="ejecutarComando('who')">👥 Ver usuarios conectados</button>
    <button type="button" onclick="ejecutarComando('cat /etc/passwd')">📋 Listar usuarios</button>
    <button type="button" onclick="ejecutarComando('cat /etc/group')">👥 Listar grupos</button>
   
    <button type="button" onclick="eliminarUsuario()">❌ Eliminar usuario</button>
    <button type="button" onclick="cambiarPassword()">🔑 Cambiar contraseña usuario</button>
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
<div class="panel">
    <button onclick="togglePanel(this)">📁 Gestión de Archivos</button>
    <div class="panel-content" style="display:<?= (isset($_POST['subir_archivo']) || isset($_POST['crear_archivo']) || isset($_POST['crear_carpeta']) || isset($_POST['guardar_edicion']) || isset($_GET['editar']) || isset($_GET['eliminar']) || isset($_GET['descargar'])) ? 'block' : 'none' ?>;">

    <form method="get" id="formArchivos">
        <label for="archivoSelect">📁 Archivos en <?= htmlspecialchars($remoteDir) ?>:</label>
        <select name="archivo" id="archivoSelect">
            <option value="">-- Selecciona un archivo --</option>
            <?php foreach ($archivos as $archivo): ?>
                <?php if ($archivo === '.' || $archivo === '..') continue; ?>
                <?php $rutaCompleta = $remoteDir . '/' . $archivo; ?>
                <option value="<?= htmlspecialchars($rutaCompleta) ?>"><?= htmlspecialchars($archivo) ?></option>
            <?php endforeach; ?>
        </select>
    
        <button type="submit" formaction="?descargar=" onclick="document.getElementById('formArchivos').action='?descargar=' + encodeURIComponent(document.getElementById('archivoSelect').value);">📥 Descargar</button>
    
        <button type="submit" formaction="?editar=" onclick="document.getElementById('formArchivos').action='?editar=' + encodeURIComponent(document.getElementById('archivoSelect').value);">✏️ Editar</button>
    
        <button type="submit" name="eliminar_archivo" onclick="return confirmarEliminar();">🗑️ Eliminar</button>
        <input type="hidden" name="ruta_eliminar" id="rutaEliminar">
    </form>
    
        <!-- Subida de archivos -->
        <h2>Subir archivo</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="archivo" required>
    <input type="submit" name="subir_archivo" value="Subir">
</form>
         <!-- Eliminacion de archivos -->
<h2>Eliminar archivo</h2>
<form method="post">
    <label>Ruta del archivo a eliminar:</label>
    <input type="text" name="ruta" placeholder="/root/archivo.txt" required>
    <input type="submit" name="eliminar_archivo" value="Eliminar">
</form>
<div class="file-manager-form">
    <h3>Crear nuevo elemento</h3>
    <form method="post" class="unified-form">
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

        <?php
        $remoteDir = '/ruta/remota/deseada'; // Ajusta esto a tu servidor

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

        // Listar archivos
        try {
            $archivos = $sftp->nlist($remoteDir);
            if ($archivos) {
                echo "<h4>Contenido de $remoteDir:</h4><ul>";
                foreach ($archivos as $archivo) {
                    if ($archivo == '.' || $archivo == '..') continue;
                    $rutaCompleta = $remoteDir . '/' . $archivo;
                    echo "<li>$archivo 
                            <a href='?descargar=$rutaCompleta'>[Descargar]</a> 
                            <a href='?editar=$rutaCompleta'>[Editar]</a> 
                            <a href='?eliminar=$rutaCompleta' onclick='return confirm(\"¿Eliminar $archivo?\")'>[Eliminar]</a>
                          </li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No se encontraron archivos ni carpetas.</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error al listar archivos: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</div>



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
    const usuario = prompt('Introduce el nombre de usuario para cambiar la contraseña:');
    if (usuario) {
        // Aquí se recomienda hacerlo manualmente en servidor, pero para ejecutar passwd
        // desde SSH sin interacción, habría que usar expect o un método distinto,
        // que es complejo y peligroso.
        // Por simplicidad, solo mostramos un mensaje:
        alert('Para cambiar la contraseña, conecta vía SSH o usa el comando: passwd ' + usuario);
    }
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
function togglePanel(header) {
    const content = header.nextElementSibling;
    content.style.display = (content.style.display === 'none') ? 'block' : 'none';
}
</script>
</head>
<body>

</body>
</html>