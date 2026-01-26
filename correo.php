<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php'; // O cambia si no usas Composer

function enviarCorreo($para, $asunto, $mensajeHTML) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';          // Cambia si usas otro proveedor
        $mail->SMTPAuth = true;
        $mail->Username = 'tucorreo@gmail.com';  // Tu correo
        $mail->Password = 'tu_clave_o_app_password'; // Usa App Password si es Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Remitente y destinatario
        $mail->setFrom('tucorreo@gmail.com', 'Panel SSH');
        $mail->addAddress($para);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $mensajeHTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
        return false;
    }
}
