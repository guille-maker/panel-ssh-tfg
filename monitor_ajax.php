<?php
session_start();
require 'conexion.php';
require 'conexion_ssh.php';

function exec_ssh($ssh, $cmd) {
    $output = $ssh->exec($cmd);
    if (stripos($output, 'command not found') !== false) {
        return false;
    }
    return trim($output);
}

header('Content-Type: application/json');

try {
    $data = [];
    
    // Obtener datos del sistema
    $data['cpu_load'] = exec_ssh($ssh, "top -bn1 | grep 'load average' | awk '{print \$10, \$11, \$12}'") ?: 'No disponible';
    $data['cpu_usage'] = exec_ssh($ssh, "mpstat 1 1 | awk '/Average/ && \$3 ~ /[0-9.]+/ {print 100 - \$12 \"%\"}'") ?: 'mpstat no disponible';
    $data['mem'] = exec_ssh($ssh, "free -m | awk 'NR==2{printf \"%s/%s MB (%.2f%%)\", \$3,\$2,\$3*100/\$2 }'") ?: 'No disponible';
    $data['swap'] = exec_ssh($ssh, "free -m | awk 'NR==3{printf \"%s/%s MB (%.2f%%)\", \$3,\$2,\$3*100/\$2 }'") ?: 'No disponible';
    $data['vmstat'] = exec_ssh($ssh, "vmstat 1 2 | tail -1") ?: 'No disponible';
    $data['disk_root'] = exec_ssh($ssh, "df -h / | awk 'NR==2{print \$3 \" usados de \" \$2 \" (\" \$5 \")\"}'") ?: 'No disponible';
    $data['disk_home'] = exec_ssh($ssh, "df -h /home | awk 'NR==2{print \$3 \" usados de \" \$2 \" (\" \$5 \")\"}'") ?: 'No disponible';
    $data['top_processes'] = exec_ssh($ssh, "ps aux --sort=-%cpu | head -n 6") ?: 'No disponible';
    $data['uptime'] = exec_ssh($ssh, "uptime -p") ?: 'No disponible';
    // Agregar estas líneas al array $data en monitor_ajax.php
$data['cpu_temp'] = exec_ssh($ssh, "sensors | grep 'Core' | awk '{print \$3}' | head -1") ?: 'No disponible';
$data['gpu_usage'] = exec_ssh($ssh, "nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits 2>/dev/null | head -1") ?: 'GPU no detectada';
$data['network_usage'] = exec_ssh($ssh, "ip -s -h link | grep -A 1 'eth0\\|ens3\\|wlan0'") ?: 'Datos de red no disponibles';
$data['logged_users'] = exec_ssh($ssh, "who | wc -l") ?: 'No disponible';
$data['load_avg'] = exec_ssh($ssh, "cat /proc/loadavg | awk '{print \$1,\$2,\$3}'") ?: 'No disponible';
$data['memory_details'] = exec_ssh($ssh, "free -h | grep 'Mem:' | awk '{print \"Total: \" \$2 \", Usada: \" \$3 \", Libre: \" \$4 \", Caché: \" \$6}'") ?: 'No disponible';
$data['zombies'] = exec_ssh($ssh, "ps aux | grep 'defunct' | grep -v grep | wc -l") ?: 'No disponible';
$data['inode_usage'] = exec_ssh($ssh, "df -i / | awk 'NR==2{print \"Usados: \" \$3 \"/\" \$2 \" (\" \$5 \")\"}'") ?: 'No disponible';
    // Estado de servicios
    $services = ['ssh', 'nginx', 'mysql'];
    $data['services_status'] = [];
    foreach($services as $service) {
        $status = exec_ssh($ssh, "systemctl is-active $service");
        if ($status === false) {
            $pgrep = exec_ssh($ssh, "pgrep -x $service");
            $data['services_status'][$service] = $pgrep ? 'activo (por proceso)' : 'inactivo';
        } else {
            $data['services_status'][$service] = $status;
        }
    }
    
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

?>