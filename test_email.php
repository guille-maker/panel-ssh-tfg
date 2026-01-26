<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

// Cargar configuración
$configFile = 'config/panel_config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

$resultado = '';
$error = '';

// Probar envío de correo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración SMTP desde la configuración
        $mail->isSMTP();
        $mail->Host = $config['smtp_settings']['host'] ?? 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_settings']['username'] ?? '';
        $mail->Password = $config['smtp_settings']['password'] ?? '';
        $mail->Port = $config['smtp_settings']['port'] ?? 2525;
        $mail->SMTPSecure = 'tls';
        
        // Remitente (usamos un email válido genérico)
        $mail->setFrom('notificaciones@panel-ssh.com', 'Sistema de Notificaciones');
        
        // Destinatario
        $testEmail = $_POST['test_email'];
        $mail->addAddress($testEmail);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'Prueba de correo desde el panel SSH';
        $mail->Body = '<h1>¡Esto es una prueba!</h1>
                      <p>Si recibes este correo, la configuración SMTP está funcionando correctamente.</p>
                      <p>Fecha: '.date('d/m/Y H:i:s').'</p>';
        
        if ($mail->send()) {
            $resultado = "✅ Correo enviado exitosamente a: $testEmail";
            $resultado .= "<br>Verifica en <a href='https://mailtrap.io/inboxes' target='_blank'>Mailtrap Inbox</a>";
        } else {
            $error = "❌ Error al enviar el correo: " . $mail->ErrorInfo;
        }
    } catch (Exception $e) {
        $error = "❌ Excepción al enviar correo: " . $e->getMessage();
        
        // Mensaje más amigable para errores comunes
        if (strpos($e->getMessage(), 'Invalid address') !== false) {
            $error .= "<br>La dirección de correo no es válida";
        } elseif (strpos($e->getMessage(), 'Could not authenticate') !== false) {
            $error .= "<br>Error de autenticación. Verifica:";
            $error .= "<br>- Usuario y contraseña SMTP";
            $error .= "<br>- Que el puerto sea correcto (2525 para Mailtrap)";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de Correo</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #0e101a;
            color: #fff;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .container {
            background-color: #1a1f2e;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(90, 133, 255, 0.3);
        }
        h1 {
            color: #a0c4ff;
            margin-top: 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #cbd5ff;
        }
        input[type="email"] {
            background-color: #0e162b;
            color: #ffffff;
            border: 1px solid #3f587e;
            padding: 8px 12px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            margin-bottom: 15px;
        }
        button {
            background-color: #3a5dfb;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background-color: #5671fc;
        }
        .success {
            color: #4CAF50;
            margin: 15px 0;
            padding: 10px;
            background-color: #1e2a1e;
            border-radius: 4px;
        }
        .error {
            color: #f44336;
            margin: 15px 0;
            padding: 10px;
            background-color: #2a1e1e;
            border-radius: 4px;
        }
        .config-info {
            background-color: #0e162b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
        }
        a {
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Prueba de Configuración de Correo</h1>
        
        <div class="config-info">
            <h3>Configuración Actual:</h3>
            <p><strong>Servidor SMTP:</strong> <?= $config['smtp_settings']['host'] ?? 'sandbox.smtp.mailtrap.io' ?></p>
            <p><strong>Usuario SMTP:</strong> <?= !empty($config['smtp_settings']['username']) ? '••••••••' : 'No configurado' ?></p>
            <p><strong>Puerto:</strong> <?= $config['smtp_settings']['port'] ?? 2525 ?></p>
            <p><strong>Email para notificaciones:</strong> <?= $config['notifications_email'] ?? 'No configurado' ?></p>
        </div>
        
        <?php if ($resultado): ?>
            <div class="success"><?= $resultado ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post">
            <label for="test_email">Enviar prueba a este correo:</label>
            <input type="email" id="test_email" name="test_email" 
                   value="<?= htmlspecialchars($config['notifications_email'] ?? '') ?>" required>
            
            <button type="submit">Enviar Correo de Prueba</button>
        </form>
        
        <div style="margin-top: 30px;">
            <h3>Notas importantes para Mailtrap:</h3>
            <ul>
                <li>Los correos <strong>no se envían realmente</strong>, solo aparecen en el panel de Mailtrap</li>
                <li>Accede a <a href="https://mailtrap.io/inboxes" target="_blank">tu inbox en Mailtrap</a> para ver los resultados</li>
                <li>No uses tu contraseña real de email, Mailtrap provee credenciales específicas</li>
            </ul>
        </div>
    </div>
</body>
</html>