<?php
require 'conexion_db.php';

$resultado = $conn->query("SELECT * FROM servidores");
?>

<form method="post" action="panel.php">
    <label>Selecciona un servidor:</label>
    <select name="servidor_id">
        <?php while ($row = $resultado->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?> (<?= $row['host'] ?>)</option>
        <?php endwhile; ?>
    </select>
    <br><br>
    <input type="submit" value="Conectar">
</form>
