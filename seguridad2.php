<?php
session_start();
require 'conexion.php';
require 'conexion_ssh.php';
require 'vendor/autoload.php';

use phpseclib3\Net\SSH2;

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

function exec_ssh($ssh, $cmd) {
    $output = $ssh->exec($cmd);
    return $output ?: 'No disponible';
}

// Inicializa SSH
$ssh = new SSH2('127.0.0.1', 2222); // Cambiar por tu servidor
$ssh->login('root', 'root');       // Cambiar por credenciales

// Seguridad: info básica
$ufw_status = exec_ssh($ssh, 'ufw status verbose');
$auth_log = exec_ssh($ssh, 'tail -n 30 /var/log/auth.log');
$fail2ban = exec_ssh($ssh, 'fail2ban-client status 2>/dev/null') ?: 'fail2ban no está instalado';
$two_fa_enabled = exec_ssh($ssh, 'test -f /etc/ssh/2fa_enabled && echo "Sí" || echo "No"');

// Formulario de gestión UFW
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ufw_rule'])) {
        $rule = escapeshellarg($_POST['ufw_rule']);
        $ufw_output = exec_ssh($ssh, "ufw $rule");
        $ssh->exec("ufw reload");
    }

    if (isset($_POST['enable_2fa'])) {
        $ssh->exec('touch /etc/ssh/2fa_enabled'); // Simulación
        $two_fa_enabled = 'Sí';
    }

    if (isset($_POST['disable_2fa'])) {
        $ssh->exec('rm -f /etc/ssh/2fa_enabled'); // Simulación
        $two_fa_enabled = 'No';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🔐 Seguridad y Accesos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        pre { background: #eee; padding: 10px; border-radius: 5px; }
        form { margin-top: 20px; }
        label, button { display: block; margin: 5px 0; }
        button { padding: 6px 12px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔐 Seguridad y Accesos</h1>

    <h2>🧱 Reglas de Firewall (UFW)</h2>
    <pre><?= htmlspecialchars($ufw_status) ?></pre>
    <form method="post">
        <label>Agregar regla UFW (ej: allow 22/tcp):</label>
        <input type="text" name="ufw_rule" required>
        <button type="submit">Aplicar Regla</button>
    </form>

    <h2>🔒 Autenticación en Dos Pasos (simulada)</h2>
    <p>2FA Activado: <strong><?= htmlspecialchars($two_fa_enabled) ?></strong></p>
    <form method="post">
        <button name="enable_2fa">Activar 2FA</button>
        <button name="disable_2fa">Desactivar 2FA</button>
    </form>

    <h2>🔍 Intentos de Inicio de Sesión Fallidos (auth.log)</h2>
    <pre><?= htmlspecialchars($auth_log) ?></pre>

    <h2>📛 Estado de fail2ban</h2>
    <pre><?= htmlspecialchars($fail2ban) ?></pre>

    <h2>🚨 Notificaciones (simulación)</h2>
    <p>Para notificaciones reales, puedes usar <code>mail()</code> o PHPMailer para eventos críticos como accesos SSH, cambios de usuarios, reglas UFW, etc.</p>
</body>
</html>
