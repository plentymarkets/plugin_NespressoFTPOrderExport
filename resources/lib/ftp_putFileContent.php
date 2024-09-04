<?php

require_once __DIR__ . '/SFtpClient.php';

function buildPath($directory, $filename) {
    $path = '';
    if(strlen($directory)) {
        $path .= $directory.'/';
    }
    return $path.$filename;
}

$host = SdkRestApi::getParam('host');
$username = SdkRestApi::getParam('username');
$password = SdkRestApi::getParam('password');
$port = SdkRestApi::getParam('port');

$directory = SdkRestApi::getParam('directory');
$filename = SdkRestApi::getParam('filename');
$content = SdkRestApi::getParam('content');

// check params
if(!strlen($host)) {
    throw new Exception('Host is not set.');
}

if($port <= 0 || is_null($port)) {
    throw new Exception('Port is not set.');
}

if(!strlen($username)) {
    throw new Exception('Username not set.');
}

if(!strlen($password)) {
    throw new Exception('Password not set.');
}

$path = buildPath($directory, $filename);

try {
    $connection = ssh2_connect($host, $port);
    ssh2_auth_password($connection, $username, $password);
    $sftp = ssh2_sftp($connection);

    $result = (bool)file_put_contents('ssh2.sftp://'.intval($host).'/'.$path, $content);

    ssh2_disconnect($sftp);
} catch (\Throwable $exception) {
    try {
        ssh2_disconnect($sftp);
    }
    catch (\Throwable $exception) {
        throw new Exception(__METHOD__.' '.$exception->getMessage());
    }

    throw new Exception(__METHOD__.' '.$exception->getMessage());
}

return $result;