<?php
include('vendor/autoload.php');

use phpseclib3\Net\SSH2;

$config = require 'ssh_config.php';

$ssh = new SSH2($config['host'], $config['port']);

if (!$ssh->login($config['user'], $config['password'])) {
    exit('Fallo en el login SSH');
}

$resultado = $ssh->exec($comando);
