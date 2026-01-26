<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotifier {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function sendNotification($subject, $message, $to = null) {
        if (empty($this->config['smtp_settings']['host']) || 
            empty($this->config['smtp_settings']['username'])) {
            error_log("Configuración SMTP incompleta");
            return false;
        }
        
        $to = $to ?: $this->config['notifications_email'];
        if (empty($to)) {
            error_log("No se especificó destinatario para notificación");
            return false;
        }
        
        $mail = new PHPMailer(true);
        try {
            // Configuración SMTP
            $smtp = $this->config['smtp_settings'];
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['port'];
            
            // Remitente
            $fromEmail = $smtp['from_email'] ?: $smtp['username'];
            $mail->setFrom($fromEmail, $smtp['from_name']);
            
            // Destinatario
            $mail->addAddress($to);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Error al enviar notificación: " . $e->getMessage());
            return false;
        }
    }
    
    public function notifyLogin($username, $ip) {
        if (!$this->config['notifications']['notify_login']) return false;
        
        $subject = "Nuevo acceso al Panel SSH";
        $message = "<h2>Se ha detectado un nuevo acceso al sistema</h2>";
        $message .= "<p><strong>Usuario:</strong> $username</p>";
        $message .= "<p><strong>IP:</strong> $ip</p>";
        $message .= "<p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>";
        
        return $this->sendNotification($subject, $message);
    }
    
    public function notifySSHConnection($server, $user, $ip) {
        if (!$this->config['notifications']['notify_ssh']) return false;
        
        $subject = "Nueva conexión SSH establecida";
        $message = "<h2>Se ha conectado a un servidor SSH</h2>";
        $message .= "<p><strong>Servidor:</strong> $server</p>";
        $message .= "<p><strong>Usuario SSH:</strong> $user</p>";
        $message .= "<p><strong>IP origen:</strong> $ip</p>";
        
        return $this->sendNotification($subject, $message);
    }
    
    public function notifyError($errorType, $details) {
        if (!$this->config['notifications']['notify_errors']) return false;
        
        $subject = "[CRÍTICO] Error en el Panel SSH: $errorType";
        $message = "<h2>Se ha producido un error crítico</h2>";
        $message .= "<p><strong>Tipo:</strong> $errorType</p>";
        $message .= "<p><strong>Detalles:</strong></p>";
        $message .= "<pre>" . htmlspecialchars($details) . "</pre>";
        $message .= "<p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>";
        
        return $this->sendNotification($subject, $message);
    }
}
?>