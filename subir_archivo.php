use phpseclib3\Net\SFTP;

$sftp = new SFTP($host);
if (!$sftp->login($usuario, $clave)) {
    exit('❌ Fallo en la conexión SFTP');
}

// SUBIR ARCHIVO
$archivo_local = 'archivo_local.txt';
$archivo_remoto = '/home/usuario/archivo_remoto.txt';

if ($sftp->put($archivo_remoto, $archivo_local, SFTP::SOURCE_LOCAL_FILE)) {
    echo "✅ Archivo transferido correctamente.";
} else {
    echo "❌ Error al transferir el archivo.";
}
