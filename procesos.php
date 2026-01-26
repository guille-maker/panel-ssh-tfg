<?php
session_start();
require 'conexion.php';
require 'conexion_ssh.php';
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

function exec_ssh($ssh, $cmd) {
    $output = $ssh->exec($cmd);
    return trim($output) ?: 'No disponible';
}

function registrar_historial($mensaje) {
    $usuario = $_SESSION['usuario'] ?? 'desconocido';
    $fecha = date("Y-m-d H:i:s");
    file_put_contents("historial_procesos.log", "[$fecha][$usuario] $mensaje\n", FILE_APPEND);
}

$mensaje = '';
$output_cmd = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Matar por PID
    if (isset($_POST['kill_pid'])) {
        $pid = intval($_POST['kill_pid']);
        if ($pid <= 10 || in_array($pid, [1])) {
            $mensaje = "❌ Proceso crítico no puede ser terminado (PID $pid).";
        } else {
            $resultado = exec_ssh($ssh, "kill $pid 2>&1");
            $mensaje = "✔️ Intentando matar PID $pid: $resultado";
            registrar_historial("Matar PID $pid: $resultado");
        }
    }

    // Ejecutar comando
    if (isset($_POST['comando'])) {
        $cmd = $_POST['comando'];
        $output_cmd = exec_ssh($ssh, "$cmd 2>&1");
        registrar_historial("Comando ejecutado: $cmd");
    }

    // Renice
    if (isset($_POST['renice_pid'], $_POST['renice_value'])) {
        $pid = intval($_POST['renice_pid']);
        $nice = intval($_POST['renice_value']);
        $resultado = exec_ssh($ssh, "renice $nice -p $pid 2>&1");
        $mensaje = "🔄 Cambiando prioridad del PID $pid: $resultado";
        registrar_historial("Renice PID $pid a $nice: $resultado");
    }

    // Kill por nombre
    if (isset($_POST['kill_name'])) {
        $nombre = escapeshellarg($_POST['kill_name']);
        if (preg_match('/(sshd|init|systemd)/i', $nombre)) {
            $mensaje = "❌ Proceso crítico no puede ser terminado.";
        } else {
            $resultado = exec_ssh($ssh, "pkill $nombre 2>&1");
            $mensaje = "✔️ Intentando matar procesos con nombre $nombre: $resultado";
            registrar_historial("Matar procesos por nombre $nombre: $resultado");
        }
    }

    // Señal personalizada
    if (isset($_POST['signal_pid'], $_POST['signal_type'])) {
        $pid = intval($_POST['signal_pid']);
        $signal = strtoupper($_POST['signal_type']);
        $resultado = exec_ssh($ssh, "kill -s $signal $pid 2>&1");
        $mensaje = "📣 Enviando señal $signal al PID $pid: $resultado";
        registrar_historial("Señal $signal enviada a PID $pid: $resultado");
    }
}

// Filtros
$filtro = $_GET['filtro'] ?? '';
$estado = $_GET['estado'] ?? '';
$cmd_procesos = "ps aux";
if ($filtro) {
    $cmd_procesos .= " | grep -i \"$filtro\"";
}
if ($estado) {
    $cmd_procesos .= " | awk '\$8 ~ /^$estado\$/'";
}
$cmd_procesos .= " | grep -v grep";
$procesos = exec_ssh($ssh, $cmd_procesos);
$arbol = exec_ssh($ssh, "pstree -p") ?: 'pstree no disponible';
$top_cpu = exec_ssh($ssh, "ps -eo pid,comm,%cpu --sort=-%cpu | head -n 11");
$top_mem = exec_ssh($ssh, "ps -eo pid,comm,%mem --sort=-%mem | head -n 11");

$detalles_pid = '';
if (isset($_GET['ver_pid'])) {
    $pid = intval($_GET['ver_pid']);
    $detalles_pid = exec_ssh($ssh, "ps -p $pid -o pid,ppid,comm,%cpu,%mem,etime,state,user");
}
?>

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
        <a href="archivos.php"><i>📂</i>Archivos</a>
        <a href="monitor.php"><i>📊</i>Monitorización</a>
        <a href="procesos.php" class="active">🔍 Procesos</a>
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
<main>
    <h1>🔍 Gestión de Procesos</h1>

    <?php if ($mensaje): ?>
        <div class="msg"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (!empty($output_cmd)): ?>
        <h2>📤 Resultado del comando:</h2>
        <pre><?= htmlspecialchars($output_cmd) ?></pre>
    <?php endif; ?>

    <h2>📝 Ejecutar Comando</h2>
    <form method="POST">
        <input type="text" name="comando" placeholder="Ej: htop, top, ls -la" required />
        <button type="submit">Ejecutar</button>
    </form>

    <h2>🔍 Buscar Procesos</h2>
    <form method="GET">
        <input type="text" name="filtro" placeholder="Ej: ssh, apache" value="<?= htmlspecialchars($filtro) ?>" />
        <select name="estado">
            <option value="">-- Estado --</option>
            <option value="R" <?= $estado === 'R' ? 'selected' : '' ?>>Running (R)</option>
            <option value="S" <?= $estado === 'S' ? 'selected' : '' ?>>Sleeping (S)</option>
            <option value="Z" <?= $estado === 'Z' ? 'selected' : '' ?>>Zombie (Z)</option>
            <option value="T" <?= $estado === 'T' ? 'selected' : '' ?>>Stopped (T)</option>
        </select>
        <button type="submit">Buscar</button>
    </form>

    <h2>📋 Lista de Procesos</h2>
    <pre><?= htmlspecialchars($procesos) ?></pre>

    <h2>💀 Matar proceso por PID</h2>
    <form method="POST">
        <input type="number" name="kill_pid" placeholder="PID" required />
        <button type="submit">Matar</button>
    </form>

    <h2>🔠 Matar proceso por nombre</h2>
    <form method="POST">
        <input type="text" name="kill_name" placeholder="Ej: apache2" required />
        <button type="submit">Matar</button>
    </form>

    <h2>⚙️ Cambiar prioridad (renice)</h2>
    <form method="POST">
        <input type="number" name="renice_pid" placeholder="PID" required />
        <input type="number" name="renice_value" placeholder="Prioridad (-20 a 19)" required />
        <button type="submit">Cambiar Prioridad</button>
    </form>

    <h2>📣 Enviar señal personalizada</h2>
    <form method="POST">
        <input type="number" name="signal_pid" placeholder="PID" required />
        <input type="text" name="signal_type" placeholder="Ej: SIGTERM, SIGKILL" required />
        <button type="submit">Enviar Señal</button>
    </form>

    <h2>📈 Top 10 por uso de CPU</h2>
    <pre><?= htmlspecialchars($top_cpu) ?></pre>

    <h2>📉 Top 10 por uso de Memoria</h2>
    <pre><?= htmlspecialchars($top_mem) ?></pre>

    <h2>🔎 Ver detalles de un PID</h2>
    <form method="GET">
        <input type="number" name="ver_pid" placeholder="PID" required />
        <button type="submit">Ver Detalles</button>
    </form>

    <?php if ($detalles_pid): ?>
        <h2>📄 Detalles del Proceso</h2>
        <pre><?= htmlspecialchars($detalles_pid) ?></pre>
    <?php endif; ?>

    <h2>🌳 Árbol de Procesos (pstree)</h2>
    <pre><?= htmlspecialchars($arbol) ?></pre>
</main>
</body>
</html>
