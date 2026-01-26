<?php
session_start();
require 'conexion.php';
require 'conexion_ssh.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios - Panel SSH</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<aside>
    <div class="sidebar-logo">⚙️ Admin SSH</div>
    <nav>
        <a href="panel.php"><i>🖥️</i>Comandos</a>
        <a href="usuarios.php" class="active"><i>👤</i>Usuarios</a>
        <a href="archivos.php"><i>📂</i>Archivos</a>
        <a href="logs.php"><i>📄</i>Logs</a>
        <a href="configuracion.php"><i>🔧</i>Configuración</a>
    </nav>
</aside>

<main>
    <h1>👤 Gestión de Usuarios</h1>

    <!-- Estilos embebidos para formularios -->
    <style>
        <>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            height: 100vh;
            background-color: #eef3fa;
        }

        aside {
            width: 240px;
            background: #1a2a49;
            color: #fff;
            display: flex;
            flex-direction: column;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-logo {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #fff;
        }

        nav {
            flex-grow: 1;
        }

        nav a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: #cbd6ec;
            text-decoration: none;
            transition: 0.2s;
            border-left: 4px solid transparent;
            font-size: 15px;
        }

        nav a i {
            margin-right: 12px;
            font-size: 16px;
        }

        nav a:hover {
            background: #223a63;
            color: #ffffff;
            border-left: 4px solid #3498db;
        }

        nav a.active {
            background: #223a63;
            color: #ffffff;
            border-left: 4px solid #3498db;
        }

        main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        h1 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #3498db;
            color: white;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: #f1f1f1;
        }
        section {
            background: #ffffff;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        section h2 {
            margin-top: 0;
            font-size: 1.2rem;
            color: #333;
        }
        form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        input[type="text"], input[type="password"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            flex: 1;
            min-width: 200px;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        button:hover {
            background-color: #2980b9;
        }
        pre {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>

    <section>
        <h2>➕ Crear Usuario</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="crear_usuario">
            <input type="text" name="usuario" placeholder="Nombre de usuario" required>
            <input type="password" name="clave" placeholder="Contraseña" required>
            <button type="submit">Crear</button>
        </form>
    </section>

    <section>
        <h2>❌ Eliminar Usuario</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="eliminar_usuario">
            <input type="text" name="usuario" placeholder="Nombre de usuario" required>
            <button type="submit">Eliminar</button>
        </form>
    </section>

    <section>
        <h2>🔐 Cambiar Contraseña</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="cambiar_password">
            <input type="text" name="usuario" placeholder="Nombre de usuario" required>
            <input type="password" name="clave" placeholder="Nueva contraseña" required>
            <button type="submit">Cambiar</button>
        </form>
    </section>

    <section>
        <h2>🟢 Usuarios Activos</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="ver_usuarios_activos">
            <button type="submit">Ver Usuarios Activos</button>
        </form>
        <?php if ($_POST['accion'] ?? '' === 'ver_usuarios_activos') {
            echo "<pre>" . htmlspecialchars($ssh->exec('who')) . "</pre>";
        } ?>
    </section>

    <section>
        <h2>📋 Lista de Usuarios del Sistema</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="ver_lista_usuarios">
            <button type="submit">Mostrar Usuarios</button>
        </form>
        <?php if ($_POST['accion'] ?? '' === 'ver_lista_usuarios') {
            echo "<pre>" . htmlspecialchars($ssh->exec("cut -d: -f1 /etc/passwd")) . "</pre>";
        } ?>
    </section>

    <section>
        <h2>👥 Grupos del Sistema</h2>
        <form method="POST">
            <input type="hidden" name="accion" value="ver_grupos">
            <button type="submit">Mostrar Grupos</button>
        </form>
        <?php if ($_POST['accion'] ?? '' === 'ver_grupos') {
            echo "<pre>" . htmlspecialchars($ssh->exec("cut -d: -f1 /etc/group")) . "</pre>";
        } ?>
    </section>
</main>
</body>
</html>
