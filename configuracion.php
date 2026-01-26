<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Cargar configuración existente
$configFile = 'config/panel_config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [
    'theme' => 'blue',
    'timezone' => 'Europe/Madrid',
    'language' => 'es',
    'date_format' => 'd/m/Y',
    'ssh_settings' => [
        'default_port' => 22,
        'timeout' => 10,
        'keepalive' => true
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'medium',
        'retention' => 30
    ],
    'notifications' => [
        'email' => '',
        'alert_config_changes' => true
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'ssh_') === 0) {
            $config['ssh_settings'][substr($key, 4)] = $value;
        } elseif (strpos($key, 'log_') === 0) {
            $config['logging'][substr($key, 4)] = $value;
        } elseif (strpos($key, 'notify_') === 0) {
            $config['notifications'][substr($key, 7)] = $value;
        } else {
            $config[$key] = $value;
        }
    }

    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    $_SESSION['config_updated'] = true;
    header("Location: configuracion.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel SSH - Configuración</title>
    <style>
        :root {
            --color-primary: #3a5dfb;
            --color-bg-dark: #0e101a;
            --color-bg-card: #1a1f2e;
            --color-bg-sidebar: #1e1e2f;
            --color-text: #d1d9ff;
            --color-text-light: #a0c4ff;
            --color-border: #3f587e;
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
        }
        
        aside {
            width: 250px;
            background: var(--color-bg-sidebar);
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar-logo {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 30px;
            color: var(--color-text-light);
        }
        
        nav a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #c0c0c0;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        nav a:hover, nav a.active {
            background: #2c2c3e;
            color: var(--color-text-light);
            border-left: 4px solid var(--color-primary);
        }
        
        nav a i {
            margin-right: 12px;
        }
        
        main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .config-section {
            background: var(--color-bg-card);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--color-border);
        }
        
        .config-section h2 {
            color: var(--color-text-light);
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--color-border);
            padding-bottom: 10px;
        }
        
        .config-item {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: var(--color-text-light);
        }
        
        input, select, textarea {
            background-color: #0e162b;
            color: white;
            border: 1px solid var(--color-border);
            padding: 10px 15px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
        }
        
        .save-btn {
            background-color: var(--color-primary);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
        }
        
        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background-color: #4CAF50;
            color: white;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: var(--color-bg-card);
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
        }
        
        .tab.active {
            background-color: var(--color-primary);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>

<aside>
    <div class="sidebar-logo">⚙ Panel SSH</div>
    <nav>
        <a href="panel.php"><i>🖥️</i> Terminal</a>
        <a href="usuarios.php"><i>👥</i> Usuarios</a>
        <a href="archivos.php"><i>📁</i> Archivos</a>
        <a href="monitor.php"><i>📊</i> Monitor</a>
        <a href="procesos.php"><i>⚙️</i> Procesos</a>
        <a href="logs.php"><i>📝</i> Logs</a>
        <a href="configuracion.php" class="active"><i>🔧</i> Configuración</a>
        <a href="seguridad.php"><i>🔐</i>Seguridad</a>
        <a href="conexion_multiserver.php"><i>🔗</i>Crons y paquetes</a>
    </nav>
</aside>

<main>
    <?php if (isset($_SESSION['config_updated'])): ?>
        <div class="notification">
            ✅ Configuración actualizada correctamente
        </div>
        <?php unset($_SESSION['config_updated']); ?>
    <?php endif; ?>

    <h1>Configuración del Panel</h1>

    <div class="tabs">
        <div class="tab active" onclick="openTab(event, 'general')">General</div>
        <div class="tab" onclick="openTab(event, 'ssh')">SSH</div>
        <div class="tab" onclick="openTab(event, 'logging')">Registros</div>
        <div class="tab" onclick="openTab(event, 'notifications')">Notificaciones</div>
    </div>

    <form method="post">
        <div id="general" class="tab-content active">
            <div class="config-section">
                <h2>⚙ Preferencias Generales</h2>
                <div class="config-item">
                    <label for="theme">Tema visual:</label>
                    <select id="theme" name="theme">
                        <option value="light" <?= $config['theme'] === 'light' ? 'selected' : '' ?>>Claro</option>
                        <option value="dark" <?= $config['theme'] === 'dark' ? 'selected' : '' ?>>Oscuro</option>
                        <option value="blue" <?= $config['theme'] === 'blue' ? 'selected' : '' ?>>Azul</option>
                    </select>
                </div>
                <div class="config-item">
                    <label for="timezone">Zona horaria:</label>
                    <select id="timezone" name="timezone">
                        <option value="Europe/Madrid" <?= $config['timezone'] === 'Europe/Madrid' ? 'selected' : '' ?>>Madrid</option>
                        <option value="UTC" <?= $config['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                    </select>
                </div>
                <div class="config-item">
                    <label for="language">Idioma:</label>
                    <select id="language" name="language">
                        <option value="es" <?= $config['language'] === 'es' ? 'selected' : '' ?>>Español</option>
                        <option value="en" <?= $config['language'] === 'en' ? 'selected' : '' ?>>Inglés</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="ssh" class="tab-content">
            <div class="config-section">
                <h2>🔌 Configuración SSH</h2>
                <div class="config-item">
                    <label for="ssh_default_port">Puerto predeterminado:</label>
                    <input type="number" id="ssh_default_port" name="ssh_default_port" value="<?= $config['ssh_settings']['default_port'] ?>">
                </div>
                <div class="config-item">
                    <label for="ssh_timeout">Timeout (segundos):</label>
                    <input type="number" id="ssh_timeout" name="ssh_timeout" value="<?= $config['ssh_settings']['timeout'] ?>">
                </div>
                <div class="config-item">
                    <label for="ssh_keepalive">Mantener conexión activa:</label>
                    <select id="ssh_keepalive" name="ssh_keepalive">
                        <option value="1" <?= $config['ssh_settings']['keepalive'] ? 'selected' : '' ?>>Sí</option>
                        <option value="0" <?= !$config['ssh_settings']['keepalive'] ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="logging" class="tab-content">
            <div class="config-section">
                <h2>📄 Configuración de Registros</h2>
                <div class="config-item">
                    <label for="log_enabled">Habilitar registro:</label>
                    <select id="log_enabled" name="log_enabled">
                        <option value="1" <?= $config['logging']['enabled'] ? 'selected' : '' ?>>Activado</option>
                        <option value="0" <?= !$config['logging']['enabled'] ? 'selected' : '' ?>>Desactivado</option>
                    </select>
                </div>
                <div class="config-item">
                    <label for="log_level">Nivel de detalle:</label>
                    <select id="log_level" name="log_level">
                        <option value="minimal" <?= $config['logging']['level'] === 'minimal' ? 'selected' : '' ?>>Mínimo</option>
                        <option value="medium" <?= $config['logging']['level'] === 'medium' ? 'selected' : '' ?>>Medio</option>
                    </select>
                </div>
                <div class="config-item">
                    <label for="log_retention">Días de retención:</label>
                    <input type="number" id="log_retention" name="log_retention" value="<?= $config['logging']['retention'] ?>">
                </div>
            </div>
        </div>

        <div id="notifications" class="tab-content">
            <div class="config-section">
                <h2>🔔 Notificaciones</h2>
                <div class="config-item">
                    <label for="notify_email">Email para notificaciones:</label>
                    <input type="email" id="notify_email" name="notify_email" value="<?= $config['notifications']['email'] ?>">
                </div>
                <div class="config-item">
                    <label>
                        <input type="checkbox" name="notify_alert_config_changes" value="1" <?= $config['notifications']['alert_config_changes'] ? 'checked' : '' ?>>
                        Notificar cambios de configuración
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="save-btn">Guardar Configuración</button>
    </form>
</main>

<script>
    function openTab(evt, tabName) {
        const tabContents = document.querySelectorAll(".tab-content");
        tabContents.forEach(tab => tab.classList.remove("active"));
        
        const tabs = document.querySelectorAll(".tab");
        tabs.forEach(tab => tab.classList.remove("active"));
        
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // Aplicar tema al cargar
    document.body.setAttribute("data-theme", "<?= $config['theme'] ?>");
    
    // Cambiar tema dinámicamente
    document.getElementById("theme").addEventListener("change", function() {
        document.body.setAttribute("data-theme", this.value);
    });
</script>

</body>
</html>