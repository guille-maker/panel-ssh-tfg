<?php
session_start();

if (isset($_SESSION['usuario'])) {
    header('Location: panel.php');
    exit;
}

$login_fallido = false; // ←✅ Inicializamos para evitar warning
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_usuario = $_POST['usuario'] ?? '';
    $input_clave = $_POST['clave'] ?? '';

    if ($input_usuario && $input_clave) {
        // Conexión a la base de datos
      require 'conexion.php';
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Buscar usuario
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario");
            $stmt->execute([':usuario' => $input_usuario]);
            $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar clave
            if ($usuario_db && password_verify($input_clave, $usuario_db['clave'])) {
                $_SESSION['usuario'] = $usuario_db['usuario'];
                $_SESSION['rol'] = 'admin';
                header('Location: panel.php');
                exit;
            } else {
                $login_fallido = true;
                $error = "Usuario o contraseña incorrectos";
            }

        } catch (PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    } else {
        $error = "Completa todos los campos.";
    }

    // Registrar acceso fallido solo si el login falló
    if ($login_fallido) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $usuario_intentado = htmlspecialchars($input_usuario);
        $fecha = date('Y-m-d H:i:s');
        $log = "[$fecha] Intento fallido desde IP $ip con usuario '$usuario_intentado'\n";

        // Asegura que el directorio logs/ exista o créalo manualmente
        file_put_contents(__DIR__ . '/logs/accesos_fallidos.log', $log, FILE_APPEND);
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema</title>
    <style>
        :root {
            --primary-color: #1a237e;
            --secondary-color: #0d47a1;
            --accent-color: #2196f3;
            --text-color: #e1f5fe;
            --error-color: #ff5252;
            --input-bg: #0d1b2a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-color);
        }
        
        .login-container {
            background: rgba(13, 27, 42, 0.8);
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #64b5f6, #2196f3);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .login-header p {
            color: rgba(225, 245, 254, 0.7);
            font-size: 0.9rem;
        }
        
        .login-form .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: rgba(225, 245, 254, 0.9);
        }
        
        .login-form input {
            width: 100%;
            padding: 12px 15px;
            background: var(--input-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .login-form input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2);
        }
        
        .login-form button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #1976d2, #2196f3);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        
        .login-form button:hover {
            background: linear-gradient(to right, #1565c0, #1976d2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }
        
        .login-form button:active {
            transform: translateY(0);
        }
        
        .error-message {
            color: var(--error-color);
            font-size: 0.9rem;
            margin-top: 1rem;
            text-align: center;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        .branding {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: rgba(225, 245, 254, 0.5);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Bienvenido</h1>
            <p>Ingrese sus credenciales para acceder al sistema</p>
        </div>
        
        <form method="post" class="login-form">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required placeholder="Ingrese su usuario">
            </div>
            
            <div class="form-group">
                <label for="clave">Contraseña</label>
                <input type="password" id="clave" name="clave" required placeholder="Ingrese su contraseña">
            </div>
            
            <button type="submit">Iniciar Sesión</button>
            
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
        </form>
        
        <div class="branding">
            <p>Sistema de Administración SSHP &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</body>
</html>