<?php
session_start();
require 'vendor/autoload.php'; // phpseclib

use phpseclib3\Net\SSH2;

// --- Configuración básica ---
$servidores = [
    'ubuntu' => ['host' => '192.168.1.10', 'usuario' => 'admin', 'clave' => 'clave1', 'nombre' => 'Ubuntu'],
    'debian' => ['host' => '192.168.1.11', 'usuario' => 'admin', 'clave' => 'clave2', 'nombre' => 'Debian'],
    'alpine' => ['host' => '192.168.1.12', 'usuario' => 'admin', 'clave' => 'clave3', 'nombre' => 'Alpine'],
];

// Variables para la conexión SSH y servidor activo
$ssh = null;
$servidor = null;
$servidor_nombre = null;
$mensaje_error = '';

// --- Función para registrar en bitácora ---
function registrarBitacora($accion, $usuario, $detalles = '') {
    $fecha = date('Y-m-d H:i:s');
    $linea = "$fecha | $usuario | $accion | $detalles" . PHP_EOL;
    file_put_contents('bitacora_usuarios.log', $linea, FILE_APPEND);
}

// --- Selección y conexión a servidor ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['conectar'])) {
        // Conectar a servidor personalizado
        $host = $_POST['host'];
        $puerto = $_POST['puerto'];
        $usuario = $_POST['usuario'];
        $clave = $_POST['clave'];
        $ssh = new SSH2($host, $puerto);
        if (!$ssh->login($usuario, $clave)) {
            $_SESSION['error_conexion'] = "Error de conexión con $host";
            $ssh = null;
        } else {
            $_SESSION['servidor_activo'] = ['host'=>$host, 'usuario'=>$usuario, 'puerto'=>$puerto];
            $servidor = $_SESSION['servidor_activo'];
            $servidor_nombre = 'Servidor Personalizado';
        }
    } elseif (isset($_POST['servidor'])) {
        $key = $_POST['servidor'];
        if (isset($servidores[$key])) {
            $servidor = $servidores[$key];
            $servidor_nombre = $servidor['nombre'];
            $ssh = new SSH2($servidor['host'], 22);
            if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
                $_SESSION['error_conexion'] = "Error de conexión con $servidor_nombre";
                $ssh = null;
            } else {
                $_SESSION['servidor_activo'] = $servidor;
            }
        }
    } elseif (isset($_POST['crear_usuario'])) {
        if (!isset($_SESSION['servidor_activo'])) {
            $mensaje_error = "No estás conectado a ningún servidor.";
        } else {
            // Crear usuario en servidor remoto
            $servidor = $_SESSION['servidor_activo'];
            $ssh = new SSH2($servidor['host'], $servidor['puerto'] ?? 22);
            if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
                $mensaje_error = "No se pudo reconectar para crear usuario.";
            } else {
                $nuevoUsuario = escapeshellarg(trim($_POST['nuevoUsuario']));
                $nuevaClave = escapeshellarg(trim($_POST['nuevaClave']));
                $grupo = escapeshellarg(trim($_POST['grupo'] ?? ''));
                $shell = escapeshellarg(trim($_POST['shell'] ?? '/bin/bash'));
                $comandoCrear = "sudo useradd -m -s $shell $nuevoUsuario";
                if ($grupo !== "''" && $grupo !== '') {
                    $comandoCrear .= " -G $grupo";
                }
                $comandoClave = "echo $nuevoUsuario:$nuevaClave | sudo chpasswd";
                // Ejecutar creación
                $outCrear = $ssh->exec($comandoCrear);
                $outClave = $ssh->exec($comandoClave);
                // Forzar cambio de clave
                if (!empty($_POST['forzar_password'])) {
                    $ssh->exec("sudo chage -d 0 $nuevoUsuario");
                }
                // Caducidad usuario
                if (!empty($_POST['caducidad'])) {
                    $fechaCaducidad = escapeshellarg($_POST['caducidad']);
                    $ssh->exec("sudo chage -E $fechaCaducidad $nuevoUsuario");
                }
                registrarBitacora('Crear Usuario', $_SESSION['usuario'], "Usuario: $nuevoUsuario");
                $mensaje_error = "Usuario $nuevoUsuario creado correctamente.";
            }
        }
    } elseif (isset($_POST['accion_usuario'])) {
        // Manejo de acciones (bloquear, desbloquear, eliminar, cambiar password, etc)
        $usuarioAccion = escapeshellarg(trim($_POST['usuario_accion'] ?? ''));
        $accion = $_POST['accion_usuario'];
        if (!isset($_SESSION['servidor_activo'])) {
            $mensaje_error = "No estás conectado a ningún servidor.";
        } else {
            $servidor = $_SESSION['servidor_activo'];
            $ssh = new SSH2($servidor['host'], $servidor['puerto'] ?? 22);
            if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
                $mensaje_error = "No se pudo reconectar para acción usuario.";
            } else {
                switch ($accion) {
                    case 'bloquear_usuario':
                        $ssh->exec("sudo usermod -L $usuarioAccion");
                        registrarBitacora('Bloquear Usuario', $_SESSION['usuario'], $usuarioAccion);
                        $mensaje_error = "Usuario $usuarioAccion bloqueado.";
                        break;
                    case 'desbloquear_usuario':
                        $ssh->exec("sudo usermod -U $usuarioAccion");
                        registrarBitacora('Desbloquear Usuario', $_SESSION['usuario'], $usuarioAccion);
                        $mensaje_error = "Usuario $usuarioAccion desbloqueado.";
                        break;
                    case 'eliminar_usuario':
                        $ssh->exec("sudo deluser --remove-home $usuarioAccion");
                        registrarBitacora('Eliminar Usuario', $_SESSION['usuario'], $usuarioAccion);
                        $mensaje_error = "Usuario $usuarioAccion eliminado.";
                        break;
                    case 'forzar_password':
                        $ssh->exec("sudo chage -d 0 $usuarioAccion");
                        registrarBitacora('Forzar Cambio Contraseña', $_SESSION['usuario'], $usuarioAccion);
                        $mensaje_error = "Cambio de contraseña forzado para $usuarioAccion.";
                        break;
                    case 'expirar_usuario':
                        $fechaCaducidad = escapeshellarg(trim($_POST['fecha_caducidad'] ?? ''));
                        if ($fechaCaducidad) {
                            $ssh->exec("sudo chage -E $fechaCaducidad $usuarioAccion");
                            registrarBitacora('Caducidad Usuario', $_SESSION['usuario'], "$usuarioAccion - $fechaCaducidad");
                            $mensaje_error = "Caducidad establecida para $usuarioAccion.";
                        }
                        break;
                    // Otros casos...
                }
            }
        }
    }
}

// --- Obtener listado usuarios para mostrar (comando) ---
$listadoUsuarios = '';
if ($ssh) {
    $listadoUsuarios = $ssh->exec('cat /etc/passwd');
}

// --- Manejo sesión usuario panel ---
if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = 'admin_panel';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel de Administración - Usuarios</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        h1, h2 { color: #333; }
        form { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
        label { display: block; margin: 8px 0 3px; }
        input[type="text"], input[type="password"], input[type="date"], select { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 3px; }
        button { padding: 8px 15px; border:none; background: #007bff; color:#fff; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
        .actions button { margin-right: 5px; }
        .filtro-busqueda { margin-bottom: 10px; }
    </style>
</head>
<body>

<h1>Panel de Administración Remota - Gestión de Usuarios</h1>

<?php if (!empty($mensaje_error)): ?>
    <p class="success"><?= htmlspecialchars($mensaje_error) ?></p>
<?php endif; ?>

<?php if (isset($_SESSION['error_conexion'])): ?>
    <p class="error"><?= htmlspecialchars($_SESSION['error_conexion']); unset($_SESSION['error_conexion']); ?></p>
<?php endif; ?>

<!-- Selección servidor -->
<form method="POST" id="formSeleccionServidor">
    <h2>Seleccionar Servidor</h2>
    <label for="servidor">Servidores Disponibles:</label>
    <select name="servidor" id="servidor" required>
        <option value="">-- Selecciona servidor --</option>
        <?php foreach ($servidores as $key => $srv): ?>
            <option value="<?= htmlspecialchars($key) ?>"
                <?= (isset($servidor_nombre) && $servidor_nombre === $srv['nombre']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($srv['nombre']) ?> (<?= htmlspecialchars($srv['host']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="seleccionar_servidor">Conectar</button>
</form>

<!-- Formulario conexión personalizada -->
<form method="POST" id="formConexionPersonalizada" style="margin-top:20px;">
    <h2>Conexión a Servidor Personalizado</h2>
    <label for="host">IP / Hostname:</label>
    <input type="text" id="host" name="host" required placeholder="Ejemplo: 192.168.1.100" />
    <label for="puerto">Puerto (por defecto 22):</label>
    <input type="text" id="puerto" name="puerto" placeholder="22" />
    <label for="usuario">Usuario SSH:</label>
    <input type="text" id="usuario" name="usuario" required placeholder="usuario" />
    <label for="clave">Contraseña SSH:</label>
    <input type="password" id="clave" name="clave" required />
    <button type="submit" name="conectar">Conectar</button>
</form>

<?php if ($ssh): ?>
    <hr />

    <!-- Crear nuevo usuario -->
    <form method="POST" id="formCrearUsuario" onsubmit="return validarCrearUsuario()">
        <h2>Crear Nuevo Usuario</h2>
        <label for="nuevoUsuario">Nombre de usuario:</label>
        <input type="text" id="nuevoUsuario" name="nuevoUsuario" required pattern="[a-z0-9_-]{3,16}" title="Solo minúsculas, números, guiones y guion bajo. 3-16 caracteres" />
        <label for="nuevaClave">Contraseña:</label>
        <input type="password" id="nuevaClave" name="nuevaClave" required minlength="6" />
        <label for="grupo">Grupo (opcional):</label>
        <input type="text" id="grupo" name="grupo" placeholder="Ej: sudo, developers" />
        <label for="shell">Shell (por defecto /bin/bash):</label>
        <input type="text" id="shell" name="shell" placeholder="/bin/bash" />
        <label>
            <input type="checkbox" name="forzar_password" /> Forzar cambio de contraseña al primer login
        </label>
        <label for="caducidad">Fecha de caducidad (opcional):</label>
        <input type="date" id="caducidad" name="caducidad" />
        <button type="submit" name="crear_usuario">Crear Usuario</button>
    </form>

    <hr />

    <!-- Buscador y filtros -->
    <div class="filtro-busqueda">
        <label for="buscarUsuario">Buscar usuario:</label>
        <input type="text" id="buscarUsuario" onkeyup="filtrarUsuarios()" placeholder="Buscar por nombre..." />
    </div>

    <!-- Tabla usuarios -->
    <table id="tablaUsuarios">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>UID</th>
                <th>GID</th>
                <th>Home</th>
                <th>Shell</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($listadoUsuarios) {
                $lineas = explode("\n", trim($listadoUsuarios));
                foreach ($lineas as $linea) {
                    if (empty($linea)) continue;
                    // passwd format: user:x:UID:GID:desc:home:shell
                    $partes = explode(':', $linea);
                    $user = htmlspecialchars($partes[0]);
                    $uid = htmlspecialchars($partes[2]);
                    $gid = htmlspecialchars($partes[3]);
                    $home = htmlspecialchars($partes[5]);
                    $shell = htmlspecialchars($partes[6]);

                    // Solo mostrar usuarios con UID >= 1000 para filtrar usuarios del sistema
                    if ($uid < 1000) continue;

                    echo "<tr>";
                    echo "<td>$user</td>";
                    echo "<td>$uid</td>";
                    echo "<td>$gid</td>";
                    echo "<td>$home</td>";
                    echo "<td>$shell</td>";
                    echo "<td class='actions'>
                        <form method='POST' class='formAccionUsuario' onsubmit='return confirmarAccion(event)'>
                            <input type='hidden' name='usuario_accion' value='$user' />
                            <button type='submit' name='accion_usuario' value='bloquear_usuario' title='Bloquear usuario'>🔒</button>
                            <button type='submit' name='accion_usuario' value='desbloquear_usuario' title='Desbloquear usuario'>🔓</button>
                            <button type='submit' name='accion_usuario' value='forzar_password' title='Forzar cambio de contraseña'>🔑</button>
                            <button type='submit' name='accion_usuario' value='eliminar_usuario' title='Eliminar usuario' class='btnEliminar'>🗑️</button>
                        </form>
                    </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No hay usuarios para mostrar o no estás conectado.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <hr />

    <!-- Bitácora de acciones -->
    <h2>Bitácora de acciones</h2>
    <pre style="background:#eee; padding:10px; max-height:200px; overflow-y:auto;">
        <?php
        if (file_exists('bitacora_usuarios.log')) {
            echo htmlspecialchars(file_get_contents('bitacora_usuarios.log'));
        } else {
            echo "No hay registros de bitácora.";
        }
        ?>
    </pre>
<?php endif; ?>

<script src="usuarios.js"></script>
</body>
</html>
