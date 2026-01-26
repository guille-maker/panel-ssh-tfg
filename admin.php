<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si usas AJAX, detecta
    $ajax = $_POST['ajax'] ?? 0;
    $accion = $_POST['accion'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    $clave = $_POST['clave'] ?? '';
    $comando = $_POST['comando'] ?? '';
    $extra = $_POST['extra'] ?? '';

    if ($ajax) {
        // Aquí procesas las acciones: crear usuario, eliminar, cambiar pass, ejecutar comando...
        switch ($accion) {
            case 'crear_usuario':
                // lógica para crear usuario
                echo "Usuario $usuario creado correctamente.";
                break;
            case 'eliminar_usuario':
                // lógica para eliminar usuario
                echo "Usuario $usuario eliminado.";
                break;
            case 'cambiar_password':
                // lógica para cambiar contraseña
                echo "Contraseña cambiada para $usuario.";
                break;
            case 'ejecutar_comando':
                // ejemplo para ejecutar comando (¡cuidado con seguridad!)
                $salida = shell_exec($comando);
                echo $salida;
                break;
            default:
                echo "Acción no reconocida.";
        }
    } else {
        echo "No es una petición AJAX.";
    }
} else {
    echo "Método no permitido.";
}
?>
