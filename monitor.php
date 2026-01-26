<?php
session_start();
require 'conexion.php';
require 'conexion_ssh.php';

function exec_ssh($ssh, $cmd) {
    try {
        $output = $ssh->exec($cmd);
        if (stripos($output, 'command not found') !== false || 
            stripos($output, 'No such file or directory') !== false) {
            return false;
        }
        return trim($output);
    } catch (Exception $e) {
        return false;
    }
}

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
    if (array_key_exists($_POST['servidor'], $servidores)) {
        $_SESSION['conexion_personalizada'] = $servidores[$_POST['servidor']];
        $_SESSION['nombre_servidor'] = ucfirst($_POST['servidor']);
        if (isset($ssh)) $ssh->disconnect();
        if (isset($sftp)) $sftp->disconnect();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['error_conexion'] = "Servidor no válido";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Conectar al servidor seleccionado
if (isset($_SESSION['conexion_personalizada'])) {
    $servidor = $_SESSION['conexion_personalizada'];
    try {
        $ssh = new SSH2($servidor['host'], $servidor['puerto']);
        if (!$ssh->login($servidor['usuario'], $servidor['clave'])) {
            $_SESSION['error_conexion'] = "Error de autenticación SSH";
            unset($ssh);
        }
    } catch (Exception $e) {
        $_SESSION['error_conexion'] = "Error al conectar: " . $e->getMessage();
    }
} else {
    $servidor = $servidores['ubuntu'];
    $_SESSION['nombre_servidor'] = 'Ubuntu';
}

// Detectar distribución
$distro = exec_ssh($ssh, "grep -E '^ID=' /etc/os-release | cut -d= -f2 | tr -d '\"'") ?: 'unknown';

// ===== MÉTRICAS DEL SISTEMA (ADAPTADAS POR DISTRO) =====

// Uso CPU
$cpu_load = exec_ssh($ssh, "uptime | grep -o 'load average: .*'") ?: 'No disponible';
$cpu_usage = exec_ssh($ssh, "top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - \$1}'") ?: 'No disponible';

// Memoria y swap
$mem = exec_ssh($ssh, "free -m | awk 'NR==2{printf \"%s/%s MB (%.2f%%)\", \$3,\$2,\$3*100/\$2 }'") ?: 'No disponible';
$swap = exec_ssh($ssh, "free -m | awk 'NR==3{printf \"%s/%s MB (%.2f%%)\", \$3,\$2,\$3*100/\$2 }'") ?: 'No disponible';
$vmstat = exec_ssh($ssh, "vmstat 1 2 | tail -1") ?: 'No disponible';

// Espacio disco
$disk_root = exec_ssh($ssh, "df -h / | awk 'NR==2{print \$3 \" usados de \" \$2 \" (\" \$5 \")\"}'") ?: 'No disponible';
$disk_home = exec_ssh($ssh, "df -h /home | awk 'NR==2{print \$3 \" usados de \" \$2 \" (\" \$5 \")\"}'") ?: 'No disponible';
$disk_du = exec_ssh($ssh, "du -sh /home/* 2>/dev/null") ?: 'No disponible';

// Procesos
$top_processes = exec_ssh($ssh, "ps aux --sort=-%cpu | head -n 6") ?: 'No disponible';

// Servicios (adaptado por distro)
if ($distro == 'alpine') {
    $services = ['sshd', 'nginx', 'mysqld', 'apache2', 'crond'];
    $services_status = [];
    foreach($services as $service) {
        $status = exec_ssh($ssh, "rc-status | grep '$service' | awk '{print \$2}'");
        $services_status[$service] = ($status && trim($status) === 'started') ? 'activo' : 'inactivo';
    }
} else {
    // Para Debian/Ubuntu
    $services = ['ssh', 'nginx', 'mysql', 'apache2', 'cron'];
    $services_status = [];
    foreach($services as $service) {
        $status = exec_ssh($ssh, "systemctl is-active $service 2>/dev/null");
        if ($status === false) {
            $pgrep = exec_ssh($ssh, "pgrep -x '$service'");
            $services_status[$service] = $pgrep ? 'activo' : 'inactivo';
        } else {
            $services_status[$service] = $status === 'active' ? 'activo' : 'inactivo';
        }
    }
}

// Estado red
$ip_addr = exec_ssh($ssh, "ip a 2>/dev/null | grep 'inet ' | grep -v '127.0.0.1' | awk '{print \$2}'");
if ($ip_addr === false) {
    $ip_addr = exec_ssh($ssh, "ifconfig 2>/dev/null | grep 'inet ' | grep -v '127.0.0.1' | awk '{print \$2}'") ?: 'No disponible';
}

// Puertos abiertos
$netstat = exec_ssh($ssh, "ss -tuln 2>/dev/null | head -20");
if ($netstat === false) {
    $netstat = exec_ssh($ssh, "netstat -tuln 2>/dev/null | head -20") ?: 'No disponible';
}

// Ping
$ping = exec_ssh($ssh, "ping -c 3 8.8.8.8 2>/dev/null | tail -6");
if ($ping === false || trim($ping) === '') {
    $ping = "No se pudo ejecutar ping. Razones posibles:\n"
          . "1. El comando ping no está instalado\n"
          . "2. No hay conectividad de red\n"
          . "3. El firewall bloquea ICMP";
}

// Traceroute
$traceroute = exec_ssh($ssh, "traceroute -m 5 8.8.8.8 2>/dev/null | head -7") ?: 'No disponible';

// Hardware info (adaptado por distro)
if ($distro == 'alpine') {
    $cpu_info = exec_ssh($ssh, "cat /proc/cpuinfo | grep 'model name' | head -1") ?: 'No disponible (instale lscpu)';
    $memory_info = exec_ssh($ssh, "cat /proc/meminfo | head -5") ?: 'No disponible';
    $disk_info = exec_ssh($ssh, "df -h") ?: 'No disponible';
    $cpu_temp = exec_ssh($ssh, "cat /sys/class/thermal/thermal_zone*/temp 2>/dev/null | awk '{print \$1/1000\"°C\"}'") ?: 'No disponible';
} else {
    // Para Debian/Ubuntu
    $cpu_info = exec_ssh($ssh, "lscpu 2>/dev/null") ?: 'No disponible';
    $memory_info = exec_ssh($ssh, "free -h 2>/dev/null") ?: 'No disponible';
    $disk_info = exec_ssh($ssh, "lsblk 2>/dev/null") ?: 'No disponible';
    $cpu_temp = exec_ssh($ssh, "sensors 2>/dev/null | grep 'Core' | awk '{print \$3}' | head -1") ?: 'No disponible';
}

// Uptime
$uptime = exec_ssh($ssh, "uptime -p 2>/dev/null") ?: exec_ssh($ssh, "uptime 2>/dev/null") ?: 'No disponible';

// Métricas adicionales
$gpu_usage = exec_ssh($ssh, "nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits 2>/dev/null | head -1") ?: 'GPU no detectada';
$network_usage = exec_ssh($ssh, "ip -s -h link 2>/dev/null | grep -A 1 'eth0\\|ens3\\|wlan0'") ?: 'Datos de red no disponibles';

// Usuarios conectados
$logged_users = exec_ssh($ssh, "who 2>/dev/null | wc -l");
if ($logged_users === false) {
    $logged_users = exec_ssh($ssh, "users 2>/dev/null | wc -w") ?: 'No disponible';
}

// Load average
$load_avg = exec_ssh($ssh, "cat /proc/loadavg 2>/dev/null | awk '{print \$1,\$2,\$3}'");
if ($load_avg === false) {
    $load_avg = exec_ssh($ssh, "uptime 2>/dev/null | grep -o 'load average: .*' | cut -d: -f2") ?: 'No disponible';
}

// Memoria detallada
$memory_details = exec_ssh($ssh, "free -h 2>/dev/null | grep 'Mem:' | awk '{print \"Total: \" \$2 \", Usada: \" \$3 \", Libre: \" \$4 \", Caché: \" \$6}'") ?: 'No disponible';

// Procesos zombie
$zombies = exec_ssh($ssh, "ps aux 2>/dev/null | awk '\$8~/Z/ {print \$0}' | wc -l");
if ($zombies === false || $zombies === '') {
    $zombies = exec_ssh($ssh, "top -bn1 2>/dev/null | grep -i zombie | awk '{print \$10}'") ?: '0';
}

// Uso de inodes
$inode_usage = exec_ssh($ssh, "df -i / 2>/dev/null | awk 'NR==2{print \"Usados: \" \$3 \"/\" \$2 \" (\" \$5 \")\"}'") ?: 'No disponible';

$servidor_nombre = $_SESSION['nombre_servidor'] ?? 'Ubuntu';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Monitorización Completa - Panel SSH</title>
<style>
    * {
        box-sizing: border-box;
    }
    body {
        margin: 0;
        font-family: 'Segoe UI', sans-serif;
        display: flex;
        height: 100vh;
        background-color: #0e101a;
        color: #d1d9ff;
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
        color: #a0c4ff;
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
        color: #a0c4ff;
        border-left: 4px solid #3498db;
    }
    nav a.active {
        background: #2c2c3e;
        color: #a0c4ff;
        border-left: 4px solid #3498db;
    }
    nav a i {
        margin-right: 10px;
    }
    main {
        flex: 1;
        padding: 30px;
        overflow-y: auto;
        background-color: #0e101a;
        color: #d1d9ff;
    }
    h1, h2, h3 {
        color: #a0c4ff;
        margin-top: 0;
    }
    pre {
        background-color: #1a1f2e;
        padding: 15px;
        border-radius: 10px;
        overflow-x: auto;
        color: #d1d9ff;
        border: 1px solid #3f587e;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.4;
    }
    .conectado {
        color: #71ff71;
        font-weight: bold;
    }
    .oculto {
        display: none;
    }
    form {
        background-color: #1a1f2e;
        padding: 20px;
        margin-bottom: 25px;
        border-radius: 12px;
        box-shadow: 0 0 15px rgba(90, 133, 255, 0.3);
        color: #d1d9ff;
    }
    label {
        display: block;
        margin-top: 15px;
        margin-bottom: 5px;
        color: #cbd5ff;
        font-weight: bold;
    }
    input[type="text"], 
    input[type="password"], 
    input[type="number"], 
    select {
        background-color: #0e162b;
        color: #ffffff;
        border: 1px solid #3f587e;
        padding: 10px;
        margin-top: 5px;
        border-radius: 8px;
        width: 100%;
        font-size: 14px;
    }
    input[type="submit"], 
    button {
        background-color: #3a5dfb;
        border: none;
        color: white;
        padding: 12px 20px;
        margin-top: 15px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        transition: background-color 0.3s;
    }
    button:hover, 
    input[type="submit"]:hover {
        background-color: #5671fc;
    }
    .status-green {
        color: #2ecc71;
        font-weight: bold;
    }
    .status-red {
        color: #e74c3c;
        font-weight: bold;
    }
    .error {
        color: #ff6b6b;
        padding: 12px;
        background: #2e0a0a;
        border-radius: 8px;
        margin: 10px 0;
        border-left: 4px solid #e74c3c;
    }
    .metric-card {
        background: #1a1f2e;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        border: 1px solid #3f587e;
    }
    .metric-title {
        color: #a0c4ff;
        border-bottom: 2px solid #3f587e;
        padding-bottom: 12px;
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 18px;
    }
    .metric-value {
        font-size: 16px;
        margin-bottom: 10px;
        padding: 8px;
        background: #0e162b;
        border-radius: 6px;
    }
    .metric-label {
        font-weight: bold;
        color: #cbd5ff;
        margin-right: 5px;
    }
    .distro-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        margin-left: 10px;
    }
    .debian-badge {
        background-color: #d70751;
        color: white;
    }
    .alpine-badge {
        background-color: #0d597f;
        color: white;
    }
    .ubuntu-badge {
        background-color: #dd4814;
        color: white;
    }
    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .service-item {
        background: #0e162b;
        padding: 12px;
        border-radius: 8px;
        border-left: 4px solid #3f587e;
    }
    .service-name {
        font-weight: bold;
        margin-bottom: 5px;
    }
    .refresh-info {
        color: #a0c4ff;
        font-size: 12px;
        text-align: right;
        margin-top: 10px;
        font-style: italic;
    }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>

<aside>
    <div class="sidebar-logo">⚙️ Admin SSH</div>
    <nav>
        <a href="panel.php"><i>🖥️</i>Comandos</a>
        <a href="usuarios.php"><i>👤</i>Usuarios</a>
        <a href="archivos.php"><i>📂</i>Archivos</a>
        <a href="monitor.php" class="active"><i>📊</i>Monitorización</a>
        <a href="procesos.php">🔍 Procesos</a>
        <a href="logs.php"><i>📄</i>Logs</a>
        <a href="configuracion.php"><i>🔧</i>Configuración</a>
        <a href="seguridad.php">🔐 Seguridad</a>
        <a href="conexion_multiserver.php"><i>🔗</i>Crons y paquetes</a>
    </nav>
</aside>

<main>
    <div id="server-status" class="metric-card">
        <h2 class="metric-title">Panel de administración remota 
            <span class="distro-badge <?= $distro ?>-badge">
                <?= strtoupper($distro) ?>
            </span>
        </h2>
        <div class="metric-value">
            <span class="metric-label">👤 Usuario:</span>
            <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
        </div>
        <?php if (isset($ssh) && $ssh->isConnected()): ?>
        <div class="metric-value">
            <span class="metric-label">🖧 Conexión:</span>
            <span class="conectado"><?= htmlspecialchars($servidor['host']) ?>:<?= htmlspecialchars($servidor['puerto']) ?> 
            como <?= htmlspecialchars($servidor['usuario']) ?></span>
        </div>
        <?php elseif (isset($_SESSION['error_conexion'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error_conexion']) ?></div>
        <?php unset($_SESSION['error_conexion']); ?>
        <?php endif; ?>
    </div>

    <button onclick="togglePersonalizado()" style="margin-bottom: 20px;">
        <i class="fas fa-cog"></i> Mostrar/Ocultar conexión personalizada
    </button>

    <form method="post" id="formPersonalizado" class="oculto">
        <h3><i class="fas fa-server"></i> Conectar a servidor personalizado</h3>
        <label><i class="fas fa-network-wired"></i> IP o Host:</label>
        <input type="text" name="host" placeholder="192.168.1.100" required>

        <label><i class="fas fa-plug"></i> Puerto (por defecto 22 o 2222):</label>
        <input type="number" name="puerto" value="2222" required>

        <label><i class="fas fa-user"></i> Usuario:</label>
        <input type="text" name="usuario" required>

        <label><i class="fas fa-key"></i> Contraseña:</label>
        <input type="password" name="clave" required>

        <input type="submit" name="conectar" value="Conectar">
    </form>

    <form method="post">
        <label for="servidor"><i class="fas fa-list"></i> Selecciona un servidor predefinido:</label>
        <select name="servidor" id="servidor">
            <option value="ubuntu">Ubuntu</option>
            <option value="debian">Debian</option>
            <option value="alpine">Alpine</option>
        </select>
        <input type="submit" value="Cambiar servidor">
    </form>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-clock"></i> Estado del Sistema</h2>
        <div class="metric-value">
            <span class="metric-label">⏱️ Uptime:</span>
            <span id="uptime"><?= htmlspecialchars($uptime) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">👥 Usuarios conectados:</span>
            <span id="logged-users"><?= htmlspecialchars($logged_users) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">📶 Load Average (1, 5, 15 min):</span>
            <span id="load-avg"><?= htmlspecialchars($load_avg) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">🧟 Procesos Zombie:</span>
            <span id="zombies"><?= htmlspecialchars($zombies) ?></span>
        </div>
        <div class="refresh-info">Actualizando automáticamente cada 3 segundos...</div>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-microchip"></i> Uso de CPU/GPU</h2>
        <div class="metric-value">
            <span class="metric-label">📊 Load average:</span>
            <span id="cpu-load"><?= htmlspecialchars($cpu_load) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">⚡ Uso CPU:</span>
            <span id="cpu-usage"><?= htmlspecialchars($cpu_usage) ?>%</span>
        </div>
        <div class="metric-value">
            <span class="metric-label">🌡️ Temperatura CPU:</span>
            <span id="cpu-temp"><?= htmlspecialchars($cpu_temp) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">🎮 Uso GPU:</span>
            <span id="gpu-usage"><?= htmlspecialchars($gpu_usage) ?></span>
        </div>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-memory"></i> Uso de memoria y swap</h2>
        <div class="metric-value">
            <span class="metric-label">💾 RAM:</span>
            <span id="mem-usage"><?= htmlspecialchars($mem) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">🔀 Swap:</span>
            <span id="swap-usage"><?= htmlspecialchars($swap) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">📊 Detalles RAM:</span>
            <span id="memory-details"><?= htmlspecialchars($memory_details) ?></span>
        </div>
        <h3>Estadísticas VM:</h3>
        <pre><?= htmlspecialchars($vmstat) ?></pre>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-hdd"></i> Espacio en disco</h2>
        <div class="metric-value">
            <span class="metric-label">/ :</span>
            <span id="disk-root"><?= htmlspecialchars($disk_root) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">/home :</span>
            <span id="disk-home"><?= htmlspecialchars($disk_home) ?></span>
        </div>
        <div class="metric-value">
            <span class="metric-label">📌 Uso de Inodes:</span>
            <span id="inode-usage"><?= htmlspecialchars($inode_usage) ?></span>
        </div>
        <h3>Uso por directorio en /home:</h3>
        <pre><?= htmlspecialchars($disk_du) ?></pre>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-tasks"></i> Procesos en ejecución (Top 5 CPU)</h2>
        <pre id="top-processes"><?= htmlspecialchars($top_processes) ?></pre>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-cogs"></i> Estado de servicios</h2>
        <div class="services-grid">
            <?php foreach($services_status as $service => $status): ?>
                <div class="service-item">
                    <div class="service-name"><?= htmlspecialchars($service) ?></div>
                    <div class="<?= $status === 'activo' ? 'status-green' : 'status-red' ?>">
                        <?= htmlspecialchars($status) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-network-wired"></i> Estado de red</h2>
        <div class="metric-value">
            <span class="metric-label">🌐 IP(s):</span>
            <?= nl2br(htmlspecialchars($ip_addr)) ?>
        </div>
        
        <h3><i class="fas fa-door-open"></i> Puertos abiertos:</h3>
        <pre><?= htmlspecialchars($netstat) ?></pre>
        
        <h3><i class="fas fa-traffic-light"></i> Uso de Red:</h3>
        <pre id="network-usage"><?= htmlspecialchars($network_usage) ?></pre>
        
        <h3><i class="fas fa-satellite-dish"></i> Ping a 8.8.8.8:</h3>
        <pre><?= htmlspecialchars($ping) ?></pre>
        
        <h3><i class="fas fa-route"></i> Traceroute (máx 5 saltos):</h3>
        <pre><?= htmlspecialchars($traceroute) ?></pre>
    </div>

    <div class="metric-card">
        <h2 class="metric-title"><i class="fas fa-microchip"></i> Información hardware</h2>
        
        <?php if ($distro == 'alpine'): ?>
            <h3><i class="fas fa-microchip"></i> CPU:</h3>
            <pre><?= htmlspecialchars($cpu_info) ?></pre>
            
            <h3><i class="fas fa-memory"></i> Memoria:</h3>
            <pre><?= htmlspecialchars($memory_info) ?></pre>
            
            <h3><i class="fas fa-hdd"></i> Discos:</h3>
            <pre><?= htmlspecialchars($disk_info) ?></pre>
        <?php else: ?>
            <h3><i class="fas fa-microchip"></i> CPU:</h3>
            <pre><?= htmlspecialchars($cpu_info) ?></pre>
            
            <h3><i class="fas fa-memory"></i> Memoria:</h3>
            <pre><?= htmlspecialchars($memory_info) ?></pre>
            
            <h3><i class="fas fa-hdd"></i> Discos:</h3>
            <pre><?= htmlspecialchars($disk_info) ?></pre>
        <?php endif; ?>
    </div>
</main>

<script>
function togglePersonalizado() {
    const form = document.getElementById('formPersonalizado');
    form.classList.toggle('oculto');
}

function updateMonitorData() {
    $.ajax({
        url: 'monitor_ajax.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.error) {
                console.error('Error:', data.error);
                $('#server-status').append('<div class="error">'+data.error+'</div>');
            } else {
                // Actualizar todos los campos
                Object.keys(data).forEach(function(key) {
                    const element = $('#'+key);
                    if (element.length) {
                        element.text(data[key]);
                    }
                });
                
                // Actualizar hora de última actualización
                const now = new Date();
                $('.refresh-info').html('Última actualización: ' + now.toLocaleTimeString());
            }
            setTimeout(updateMonitorData, 3000);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            $('.refresh-info').html('Error en la actualización. Reintentando...');
            setTimeout(updateMonitorData, 5000);
        }
    });
}

$(document).ready(function() {
    updateMonitorData();
    
    $('form[method="post"]').on('submit', function() {
        $('#server-status').html('<i class="fas fa-spinner fa-spin"></i> Cambiando de servidor...');
    });
    
    // Resaltar el servidor seleccionado en el dropdown
    $('select[name="servidor"]').val('<?= strtolower($distro) ?>');
});
</script>
</body>
</html>