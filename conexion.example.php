<?php
$host = 'localhost';
$db   = 'tfg';
$user = 'USUARIO_MYSQL';
$pass = 'PASSWORD_MYSQL';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error de conexión";
    exit;
}
