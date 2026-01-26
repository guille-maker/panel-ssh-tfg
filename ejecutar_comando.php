<?php
// ejecutar_comando.php
require 'conexion_multiserver.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['comando'])) {
        // Adaptar comando según distro
        $comando = $data['comando'];
        
        // Detectar si es Alpine (usa apk en lugar de apt)
        if (strpos($comando, 'apt-get') !== false) {
            $comando = str_replace('apt-get', 'apk', $comando);
            $comando = str_replace('install', 'add', $comando);
            $comando = str_replace('remove', 'del', $comando);
        }
        
        $resultados = ejecutar_en_todos($comando);
        echo json_encode($resultados);
    } else {
        echo json_encode(['error' => 'Comando no especificado']);
    }
} else {
    echo json_encode(['error' => 'Método no permitido']);
}
?>