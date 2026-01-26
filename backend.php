<?php 
$ssh = new SSH2($servidores[$servidor]['host'], $servidores[$servidor]['puerto']);
if (!$ssh->login($servidores[$servidor]['usuario'], $servidores[$servidor]['clave'])) {
    echo json_encode(['error' => 'Error de conexión SSH']);
    exit;
}

$output = $ssh->exec($comando);
echo json_encode(['output' => $output]);
?>