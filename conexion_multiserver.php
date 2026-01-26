<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel SSH</title>
    <style>
        /* ===== ESTILOS GENERALES ===== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            height: 100vh;
            background-color: #0e101a;
            color: #d1d9ff;
        }
        
        /* ===== BARRA LATERAL ===== */
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
        
        /* ===== CONTENIDO PRINCIPAL ===== */
        main {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background-color: #0e101a;
        }
        
        h1, h2, h3 {
            color: #a0c4ff;
            margin-bottom: 15px;
        }
        
        /* ===== TARJETAS DE CONTENIDO ===== */
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
        
        /* ===== FORMULARIOS ===== */
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
        
        input[type="text"], 
        input[type="password"], 
        input[type="number"], 
        select,
        textarea {
            background-color: #0e162b;
            color: #ffffff;
            border: 1px solid #3f587e;
            padding: 10px;
            margin-top: 5px;
            border-radius: 8px;
            width: 100%;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        input[type="submit"], 
        button {
            background-color: #3a5dfb;
            border: none;
            color: white;
            padding: 10px 16px;
            margin-top: 10px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        button:hover, 
        input[type="submit"]:hover {
            background-color: #5671fc;
        }
        
        /* ===== RESULTADOS Y PRE ===== */
        pre {
            background-color: #1a1f2e;
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
            white-space: pre-wrap;
            color: #d1d9ff;
            border: 1px solid #3f587e;
            font-family: 'Courier New', monospace;
        }
        
        /* ===== ESTADOS ===== */
        .conectado {
            color: #71ff71;
            font-weight: bold;
        }
        
        .oculto {
            display: none;
        }
        
        .error {
            color: #ff6b6b;
            padding: 10px;
            background: #2e0a0a;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        /* ===== PESTAÑAS ===== */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #3f587e;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: #1a1f2e;
            border: none;
            color: #d1d9ff;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .tab-btn.active {
            background: #3a5dfb;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* ===== GRID DE RESULTADOS ===== */
        .resultados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .servidor-resultado {
            background: #0e162b;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #3a5dfb;
        }
        
        .servidor-resultado h4 {
            margin-top: 0;
            color: #a0c4ff;
        }
        
        /* ===== FORMULARIOS EN LINEA ===== */
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .form-inline .form-group {
            flex: 1;
        }
        
        /* ===== BADGES ===== */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background-color: #2ecc71;
            color: white;
        }
        
        .badge-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .badge-warning {
            background-color: #f39c12;
            color: white;
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
        <a href="procesos.php">🔍 Procesos</a>
        <a href="logs.php"><i>📄</i>Logs</a>
        <a href="configuracion.php"><i>🔧</i>Configuración</a>
        <a href="seguridad.php">🔐 Seguridad</a>
        <a href="conexion_multiserver.php"><i>🔗</i>Crons y paquetes</a>
    </nav>
</aside>

<main>
    <div class="metric-card">
        <h1>🔐 Gestión Avanzada</h1>
        <p>👤 Bienvenido, <strong>admin</strong></p>
        <p>🖧 Conectado a: <span class="conectado">127.0.0.1 como root (Alpine)</span></p>
        
        <button onclick="togglePersonalizado()">🔧 Mostrar/Ocultar conexión</button>
        <form method="post" id="formPersonalizado" class="oculto">
            <!-- Formulario de conexión -->
        </form>
    </div>

    <!-- Panel de pestañas principal -->
    <div class="metric-card">
        <div class="tabs">
            <button class="tab-btn active" data-target="#cron">Cron Jobs</button>
            <button class="tab-btn" data-target="#tareas">Tareas</button>
            <button class="tab-btn" data-target="#paquetes">Paquetes</button>
        </div>

        <!-- Sección CRON JOBS -->
        <div id="cron" class="tab-content active">
            <h3><i class="fas fa-clock"></i> Gestión de Cron Jobs</h3>
            <div class="form-inline">
                <div class="form-group">
                    <input type="text" id="nuevoCron" placeholder="* * * * * /ruta/script.sh" class="cron-input">
                </div>
                <button onclick="agregarCron()">➕ Agregar</button>
            </div>
            
            <div class="resultados-grid" id="cronResults">
                <!-- Ejemplo de datos estáticos (se reemplazarán por dinámicos) -->
                <div class="servidor-resultado">
                    <h4>Ubuntu</h4>
                    <pre># Backup diario
0 3 * * * /usr/bin/backup.sh

# Limpieza semanal
0 4 * * 1 /usr/bin/cleanup.sh</pre>
                    <button class="btn-small" onclick="eliminarCron('ubuntu', 1)">🗑️ Eliminar</button>
                </div>
                
                <div class="servidor-resultado">
                    <h4>Debian</h4>
                    <pre># Actualización diaria
0 2 * * * /usr/bin/update-system.sh</pre>
                </div>
                
                <div class="servidor-resultado">
                    <h4>Alpine</h4>
                    <pre># Monitoreo cada 5 mins
*/5 * * * * /usr/bin/monitor.sh</pre>
                </div>
            </div>
        </div>

        <!-- Sección TAREAS PROGRAMADAS -->
        <div id="tareas" class="tab-content">
            <h3><i class="fas fa-tasks"></i> Tareas Programadas</h3>
            <div class="form-inline">
                <div class="form-group">
                    <select id="tipoTarea">
                        <option value="timer">Timer</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" id="nombreTarea" placeholder="Nombre de la tarea">
                </div>
                <button onclick="crearTarea()">🛠️ Crear</button>
            </div>
            
            <div class="resultados-grid" id="tareasResults">
                <div class="servidor-resultado">
                    <h4>Ubuntu</h4>
                    <div class="tarea-item">
                        <span class="badge badge-success">Activo</span>
                        <strong>backup.timer</strong>
                        <p>Ejecuta backup.service diariamente a las 3:00 AM</p>
                        <button class="btn-small" onclick="gestionTarea('ubuntu', 'backup', 'stop')">⏸ Detener</button>
                    </div>
                </div>
                
                <div class="servidor-resultado">
                    <h4>Debian</h4>
                    <div class="tarea-item">
                        <span class="badge badge-warning">Inactivo</span>
                        <strong>update.timer</strong>
                        <p>Actualizaciones automáticas</p>
                        <button class="btn-small" onclick="gestionTarea('debian', 'update', 'start')">▶ Iniciar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección GESTIÓN DE PAQUETES -->
        <div id="paquetes" class="tab-content">
            <h3><i class="fas fa-box-open"></i> Gestión de Paquetes</h3>
            <div class="form-inline">
                <div class="form-group">
                    <select id="accionPaquete">
                        <option value="install">Instalar</option>
                        <option value="remove">Eliminar</option>
                        <option value="update">Actualizar</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" id="nombrePaquete" placeholder="Nombre del paquete">
                </div>
                <button onclick="gestionarPaquete()">⚡ Ejecutar</button>
            </div>
            
            <div class="resultados-grid" id="paquetesResults">
                <div class="servidor-resultado">
                    <h4>Ubuntu</h4>
                    <div class="paquete-item">
                        <strong>nginx</strong>
                        <span class="badge badge-success">1.18.0-0ubuntu1</span>
                        <button class="btn-small" onclick="accionPaquete('ubuntu', 'nginx', 'remove')">🗑️ Eliminar</button>
                    </div>
                    <div class="paquete-item">
                        <strong>mysql-server</strong>
                        <span class="badge badge-warning">8.0.25-0ubuntu0.20.04.1</span>
                    </div>
                </div>
                
                <div class="servidor-resultado">
                    <h4>Alpine</h4>
                    <div class="paquete-item">
                        <strong>apache2</strong>
                        <span class="badge badge-success">2.4.46-r0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Funciones JavaScript para interactuar con los paneles

// Ejemplo de función para agregar un cron job
function agregarCron() {
    const cronJob = document.getElementById('nuevoCron').value;
    if (!cronJob) return;
    
    // Aquí iría la llamada AJAX real al servidor
    console.log(`Agregando cron job: ${cronJob}`);
    
    // Ejemplo de cómo agregar dinámicamente
    const cronResults = document.getElementById('cronResults');
    const nuevoCron = document.createElement('div');
    nuevoCron.className = 'servidor-resultado';
    nuevoCron.innerHTML = `
        <h4>Nuevo Servidor</h4>
        <pre>${cronJob}</pre>
        <button class="btn-small" onclick="this.parentElement.remove()">🗑️ Eliminar</button>
    `;
    cronResults.appendChild(nuevoCron);
    
    // Limpiar el input
    document.getElementById('nuevoCron').value = '';
}

// Funciones de ejemplo para las otras secciones
function crearTarea() {
    const nombre = document.getElementById('nombreTarea').value;
    const tipo = document.getElementById('tipoTarea').value;
    console.log(`Creando tarea ${tipo}: ${nombre}`);
    // Lógica real de creación...
}

function gestionarPaquete() {
    const accion = document.getElementById('accionPaquete').value;
    const paquete = document.getElementById('nombrePaquete').value;
    console.log(`Ejecutando ${accion} en ${paquete}`);
    // Lógica real de gestión de paquetes...
}

// Control de pestañas (se mantiene igual)
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        btn.classList.add('active');
        document.querySelector(btn.dataset.target).classList.add('active');
    });
});

// Función para mostrar/ocultar formulario de conexión
function togglePersonalizado() {
    const form = document.getElementById('formPersonalizado');
    form.classList.toggle('oculto');
}

</script>
</body>
</html>