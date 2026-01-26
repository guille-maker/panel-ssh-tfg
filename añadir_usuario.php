<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Administrador</title>
    <style>
        body {
            background-color: #0d1b2a;
            color: #ffffff;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        form {
            background-color: #1b263b;
            padding: 20px;
            border-radius: 10px;
        }
        input {
            display: block;
            margin-bottom: 10px;
            padding: 8px;
            width: 100%;
        }
        button {
            background-color: #415a77;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
        }
        button:hover {
            background-color: #778da9;
        }
    </style>
</head>
<body>
    <form action="crear_usuario.php" method="POST">
        <h2>Añadir nuevo administrador</h2>
        <input type="text" name="usuario" placeholder="Usuario" required>
        <input type="password" name="clave" placeholder="Contraseña" required>
        <button type="submit">Crear usuario</button>
    </form>
</body>
</html>
