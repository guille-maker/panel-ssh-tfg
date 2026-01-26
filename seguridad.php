<?php
session_start();
require 'conexion.php';
require 'vendor/autoload.php';

use phpseclib3\Net\SSH2;
use PHPMailer\PHPMailer\PHPMailer;

// -------------------- CONFIG --------------------
$config = require 'config.php';

// -------------------- LOGIN CHECK --------------------
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit();
}

// -------------------- CSRF TOKEN --------------------
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function check_csrf($token){
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------- FUNCIONES --------------------
function enviarAlertaCorreo($asunto, $mensaje, $config) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['mail']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['mail']['user'];
        $mail->Password = $config['mail']['pass'];
        $mail->Port = $config['mail']['port'];
        $mail->SMTPSecure = $config['mail']['secure'];

        $mail->setFrom($config['mail']['from'], 'Alerta SSH Panel');
        $mail->addAddress($config['mail']['to']);

        $mail->isHTML(true);
        $mail->Subject = "⚠ ALERTA SSH: " . $asunto;
        $mail->Body = "
            <h3>Evento de seguridad en el Panel SSH</h3>
            <p><strong>Usuario:</strong> {$_SESSION['usuario']}</p>
            <p><strong>IP:</strong> {$_SERVER['REMOTE_ADDR']}</p>
            <p><strong>Hora:</strong> " . date('Y-m-d H:i:s') . "</p>
            <hr>
            <p><strong>Detalles:</strong> {$mensaje}</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar alerta: " . $e->getMessage());
        return false;
    }
}

function exec_ssh($ssh, $cmd) {
    return trim($ssh->exec($cmd)) ?: 'Sin resultados.';
}

$servidores = [
    'ubuntu' => ['host' => '127.0.0.1', 'puerto' => 2222, 'usuario' => 'root', 'clave' => 'root'],
    'debian' => ['host' => '127.0.0.1', 'puerto' => 2223, 'usuario' => 'root', 'clave' => 'root'],
    'alpine' => ['host' => '127.0.0.1', 'puerto' => 2224, 'usuario' => 'root', 'clave' => 'root']
];

// Manejo de conexión personalizada
if (isset($_POST['conectar'])) {
    if (!check_csrf($_POST['csrf_token'])) {
        die("CSRF token inválido.");
    }

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
    if (!check_csrf($_POST['csrf_token'])) {
        die("CSRF token inválido.");
    }

    $_SESSION['conexion_personalizada'] = $servidores[$_POST['servidor']];
    $_SESSION['nombre_servidor'] = ucfirst($_POST['servidor']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Si ya hay conexión activa
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
    } catch (Exception $e) {
        $_SESSION['error_conexion'] = "❌ Error de conexión: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Datos
$auth_log = exec_ssh($ssh, 'grep "Failed password" /var/log/auth.log 2>/dev/null || grep "Failed password" /var/log/secure 2>/dev/null || journalctl _COMM=sshd -n 50 --no-pager 2>/dev/null || echo "No se encontraron registros de acceso fallido."');
$fail2ban_status = exec_ssh($ssh, 'fail2ban-client status sshd 2>/dev/null') ?: 'Fail2ban no está instalado.';
$firewall_rules = exec_ssh($ssh, 'ufw status verbose || iptables -L');
$two_fa_enabled = exec_ssh($ssh, 'test -f /etc/ssh/2fa_enabled && echo "Sí" || echo "No"');
$ssh_config = exec_ssh($ssh, 'cat /etc/ssh/sshd_config 2>/dev/null || echo "No se pudo leer la configuración SSH"');

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!check_csrf($_POST['csrf_token'])) {
        die("CSRF token inválido.");
    }

    if (isset($_POST['ufw_rule'])) {
        $rule = escapeshellarg($_POST['ufw_rule']);
        exec_ssh($ssh, "ufw $rule");
        exec_ssh($ssh, "ufw reload");
        $firewall_rules = exec_ssh($ssh, 'ufw status verbose || iptables -L');

        enviarAlertaCorreo("Cambio en firewall", "Nueva regla UFW: " . htmlspecialchars($_POST['ufw_rule']), $config);
    }

    if (isset($_POST['enable_2fa'])) {
        exec_ssh($ssh, 'touch /etc/ssh/2fa_enabled');
        $two_fa_enabled = 'Sí';
    }

    if (isset($_POST['disable_2fa'])) {
        exec_ssh($ssh, 'rm -f /etc/ssh/2fa_enabled');
        $two_fa_enabled = 'No';
    }

    if (isset($_POST['ssh_config'])) {
        $config_text = escapeshellarg($_POST['ssh_config']);
        exec_ssh($ssh, "echo $config_text > /etc/ssh/sshd_config.tmp && mv /etc/ssh/sshd_config.tmp /etc/ssh/sshd_config");
        exec_ssh($ssh, "service ssh restart");
        $ssh_config = exec_ssh($ssh, 'cat /etc/ssh/sshd_config');

        enviarAlertaCorreo("Configuración SSH modificada", "Se editó el archivo /etc/ssh/sshd_config", $config);
    }

    if (isset($_POST['fail2ban_action'])) {
        $action = $_POST['fail2ban_action'];
        if ($action === 'install') {
            exec_ssh($ssh, 'apt-get install -y fail2ban || apk add fail2ban || yum install -y fail2ban');
        } else {
            exec_ssh($ssh, "fail2ban-client $action sshd");
        }
        $fail2ban_status = exec_ssh($ssh, 'fail2ban-client status sshd 2>/dev/null') ?: 'Fail2ban no está instalado.';
    }
}

// logs web
$log_file = 'logs/accesos_fallidos.log';
$contenido = file_exists($log_file) ? file_get_contents($log_file) : 'No hay registros.';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Seguridad SSH</title>
    <style>
        :root {
            --color-primary: #3a5dfb;
            --color-primary-hover: #5671fc;
            --color-bg-dark: #0e101a;
            --color-bg-card: #1a1f2e;
            --color-bg-sidebar: #1e1e2f;
            --color-text: #d1d9ff;
            --color-text-light: #a0c4ff;
            --color-border: #3f587e;
            --color-success: #71ff71;
            --color-error: #ff6b6b;
            --color-warning: #f39c12;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
            background-color: var(--color-bg-dark);
            color: var(--color-text);
            line-height: 1.6;
        }
        
        /* Barra lateral */
        aside {
            width: 250px;
            background: var(--color-bg-sidebar);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }
        
        .sidebar-logo {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 30px;
            color: var(--color-text-light);
            padding: 0 20px;
        }
        
        nav {
            flex-grow: 1;
            overflow-y: auto;
        }
        
        nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #c0c0c0;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        nav a:hover, nav a:focus {
            background: #2c2c3e;
            color: var(--color-text-light);
            border-left: 4px solid var(--color-primary);
            outline: none;
        }
        
        nav a.active {
            background: #2c2c3e;
            color: var(--color-text-light);
            border-left: 4px solid var(--color-primary);
        }
        
        nav a i {
            margin-right: 12px;
            font-style: normal;
        }
        
        /* Contenido principal */
        main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        h1, h2, h3, h4 {
            color: var(--color-text-light);
            margin-bottom: 1rem;
        }
        
        h1 { font-size: 1.8rem; }
        h2 { font-size: 1.5rem; }
        h3 { font-size: 1.3rem; }
        
        /* Tarjetas de contenido */
        .metric-card {
            background: var(--color-bg-card);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
            border: 1px solid var(--color-border);
        }
        
        /* Formularios */
        form {
            background-color: var(--color-bg-card);
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(90, 133, 255, 0.2);
        }
        
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: var(--color-text-light);
            font-weight: 500;
        }
        
        input[type="text"], 
        input[type="password"], 
        input[type="number"], 
        select,
        textarea {
            background-color: #0e162b;
            color: #ffffff;
            border: 1px solid var(--color-border);
            padding: 12px;
            margin-top: 5px;
            border-radius: 8px;
            width: 100%;
            transition: border 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(58, 93, 251, 0.2);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        input[type="submit"], 
        button {
            background-color: var(--color-primary);
            border: none;
            color: white;
            padding: 12px 20px;
            margin-top: 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        button:hover, 
        input[type="submit"]:hover,
        button:focus {
            background-color: var(--color-primary-hover);
            transform: translateY(-1px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button i {
            margin-right: 8px;
        }
        
        /* Resultados y pre */
        pre {
            background-color: var(--color-bg-card);
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
            white-space: pre-wrap;
            color: var(--color-text);
            border: 1px solid var(--color-border);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 15px 0;
        }
        
        /* Estados */
        .conectado {
            color: var(--color-success);
            font-weight: bold;
        }
        
        .oculto {
            display: none;
        }
        
        .error {
            color: var(--color-error);
            padding: 12px 15px;
            background: #2e0a0a;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--color-error);
        }
        
        .success {
            color: var(--color-success);
            padding: 12px 15px;
            background: #0a2e1a;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--color-success);
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .badge-success {
            background-color: var(--color-success);
            color: #0a2e1a;
        }
        
        .badge-danger {
            background-color: var(--color-error);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--color-warning);
            color: #3a2a00;
        }
        
        .badge-info {
            background-color: var(--color-primary);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            aside {
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>

<aside>
    <div class="sidebar-logo">⚙️ Admin SSH</div>
    <nav>
        <a href="panel.php"><i>🖥️</i>Comandos</a>
        <a href="usuarios.php"><i>👤</i>Usuarios</a>
        <a href="archivos.php"><i>📂</i>Archivos</a>
        <a href="monitor.php"><i>📊</i>Monitorización</a>
        <a href="procesos.php"><i>🔍</i>Procesos</a>
        <a href="logs.php"><i>📄</i>Logs</a>
        <a href="configuracion.php"><i>🔧</i>Configuración</a>
        <a href="seguridad.php" class="active"><i>🔐</i>Seguridad</a>
        <a href="conexion_multiserver.php"><i>🔗</i>Crons y paquetes</a>
    </nav>
</aside>

<main>
    <div class="metric-card">
        <h1>🔐 Seguridad y Accesos</h1>
        <p><i>👤</i> Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong></p>
        
        <?php if ($ssh): ?>
            <p><i>🖧</i> Conectado a: <span class="conectado"><?= $servidor['host'] ?>:<?= $servidor['puerto'] ?> como <?= $servidor['usuario'] ?> (<?= $servidor_nombre ?>)</span></p>
        <?php elseif (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <button onclick="togglePersonalizado()"><i>🔧</i> Mostrar/Ocultar conexión</button>
        
        <form method="post" id="formPersonalizado" class="oculto">
            <h3>🔌 Conectar a servidor personalizado</h3>
            <label>IP o Host:</label>
            <input type="text" name="host" placeholder="192.168.1.100" required>
            
            <label>Puerto:</label>
            <input type="number" name="puerto" value="22" required>
            
            <label>Usuario:</label>
            <input type="text" name="usuario" required>
            
            <label>Contraseña:</label>
            <input type="password" name="clave" required>
            
            <input type="submit" name="conectar" value="Conectar">
        </form>
        
        <form method="post">
            <label for="servidor">🎯 O selecciona un servidor predefinido:</label>
            <select name="servidor" id="servidor">
                <?php foreach ($servidores as $key => $s): ?>
                    <option value="<?= $key ?>" <?= ($servidor['host'] === $s['host'] && $servidor['puerto'] === $s['puerto']) ? 'selected' : '' ?>>
                        <?= ucfirst($key) ?> (<?= $s['host'] ?>:<?= $s['puerto'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Cambiar servidor">
        </form>
    </div>

    <div class="metric-card">
        <h2>🔒 Autenticación en dos pasos (2FA)</h2>
        <p>Estado actual: <strong><?= $two_fa_enabled ?></strong></p>
        
        <form method="post">
            <button type="submit" name="enable_2fa"><i>✅</i> Activar 2FA (simulado)</button>
            <button type="submit" name="disable_2fa"><i>❌</i> Desactivar 2FA</button>
        </form>
        
        <h3>Implementación real con Google Authenticator:</h3>
        <pre>sudo apt install libpam-google-authenticator   # Para Ubuntu/Debian
sudo apk add google-authenticator         # Para Alpine
sudo yum install google-authenticator     # Para CentOS/RHEL

google-authenticator</pre>
        
        <p>Luego configura PAM y SSH:</p>
        <pre># Editar /etc/pam.d/sshd y agregar:
auth required pam_google_authenticator.so

# Editar /etc/ssh/sshd_config y cambiar:
ChallengeResponseAuthentication yes
UsePAM yes</pre>
    </div>

    <div class="metric-card">
        <h2>🔍 Intentos de acceso fallidos</h2>
        <pre><?= htmlspecialchars($auth_log) ?></pre>
        
        <h3>📄 Accesos fallidos a la web</h3>
        <pre><?= htmlspecialchars($contenido) ?></pre>
    </div>

    <div class="metric-card">
        <h2>🛡️ Fail2Ban</h2>
        <pre><?= htmlspecialchars($fail2ban_status) ?></pre>
        
        <form method="post">
            <label>Acciones Fail2Ban:</label>
            <select name="fail2ban_action">
                <option value="install">Instalar Fail2Ban</option>
                <option value="start">Iniciar</option>
                <option value="stop">Detener</option>
                <option value="restart">Reiniciar</option>
                <option value="status">Estado</option>
            </select>
            <button type="submit">Ejecutar</button>
        </form>
        
        <h3>Configuración recomendada:</h3>
        <pre># /etc/fail2ban/jail.local
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600</pre>
    </div>

    <div class="metric-card">
        <h2>🧱 Firewall (UFW/IPtables)</h2>
        <pre><?= htmlspecialchars($firewall_rules) ?></pre>
        
        <form method="post">
            <label>Agregar regla UFW (ej: <code>allow 22/tcp</code> o <code>deny 80</code>):</label>
            <input type="text" name="ufw_rule" required />
            <button type="submit"><i>⚡</i> Aplicar Regla</button>
        </form>
        
        <h3>Comandos útiles:</h3>
        <pre>ufw enable          # Habilitar firewall
ufw disable         # Deshabilitar firewall
ufw default deny    # Denegar todo por defecto
ufw allow 22/tcp    # Permitir SSH
ufw deny 80         # Denegar HTTP</pre>
    </div>

    <div class="metric-card">
        <h2>🔧 Configuración SSH (sshd_config)</h2>
        <form method="post">
            <label>Editar configuración SSH:</label>
            <textarea name="ssh_config" rows="20"><?= htmlspecialchars($ssh_config) ?></textarea>
            <button type="submit"><i>💾</i> Guardar Configuración</button>
        </form>
        
        <h3>Configuración recomendada:</h3>
        <pre>Port 2222                              # Cambiar puerto SSH
PermitRootLogin no                   # Deshabilitar root
PasswordAuthentication no            # Usar solo claves SSH
AllowUsers usuario1 usuario2         # Permitir solo ciertos usuarios
MaxAuthTries 3                       # Intentos máximos
ClientAliveInterval 300              # Desconectar inactivos
ClientAliveCountMax 0</pre>
    </div>

    <div class="metric-card">
        <h2>🚨 Notificaciones por correo</h2>
        <p>Configuración para alertas de seguridad:</p>
        
        <h3>1. Instalar Postfix o msmtp:</h3>
        <pre>sudo apt install postfix mailutils   # Para Ubuntu/Debian
sudo apk add postfix mailx         # Para Alpine</pre>
        
        <h3>2. Ejemplo de script para alertas:</h3>
        <pre>#!/bin/bash
# alert.sh
SUBJECT="Alerta de seguridad en $(hostname)"
TO="admin@example.com"
MESSAGE="Se detectaron intentos de acceso fallidos:\n\n$(grep 'Failed password' /var/log/auth.log | tail -n 5)"

echo -e "$MESSAGE" | mail -s "$SUBJECT" "$TO"</pre>
        
        <h3>3. Agregar a cron para monitoreo:</h3>
        <pre># Ejecutar cada hora
0 * * * * /ruta/al/script/alert.sh</pre>
    </div>
</main>

<script>
// Función para mostrar/ocultar formulario de conexión
function togglePersonalizado() {
    const form = document.getElementById('formPersonalizado');
    form.classList.toggle('oculto');
}

// Manejo de envío de comandos SSH via AJAX
document.getElementById('formComando')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const comando = document.getElementById('inputComando').value;
    const salida = document.getElementById('salida');
    
    salida.textContent = 'Ejecutando comando...';
    
    fetch('ejecutar_comando.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'comando=' + encodeURIComponent(comando)
    })
    .then(response => response.text())
    .then(data => {
        salida.textContent = data;
    })
    .catch(error => {
        salida.textContent = 'Error: ' + error.message;
    });
});
</script>
</body>
</html>