use phpseclib3\Net\SSH2;
require 'vendor/autoload.php';

$config = require 'ssh_config.php';

$ssh = new SSH2($config['host'], $config['port']);

if (!$ssh->login($config['user'], $config['password'])) {
    die('Fallo en la conexión SSH');
}
