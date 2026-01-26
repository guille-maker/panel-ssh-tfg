<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$servidor_id = $_POST['servidor_id'] ?? null; // si lo envías por fetch/AJAX
// Obtener lista de servidores
$servidores = $pdo->query("SELECT id, nombre FROM servidores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Capturar servidor seleccionado
$servidorSeleccionado = isset($_GET['servidor_id']) ? intval($_GET['servidor_id']) : 0;

// Obtener logs filtrados (si se ha seleccionado un servidor)
if ($servidorSeleccionado > 0) {
    $stmt = $pdo->prepare("INSERT INTO logs (usuario, comando, fecha, servidor_id) VALUES (?, ?, NOW(), ?)");
$stmt->execute([$usuario, $comando, $servidor_id]);
} else {
    // Obtener todos los logs si no hay filtro
    $stmt = $pdo->query("SELECT logs.id, logs.usuario, logs.comando, logs.fecha, servidores.nombre AS servidor
                         FROM logs 
                         LEFT JOIN servidores ON logs.servidor_id = servidores.id
                         ORDER BY logs.fecha DESC");
}

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Logs - Panel SSH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; display: flex; height: 100vh; background-color: #eef3fa; }
        aside { width: 240px; background: #1a2a49; color: #fff; display: flex; flex-direction: column; padding-top: 20px; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); }
        .sidebar-logo { text-align: center; font-size: 22px; font-weight: bold; margin-bottom: 30px; color: #fff; }
        nav { flex-grow: 1; }
        nav a { display: flex; align-items: center; padding: 14px 20px; color: #cbd6ec; text-decoration: none; transition: 0.2s; border-left: 4px solid transparent; font-size: 15px; }
        nav a i { margin-right: 12px; font-size: 16px; }
        nav a:hover { background: #223a63; color: #ffffff; border-left: 4px solid #3498db; }
        nav a.active { background: #223a63; color: #ffffff; border-left: 4px solid #3498db; }
        main { flex: 1; padding: 30px; overflow-y: auto; }
        h1 { color: #2c3e50; margin-top: 0; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #3498db; color: white; position: sticky; top: 0; }
        tr:hover { background-color: #f1f1f1; }
        form { margin-bottom: 20px; }
        select, button { padding: 8px; font-size: 14px; }
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
        body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background-color: #0e101a;
    color: #d1d9ff;
    display: flex;
    height: 100vh;
}

aside {
    width: 230px;
    background: #12172a;
    color: #fff;
    display: flex;
    flex-direction: column;
    padding-top: 20px;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
}

.sidebar-logo {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 30px;
    color: #a0b4ff;
}

nav a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #a8b2d1;
    text-decoration: none;
    border-left: 4px solid transparent;
    transition: 0.3s;
}

nav a:hover, nav a.active {
    background: #1d2238;
    color: #ffffff;
    border-left: 4px solid #3a5dfb;
}

nav a i {
    margin-right: 10px;
}

main {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
    background-color: #0e101a;
}

h1 {
    color: #8fb1ff;
    margin-bottom: 20px;
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

select, input[type="text"], input[type="password"], input[type="number"] {
    background-color: #0e162b;
    color: #ffffff;
    border: 1px solid #3f587e;
    padding: 8px;
    margin-top: 5px;
    border-radius: 8px;
    width: 100%;
}

button, input[type="submit"] {
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

table {
    width: 100%;
    border-collapse: collapse;
    background: #1a1f2e;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    color: #d1d9ff;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #2e3c5e;
}

th {
    background-color: #2c3f74;
    color: #d1e3ff;
    position: sticky;
    top: 0;
}

tr:hover {
    background-color: #2a2f45;
}

pre {
    background-color: #1a1f2e;
    padding: 10px;
    border-radius: 10px;
    white-space: pre-wrap;
}

a {
    color: #4f8bff;
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

<aside>
    <div class="sidebar-logo">🛠 Configuración</div>
    <nav>
    <a href="panel.php"><i>🖥️</i>Comandos</a>
        <a href="usuarios.php"><i>👤</i>Usuarios</a>
        <a href="archivos.php"><i>📂</i>Archivos</a>
        <a href="monitor.php"><i>📊</i>Monitorización</a>
        <a href="procesos.php">🔍 Procesos</a>
        <a href="logs.php" class="active"><i>📄</i>Logs</a>
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
      body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background-color: #0e101a;
    color: #d1d9ff;
    display: flex;
    height: 100vh;
}

aside {
    width: 230px;
    background: #12172a;
    color: #fff;
    display: flex;
    flex-direction: column;
    padding-top: 20px;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
}

.sidebar-logo {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 30px;
    color: #a0b4ff;
}

nav a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #a8b2d1;
    text-decoration: none;
    border-left: 4px solid transparent;
    transition: 0.3s;
}

nav a:hover, nav a.active {
    background: #1d2238;
    color: #ffffff;
    border-left: 4px solid #3a5dfb;
}

nav a i {
    margin-right: 10px;
}

main {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
    background-color: #0e101a;
}

h1 {
    color: #8fb1ff;
    margin-bottom: 20px;
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

select, input[type="text"], input[type="password"], input[type="number"] {
    background-color: #0e162b;
    color: #ffffff;
    border: 1px solid #3f587e;
    padding: 8px;
    margin-top: 5px;
    border-radius: 8px;
    width: 100%;
}

button, input[type="submit"] {
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

table {
    width: 100%;
    border-collapse: collapse;
    background: #1a1f2e;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    color: #d1d9ff;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #2e3c5e;
}

th {
    background-color: #2c3f74;
    color: #d1e3ff;
    position: sticky;
    top: 0;
}

tr:hover {
    background-color: #2a2f45;
}

pre {
    background-color: #1a1f2e;
    padding: 10px;
    border-radius: 10px;
    white-space: pre-wrap;
}

a {
    color: #4f8bff;
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
    <h1><i class="fas fa-file-alt" color="white"></i> Registro de Comandos Ejecutados</h1>

    <form method="GET" action="logs.php">
        <label for="servidor_id">Filtrar por máquina:</label>
        <select name="servidor_id" id="servidor_id">
            <option value="0">-- Todas las máquinas --</option>
            <?php foreach ($servidores as $servidor): ?>
                <option value="<?= $servidor['id'] ?>" <?= $servidor['id'] == $servidorSeleccionado ? 'selected' : '' ?>>
                    <?= htmlspecialchars($servidor['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <?php if (empty($logs)): ?>
        <p>No hay logs registrados aún.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Comando</th>
                    <th>Fecha</th>
                    <th>Servidor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['id']) ?></td>
                        <td><?= htmlspecialchars($log['usuario']) ?></td>
                        <td><?= htmlspecialchars($log['comando']) ?></td>
                        <td><?= htmlspecialchars($log['fecha']) ?></td>
                        <td><?= htmlspecialchars($log['servidor'] ?? 'N/D') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

</body>
</html>
