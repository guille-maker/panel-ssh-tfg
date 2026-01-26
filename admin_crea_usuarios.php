<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'admin') {
    die("Acceso restringido.");
}

require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_usuario = $_POST['nuevo_usuario'] ?? '';
    $nueva_clave = $_POST['nueva_clave'] ?? '';

    if ($nuevo_usuario && $nueva_clave) {
        $clave_cifrada = password_hash($nueva_clave, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, clave) VALUES (:usuario, :clave)");
        
        try {
            $stmt->execute(['usuario' => $nuevo_usuario, 'clave' => $clave_cifrada]);
            $mensaje = "Usuario creado correctamente";
        } catch (PDOException $e) {
            $mensaje = "Error al crear el usuario: " . $e->getMessage();
        }
    } else {
        $mensaje = "Rellena todos los campos.";
    }
}
?>

<h2>Crear nuevo usuario</h2>
<?php if (isset($mensaje)) echo "<p>$mensaje</p>"; ?>
<form method="post">
    Usuario: <input type="text" name="nuevo_usuario"><br>
    Contraseña: <input type="password" name="nueva_clave"><br>
    <input type="submit" value="Crear">
</form>
