<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Solo acceso para admin
// if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
//     header('Location: index.php');
//     exit();
// }


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_usuario = $_POST['usuario'] ?? '';
    $clave_plana = $_POST['clave'] ?? '';

    if ($nuevo_usuario && $clave_plana) {
        $clave_hash = password_hash($clave_plana, PASSWORD_DEFAULT);

        $host = 'localhost';
        $dbname = 'tfg';
        $user = 'root';
        $pass = 'root';

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, clave) VALUES (:usuario, :clave)");
            $stmt->execute([
                ':usuario' => $nuevo_usuario,
                ':clave' => $clave_hash
            ]);

            echo "<p style='color: green;'>✅ Usuario creado correctamente.</p>";
            // header("Location: panel.php"); exit;

        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Por favor, completa todos los campos.</p>";
    }
}
header("Location: panel.php");
exit();
?>
